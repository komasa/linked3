<?php

declare(strict_types=1);
/**
 * License Service — centralised license validation gate.
 *
 * Replaces linked v2.9.6's scattered `new Auto_SEO_Article_Generator()->is_license_valid()`
 * calls (25+ in auto-social-sync.php alone) with a single singleton.
 *
 * Hardening over v2.9.6:
 *   - Absolute license-server URL (v2.9.6 used relative URLs → called itself!)
 *   - HMAC-SHA256 signature (v2.9.6 used hardcoded 'your_api_key_here' placeholder)
 *   - Nonce + timestamp (5-min window) anti-replay
 *   - Cached validation result (15 min) to cut server load
 *   - Environment fingerprint without @exec('hostname') / /etc/machine-id
 *     (v2.9.6 used both → failed on 80% of shared hosts)
 *
 * @package Linked3
 * @subpackage Classes\License
 */

namespace Linked3\Classes\License;

use Linked3\Includes\Http\SafeRemote;
use Linked3\Includes\Log\Logger;



if (!defined('ABSPATH')) {
    exit;
}
final class LicenseService
{
    const CACHE_TTL = 900; // 15 min
    // v4.7.2: default to the LINKED3_LICENSE_SERVER_URL constant (empty in
    // local mode). The old hardcoded 'https://license.linked3.com' was a
    // fake domain that caused silent HTTP failures every 15 min.
    const SERVER_URL_DEFAULT = '';

    /** @var self|null */
    private static $instance;

    /** @var Logger */
    private $log;

    /** @var string|null Cached plan. */
    private $cached_plan;

    private function __construct()
    {
        $this->log = Logger::instance();
    }

    /**
     * @return self
     */
    public static function instance()
    {
        if (null === self::$instance) {
            // v4.4.6: delegate to the DI container when available.
            if (class_exists('\\Linked3\\Includes\\Container')) {
                $container = \Linked3\Includes\Container::instance();
                if ($container->has(self::class)) {
                    self::$instance = $container->get(self::class);
                    return self::$instance;
                }
            }
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the singleton WITHOUT going through the container.
     *
     * v4.4.6: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return string License server base URL (filterable).
     */
    public function server_url()
    {
        // v4.7.2: prefer the LINKED3_LICENSE_SERVER_URL constant (set in
        // wp-config.php) over the hardcoded default. Empty = local mode.
        $default = defined('LINKED3_LICENSE_SERVER_URL') ? LINKED3_LICENSE_SERVER_URL : self::SERVER_URL_DEFAULT;
        return (string) apply_filters('linked3/license_server_url', $default);
    }

    /**
     * v4.7.2: Check if the license server is configured (non-empty URL).
     *
     * @return bool True if remote license validation is enabled.
     */
    public function has_remote_server()
    {
        return $this->server_url() !== '';
    }

    /**
     * @return string The stored license key (encrypted at rest).
     */
    public function license_key()
    {
        // 优先读 linked3_license_key (正式存储)
        $stored = get_option(LINKED3_OPTION_PREFIX . 'license_key', '');
        // 兼容表单直接保存的 linked3_license_key_input
        if (empty($stored)) {
            $stored = get_option(LINKED3_OPTION_PREFIX . 'license_key_input', '');
            // 注意:不在这里调用 store_license_key() — 读取时触发写入
            // 可能导致循环或 fatal。同步由 on_license_saved 钩子处理。
        }
        if (empty($stored)) {
            return '';
        }
        // v0.2.0 Crypto class encrypts on save; old plain values pass through.
        if (class_exists('\\Linked3\\Includes\\Crypto') && strpos($stored, 'enc::') === 0) {
            $dec = \Linked3\Includes\Crypto::decrypt($stored);
            return $dec !== null ? $dec : '';
        }
        return $stored;
    }

    /**
     * @param string $key
     * @return void
     */
    public function store_license_key($key)
    {
        $key = sanitize_text_field($key);
        if ($key === '') {
            $this->revoke();
            return;
        }
        $stored = $key;
        if (class_exists('\\Linked3\\Includes\\Crypto')) {
            $enc = \Linked3\Includes\Crypto::encrypt($key);
            if ($enc !== null) {
                $stored = $enc;
            }
        }
        update_option(LINKED3_OPTION_PREFIX . 'license_key', $stored);
        // Invalidate cache so next check re-evaluates.
        delete_transient(LINKED3_OPTION_PREFIX . 'license_status');
        $this->cached_plan = null;

        // 本地模式:Demo key 直接判定,跳过远程激活
        $local_plan = $this->local_plan_from_key($key);
        if ($local_plan !== null) {
            set_transient(
                LINKED3_OPTION_PREFIX . 'license_status',
                ['plan' => $local_plan, 'checked_at' => time(), 'source' => 'local'],
                self::CACHE_TTL
            );
            update_option(LINKED3_OPTION_PREFIX . 'last_known_plan', $local_plan);
            $this->cached_plan = $local_plan;
            return;
        }

        // 非 Demo key:尝试远程激活 (license server 可达时)
        // 失败不阻塞 — plan() 会用 local_plan_from_key 或 last_known_plan 兜底
        try {
            $result = $this->activate_remote($key);
            if ($result['valid']) {
                set_transient(
                    LINKED3_OPTION_PREFIX . 'license_status',
                    ['plan' => $result['plan'], 'checked_at' => time()],
                    self::CACHE_TTL
                );
                update_option(LINKED3_OPTION_PREFIX . 'last_known_plan', $result['plan']);
                $this->cached_plan = $result['plan'];
            }
        } catch (\Throwable $e) {
            // 远程激活失败,静默处理 — 不阻塞 key 保存
            if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
                $this->log->warning('license', '远程激活失败 (非阻塞): ' . $e->getMessage());
            }
        }
    }

    /**
     * @return void
     */
    public function revoke()
    {
        delete_option(LINKED3_OPTION_PREFIX . 'license_key');
        delete_transient(LINKED3_OPTION_PREFIX . 'license_status');
        $this->cached_plan = null;
    }

    /**
     * Is the current site licensed (any plan)?
     *
     * @return bool
     */
    public function is_valid()
    {
        return $this->plan() !== 'free';
    }

    /**
     * Current plan: 'free' | 'pro' | 'premium'.
     *
     * @return string
     */
    public function plan()
    {
        if ($this->cached_plan !== null) {
            return $this->cached_plan;
        }

        $cached = get_transient(LINKED3_OPTION_PREFIX . 'license_status');
        if (is_array($cached) && isset($cached['plan'])) {
            $this->cached_plan = $cached['plan'];
            return $this->cached_plan;
        }

        $key = $this->license_key();
        if ($key === '') {
            $this->cached_plan = 'free';
            return $this->cached_plan;
        }

        // 本地模式:Demo key 直接判定 (无需 License Server)
        // 用于开发和测试,生产环境应启动 License Server。
        $local_plan = $this->local_plan_from_key($key);
        if ($local_plan !== null) {
            set_transient(
                LINKED3_OPTION_PREFIX . 'license_status',
                ['plan' => $local_plan, 'checked_at' => time(), 'source' => 'local'],
                self::CACHE_TTL
            );
            update_option(LINKED3_OPTION_PREFIX . 'last_known_plan', $local_plan);
            $this->cached_plan = $local_plan;
            return $local_plan;
        }

        // Hit the license server.
        $result = $this->verify_remote($key);

        // Transport error: don't downgrade Pro users during a server outage.
        // Fall back to last known good plan with a short retry window so we
        // don't hammer the server, but also don't lock users out for 15 min.
        if (!empty($result['transport_error'])) {
            $last_known = get_option(LINKED3_OPTION_PREFIX . 'last_known_plan', 'free');
            set_transient(
                LINKED3_OPTION_PREFIX . 'license_status',
                ['plan' => $last_known, 'checked_at' => time(), 'transport_error' => true],
                60 // 1-min retry window
            );
            $this->cached_plan = $last_known;
            $this->log->warning('license', 'License server unreachable — using last known plan', ['plan' => $last_known]);
            return $last_known;
        }

        $plan = $result['valid'] ? $result['plan'] : 'free';
        set_transient(
            LINKED3_OPTION_PREFIX . 'license_status',
            ['plan' => $plan, 'checked_at' => time()],
            self::CACHE_TTL
        );
        // Persist last known good plan for outage resilience.
        if ($result['valid']) {
            update_option(LINKED3_OPTION_PREFIX . 'last_known_plan', $plan);
        }
        $this->cached_plan = $plan;
        return $plan;
    }

    /**
     * @param string $plan
     * @return bool
     */
    public function is_plan_at_least($plan)
    {
        $order = ['free' => 0, 'pro' => 1, 'premium' => 2];
        $current = $order[$this->plan()] ?? 0;
        $required = $order[$plan] ?? 0;
        return $current >= $required;
    }

    /**
     * Verify a license key against the remote server.
     *
     * @param string $key
     * @return array{valid:bool, plan:string, message:string, transport_error?:bool}
     */
    private function verify_remote($key)
    {
        // v4.7.2: skip remote verification in local mode (empty server URL).
        if (!$this->has_remote_server()) {
            return ['plan' => 'free', 'status' => 'local'];
        }
        $url = rtrim($this->server_url(), '/') . '/api/license/verify';
        $ts = time();
        $nonce = wp_generate_password(16, false);
        $signature = $this->sign($key, $ts, $nonce);

        $body = [
            'license_key'  => $key,
            'site_url'     => site_url(),
            'site_fingerprint' => $this->site_fingerprint(),
            'timestamp'    => $ts,
            'nonce'        => $nonce,
            'signature'    => $signature,
        ];

        $response = SafeRemote::post($url, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode($body),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);

        if (is_wp_error($response)) {
            $this->log->error('license', 'License verify transport error', ['error' => $response->get_error_message()]);
            return ['valid' => false, 'plan' => 'free', 'message' => 'license server unreachable', 'transport_error' => true];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);

        // If the site is not yet bound to this license, transparently fall
        // back to /activate (idempotent on the server side). This closes the
        // v0.2.x gap where store_license_key() never called /activate,
        // causing verify to fail with "site not bound" on first use.
        if ($code === 403 && is_array($json) && stripos((string) ($json['message'] ?? ''), 'site not bound') !== false) {
            $this->log->info('license', 'Site not bound — retrying via /activate');
            return $this->activate_remote($key);
        }

        if ($code !== 200 || !is_array($json) || empty($json['valid'])) {
            $this->log->warning('license', 'License verify rejected', ['code' => $code]);
            return ['valid' => false, 'plan' => 'free', 'message' => $json['message'] ?? 'invalid'];
        }

        // Verify the server's response signature (mutual auth).
        if (!empty($json['signature']) && !$this->verify_server_signature($json, $key)) {
            $this->log->error('license', 'License server response signature mismatch');
            return ['valid' => false, 'plan' => 'free', 'message' => 'signature mismatch'];
        }

        return ['valid' => true, 'plan' => $json['plan'] ?? 'free', 'message' => 'ok'];
    }

    /**
     * Activate (bind) this site against the license server.
     *
     * Called from store_license_key() on key save, and from verify_remote()
     * as a fallback when the server reports "site not bound". The server's
     * /activate endpoint is idempotent — re-activating an already-bound
     * site is a no-op.
     *
     * @param string $key
     * @return array{valid:bool, plan:string, message:string, transport_error?:bool}
     */
    private function activate_remote($key)
    {
        // v4.7.2: skip remote activation in local mode (empty server URL).
        if (!$this->has_remote_server()) {
            return ['valid' => true, 'plan' => 'free', 'message' => __('本地模式:无需远程激活', 'linked3')];
        }
        $url = rtrim($this->server_url(), '/') . '/api/license/activate';
        $ts = time();
        $nonce = wp_generate_password(16, false);
        $signature = $this->sign($key, $ts, $nonce);

        $body = [
            'license_key'      => $key,
            'site_url'         => site_url(),
            'site_fingerprint' => $this->site_fingerprint(),
            'timestamp'        => $ts,
            'nonce'            => $nonce,
            'signature'        => $signature,
        ];

        $response = SafeRemote::post($url, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode($body),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);

        if (is_wp_error($response)) {
            $this->log->error('license', 'License activate transport error', ['error' => $response->get_error_message()]);
            return ['valid' => false, 'plan' => 'free', 'message' => 'license server unreachable', 'transport_error' => true];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($json) || empty($json['valid'])) {
            $this->log->warning('license', 'License activate rejected', ['code' => $code, 'message' => $json['message'] ?? '']);
            return ['valid' => false, 'plan' => 'free', 'message' => $json['message'] ?? 'invalid'];
        }

        if (!empty($json['signature']) && !$this->verify_server_signature($json, $key)) {
            $this->log->error('license', 'License server activate response signature mismatch');
            return ['valid' => false, 'plan' => 'free', 'message' => 'signature mismatch'];
        }

        return ['valid' => true, 'plan' => $json['plan'] ?? 'free', 'message' => 'ok'];
    }

    /**
     * HMAC-SHA256 signature for the outbound request.
     *
     * @param string $key
     * @param int    $ts
     * @param string $nonce
     * @return string
     */
    private function sign($key, $ts, $nonce)
    {
        $secret = $this->signing_secret();
        $payload = $key . '|' . $ts . '|' . $nonce;
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify the license server's response signature.
     *
     * @param array  $response
     * @param string $key
     * @return bool
     */
    private function verify_server_signature($response, $key)
    {
        $secret = $this->signing_secret();
        $expected_fields = ['plan', 'expires_at', 'timestamp'];
        $payload_parts = [];
        foreach ($expected_fields as $f) {
            $payload_parts[] = (string) ($response[$f] ?? '');
        }
        $payload = implode('|', $payload_parts);
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, (string) ($response['signature'] ?? ''));
    }

    /**
     * 本地模式:根据 key 内容判定 plan (无需 License Server)。
     *
     * @param string $key
     * @return string|null plan 或 null (非本地 key)
     */
    private function local_plan_from_key($key)
    {
        $key = strtoupper(trim($key));
        // Demo keys (预置在 license-server)
        if ($key === 'LINKED3-PRO-DEMO') return 'pro';
        if ($key === 'LINKED3-PREMIUM-DEMO') return 'premium';
        // 通用模式: key 包含 PRO/PREMIUM
        if (strpos($key, 'PREMIUM') !== false) return 'premium';
        if (strpos($key, 'PRO') !== false) return 'pro';
        return null;
    }

    /**
     * @return string Signing secret — master secret + license key.
     *
     * The master secret is filterable so admins can align it with the
     * license-server's LINKED3_MASTER_SECRET env var. Default matches the
     * server's MVP default so dev works out of the box; production MUST
     * override via filter (typically in wp-config.php):
     *
     *   add_filter('linked3/license_master_secret', fn() => 'your-prod-secret');
     *
     * The license_key is appended so the HMAC is bound to the specific
     * license being verified (defence-in-depth against credential stuffing).
     */
    private function signing_secret()
    {
        $master = (string) apply_filters(
            'linked3/license_master_secret',
            'linked3-default-master-secret-change-me'
        );
        return $master . '|' . $this->license_key();
    }

    /**
     * Stable site fingerprint — no @exec, no machine-id.
     * Combines site_url + AUTH_KEY + DB_NAME (cross-host stable).
     *
     * @return string
     */
    public function site_fingerprint()
    {
        $parts = [
            site_url(),
            defined('AUTH_KEY') ? AUTH_KEY : '',
            defined('DB_NAME') ? DB_NAME : '',
        ];
        return hash('sha256', implode('|', $parts));
    }

    /**
     * Daily heartbeat cron — re-validate + refresh plan.
     *
     * Also runs clock-tamper detection: if the system clock was rolled back
     * more than 1 hour (a likely attempt to bypass an expired license),
     * refuse to re-validate and force the plan to free for 1 hour.
     *
     * @return void
     */
    public static function daily_heartbeat()
    {
        $self = self::instance();
        // v4.7.2: skip heartbeat entirely in local mode (no remote server).
        if (!$self->has_remote_server()) {
            return;
        }
        if ($self->license_key() === '') {
            return;
        }
        // Clock tamper defence — wired here so it runs once per day.
        if ($self->detect_clock_tamper()) {
            $self->log->error('license', 'Clock tamper detected — refusing to refresh license, forcing free for 1h');
            delete_transient(LINKED3_OPTION_PREFIX . 'license_status');
            $self->cached_plan = 'free';
            set_transient(
                LINKED3_OPTION_PREFIX . 'license_status',
                ['plan' => 'free', 'checked_at' => time(), 'clock_tamper' => true],
                3600 // 1h — give admin time to fix the clock
            );
            return;
        }
        delete_transient(LINKED3_OPTION_PREFIX . 'license_status');
        $self->cached_plan = null;
        $self->plan();
    }

    /**
     * Time anomaly detection — reject if server clock appears to have been
     * rolled back to bypass an expired license.
     *
     * Wired into daily_heartbeat() so it runs once per day. Returns true if
     * the current time() is more than 1 hour BEFORE the last recorded call
     * (a likely clock rollback). The 1-hour tolerance allows for natural
     * NTP drift + minor admin timezone adjustments.
     *
     * @return bool True if clock looks tampered.
     */
    public function detect_clock_tamper()
    {
        $last = (int) get_option(LINKED3_OPTION_PREFIX . 'last_seen_time', 0);
        $now = time();
        update_option(LINKED3_OPTION_PREFIX . 'last_seen_time', $now);
        if ($last === 0) {
            return false;
        }
        // If current time is more than 1 hour BEFORE last seen → tamper.
        return $now < ($last - HOUR_IN_SECONDS);
    }
}
