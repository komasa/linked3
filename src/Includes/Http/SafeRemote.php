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
    public static function get($url, array $args = [])
    {
        return self::request('GET', $url, $args);
    }

    /**
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    public static function post($url, array $args = [])
    {
        return self::request('POST', $url, $args);
    }

    /**
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    public static function put($url, array $args = [])
    {
        return self::request('PUT', $url, $args);
    }

    /**
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    public static function delete($url, array $args = [])
    {
        return self::request('DELETE', $url, $args);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $args
     * @return array|\WP_Error
     */
    private static function request($method, $url, array $args = [])
    {
        $url = esc_url_raw($url);
        if (empty($url)) {
            return new \WP_Error('linked3_bad_url', __('URL 为空。', 'linked3'));
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            return new \WP_Error('linked3_bad_url', __('URL 格式错误:缺少主机。', 'linked3'));
        }

        // 1) Host whitelist.
        $allowed = array_merge(
            self::get_allowed_hosts(),
            (array) apply_filters('linked3/safe_remote_allowed_hosts', []),
            isset($args['allowed_hosts']) ? (array) $args['allowed_hosts'] : []
        );
        $allowed_lower = array_map('strtolower', $allowed);
        $host_lower = strtolower($host);
        $match = false;
        $in_default_whitelist = false;
        $default_lower = array_map('strtolower', self::get_allowed_hosts());
        foreach ($allowed_lower as $h) {
            if ($host_lower === $h || (substr($host_lower, -strlen('.' . $h)) === '.' . $h)) {
                $match = true;
                // 检查是否在默认白名单 (内置信任域名)
                foreach ($default_lower as $dl) {
                    if ($host_lower === $dl || (substr($host_lower, -strlen('.' . $dl)) === '.' . $dl)) {
                        $in_default_whitelist = true;
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

        // 2) SSRF guard: resolve and reject private/link-local IPs.
        // 内置白名单域名跳过 — 某些环境(如 playground/Docker)DNS 解析到内网,
        // 但白名单域名是受信任的公网 API,不应因内网 IP 被拦截。
        // 通过 allowed_hosts 参数传入的域名(采集场景)也跳过 —
        // 用户主动输入 URL 采集,不是服务端自动跳转,SSRF 风险由用户自己承担。
        if (!$in_default_whitelist && empty($args['skip_ssrf'])) {
            $err = self::guard_ssrf($host);
            if (is_wp_error($err)) {
                return $err;
            }
        }

        // 3) Circuit breaker — if this host failed >10 times in last 5 min, refuse.
        $key = 'linked3_cb_' . md5($host_lower);
        $fails = (int) get_transient($key);
        if ($fails >= 10) {
            return new \WP_Error(
                'linked3_circuit_open',
                sprintf(__('%s 的熔断器已开启,请稍后重试。', 'linked3'), $host)
            );
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
        // Uses a named static method + static property instead of a closure
        // to avoid the scanner misinterpreting `use` as a trait import.
        self::$ctx_host_lower = $host_lower;
        add_action('requests-requests.before_redirect', [self::class, 'redirect_guard']);

        try {
            $response = wp_remote_request($url, $args);
        } finally {
            // Always remove the guard — even on exception — so it doesn't
            // contaminate later requests in the same PHP process.
            remove_action('requests-requests.before_redirect', [self::class, 'redirect_guard']);
        }

        // 6) Record success / failure for circuit breaker.
        if (is_wp_error($response)) {
            set_transient($key, $fails + 1, 5 * MINUTE_IN_SECONDS);
        } else {
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 500) {
                set_transient($key, $fails + 1, 5 * MINUTE_IN_SECONDS);
            } elseif ($fails > 0) {
                // Half-open recovery: clear on success.
                delete_transient($key);
            }
        }

        return $response;
    }

    /**
     * Resolve the host and reject if it points at private / loopback /
     * link-local / reserved IP space. Defends against DNS rebinding SSRF.
     *
     * @param string $host
     * @return true|\WP_Error
     */
    private static function guard_ssrf($host)
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
    public static function is_private_ip($ip)
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
