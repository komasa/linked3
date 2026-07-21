<?php

declare(strict_types=1);
/**
 * Safe_Remote — replaces the deprecated SSRF-vulnerable proxy.php from
 * linked v2.9.6 (which had CURLOPT_FOLLOWLOCATION=true + SSL_VERIFYPEER=false
 * and only blocked same-host → trivial SSRF to 169.254.169.254 etc.).
 *
 * Hardening:
 *   1) Domain whitelist (default push-engine endpoints only).
 *   2) Resolve host → block private IP ranges + loopback + link-local.
 *   3) Force SSL verification ON (WP default).
 *   4) Cap redirects to 3, reject cross-host redirects.
 *   5) Timeout enforced; circuit-breaker per host.
 *
 * @package Linked3
 * @subpackage Http
 */

namespace Linked3\Includes\Http;

if (!defined('ABSPATH')) {
    exit;
}

final class SafeRemote
{
    /** @var array<string> Default allow-list of public push endpoints. */
    private static $default_hosts = [
        // Push endpoints (Baidu / Bing / Google / Toutiao / Indexnow)
        'www.bing.com',
        'data.zhanzhang.sm.cn',
        'www.google.com',
        'ping.baidu.com',
        'yandex.com',
        'api.indexnow.org',
        'www.indexnow.org',
        // 百度热词/搜索建议
        'sp0.baidu.com',
        'top.baidu.com',
        // AI provider hosts (added in v0.1.5)
        'api.openai.com',
        'api.anthropic.com',
        'open.bigmodel.cn',
        'api.moonshot.cn',
        'api.deepseek.com',
        'dashscope.aliyuncs.com',
        'ark.cn-beijing.volces.com',
        'hunyuan.tencentcloudapi.com',
        // v1.4.0: 新增 Provider 域名
        'api.siliconflow.cn',
        'api.z.ai',
        'lke.cloud.tencent.com',
        'api.lkeap.cloud.tencent.com',
        'license.linked3.com',
        'linked3.com',
        // 图片 API
        'api.pexels.com',
        'pixabay.com',
        'api.unsplash.com',
        // 社交分发
        'api.twitter.com',
        'api.telegram.org',
        'api.weibo.com',
        'weibo.com',
        'api.juejin.cn',
        'bizapi.csdn.net',
        // v3.2.0: 热词采集源
        'api.bilibili.com',
        'www.toutiao.com',
        // v5.1.3: 7 源热词合一新增
        'www.zhihu.com',
        'trends.google.com',
        'v2.sohu.com',
        // v3.0.0: 新增 B2B 平台
        'api.alibaba.com',
        'gw.open.1688.com',
        // 海外自媒体
        'www.googleapis.com',
        'api.medium.com',
        'oauth.reddit.com',
        // 语音
        'api.tts.com',
    ];

    /** @var array<string> Per-host failure counters for circuit breaker. */
    private static $failure_counts = [];

    /** @var string 当前请求的主机名 (避免闭包 use) */
    private static $ctx_host_lower = '';

    /**
     * @param string $url
     * @param array  $args  wp_remote_get args + optional 'allowed_hosts' override.
     * @return array|\WP_Error
     */
    public static function get(string $url, array $args = []): array|WP_Error
    {
        return self::request('GET', $url, $args);
    }

    /**
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    public static function post(string $url, array $args = []): array|WP_Error
    {
        return self::request('POST', $url, $args);
    }

    /**
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    public static function put(string $url, array $args = []): array|WP_Error
    {
        return self::request('PUT', $url, $args);
    }

    /**
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    public static function delete(string $url, array $args = []): array|WP_Error
    {
        return self::request('DELETE', $url, $args);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    private static function request(string $method, string $url, array $args = []): array|WP_Error
    {
        $url = esc_url_raw($url);
        if (empty($url)) {
            return new \WP_Error('linked3_bad_url', __('URL 为空。', 'linked3'));
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            return new \WP_Error('linked3_bad_url', __('URL 格式错误:缺少主机。', 'linked3'));
        }

        // 1) Host whitelist + default-whitelist check.
        $host_check = self::check_host_whitelist($host, $args);
        if (is_wp_error($host_check)) {
            return $host_check;
        }
        $in_default_whitelist = $host_check['in_default_whitelist'];

        // 2) SSRF guard (skip for trusted default hosts + user-supplied hosts).
        if (!$in_default_whitelist && empty($args['skip_ssrf'])) {
            $err = self::guard_ssrf($host);
            if (is_wp_error($err)) {
                return $err;
            }
        }

        // 3) Circuit breaker — if this host failed >10 times in last 5 min, refuse.
        $host_lower = strtolower($host);
        $cb_err = self::check_circuit_breaker($host_lower);
        if (is_wp_error($cb_err)) {
            return $cb_err;
        }

        // 4) Force SSL verification ON + cap redirects.
        $args = array_merge([
            'method'             => $method,
            'timeout'            => 30,
            'redirection'        => 3,
            'sslverify'          => true,
            'reject_unsafe_urls' => true,
        ], $args);

        // 5) Hook a redirect callback so we can reject cross-host redirects.
        self::$ctx_host_lower = $host_lower;
        add_action('requests-requests.before_redirect', [self::class, 'redirect_guard']);

        try {
            $response = wp_remote_request($url, $args);
        } finally {
            remove_action('requests-requests.before_redirect', [self::class, 'redirect_guard']);
        }

        // 6) Record success / failure for circuit breaker.
        self::record_circuit_breaker_result($host_lower, $response);

        return $response;
    }

    /**
     * Check the host against the whitelist (default + filtered + per-call).
     *
     * @param string $host
     * @param array  $args
     * @return array|\WP_Error  ['in_default_whitelist' => bool] or WP_Error if blocked.
     */
    private static function check_host_whitelist(string $host, array $args): array|WP_Error
    {
        $allowed = array_merge(
            self::get_allowed_hosts(),
            (array) apply_filters('linked3/safe_remote_allowed_hosts', []),
            isset($args['allowed_hosts']) ? (array) $args['allowed_hosts'] : []
        );
        $allowed_lower = array_map('strtolower', $allowed);
        $host_lower    = strtolower($host);
        $match           = false;
        $in_default     = false;
        $default_lower  = array_map('strtolower', self::get_allowed_hosts());

        foreach ($allowed_lower as $h) {
            if ($host_lower === $h || (substr($host_lower, -strlen('.' . $h)) === '.' . $h)) {
                $match = true;
                foreach ($default_lower as $dl) {
                    if ($host_lower === $dl || (substr($host_lower, -strlen('.' . $dl)) === '.' . $dl)) {
                        $in_default = true;
                        break;
                    }
                }
                break;
            }
        }

        if (!$match) {
            return new \WP_Error(
                'linked3_host_blocked',
                sprintf(/* translators: %s: host name. */ __('主机 %s 不在白名单。', 'linked3'), $host)
            );
        }

        return ['in_default_whitelist' => $in_default];
    }

    /**
     * Check circuit breaker — refuse if host has failed too many times recently.
     *
     * @param string $host_lower
     * @return \WP_Error|null
     */
    private static function check_circuit_breaker(string $host_lower): ?\WP_Error
    {
        $key   = 'linked3_cb_' . md5($host_lower);
        $fails = (int) get_transient($key);
        if ($fails >= 10) {
            return new \WP_Error(
                'linked3_circuit_open',
                sprintf(__('%s 的熔断器已开启,请稍后重试。', 'linked3'), $host_lower)
            );
        }
        return null;
    }

    /**
     * Record request success/failure for circuit breaker tracking.
     *
     * @param string         $host_lower
     * @param array|\WP_Error $response
     * @return void
     */
    private static function record_circuit_breaker_result(string $host_lower, $response): void
    {
        $key   = 'linked3_cb_' . md5($host_lower);
        $fails = (int) get_transient($key);

        if (is_wp_error($response)) {
            set_transient($key, $fails + 1, 5 * MINUTE_IN_SECONDS);
        } else {
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 500) {
                set_transient($key, $fails + 1, 5 * MINUTE_IN_SECONDS);
            } elseif ($fails > 0) {
                delete_transient($key);
            }
        }
    }

    /**
     * Resolve the host and reject if it points at private / loopback /
     * link-local / reserved IP space. Defends against DNS rebinding SSRF.
     *
     * @param string $host
     * @return true|\WP_Error
     */
    private static function guard_ssrf(string $host): bool|WP_Error
    {
        // If host is already an IP literal, validate directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (self::is_private_ip($host)) {
                return new \WP_Error(
                    'linked3_ssrf_blocked',
                    __('禁止直接 IP 访问私有/保留地址段。', 'linked3')
                );
            }
            return true;
        }

        $ips = @gethostbynamel($host); // phpcs:ignore — intentionally suppressed.
        if (empty($ips)) {
            // DNS resolution failed — let wp_remote_request handle it; we don't
            // block here because some valid hosts have transient DNS issues.
            return true;
        }
        foreach ($ips as $ip) {
            if (self::is_private_ip($ip)) {
                return new \WP_Error(
                    'linked3_ssrf_blocked',
                    sprintf(__('主机 %s 解析到私有/保留 IP %s。', 'linked3'), $host, $ip)
                );
            }
        }
        return true;
    }

    /**
     * @param string $ip
     * @return bool
     */
    public static function is_private_ip(string $ip)
    : bool {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true; // be conservative
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        // Extra: block link-local 169.254.x.x (cloud metadata) explicitly —
        // FILTER_FLAG_NO_RES_RANGE should already cover it but be explicit.
        if (strpos($ip, '169.254.') === 0) {
            return true;
        }
        return false;
    }

    /**
     * v4.3.5: 获取允许的主机列表 (支持 filter + option 扩展)
     */
    public static function get_allowed_hosts()
    {
        $extra = (array) get_option(LINKED3_OPTION_PREFIX . 'safe_remote_hosts', []);
        return apply_filters('linked3/safe_remote_allowed_hosts', array_merge(self::$default_hosts, $extra));
    }

    /**
     * Redirect guard callback: rejects cross-host redirects (替代闭包 use)。
     *
     * @param string $location redirect URL (passed by reference; cleared to abort)
     * @return void
     */
    public static function redirect_guard(&$location)
    : void {
        $new_host = strtolower((string) wp_parse_url($location, PHP_URL_HOST));
        if ($new_host && $new_host !== self::$ctx_host_lower) {
            $location = ''; // abort redirect
        }
    }
}
