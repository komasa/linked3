<?php
/**
 * Subscription manager — daily check + expiry handling.
 *
 * Daily cron (linked3_subscription_check):
 *   1) Pull subscription status from billing server
 *   2) If renewal succeeded → maintain plan
 *   3) If renewal failed → 7-day grace → downgrade to Free
 *   4) Notify user (3 days before / on day / 7-day grace start)
 *
 * @package Linked3
 * @subpackage Classes\Billing
 */

namespace Linked3\Classes\Billing;

use Linked3\Classes\License\Linked3_License_Service;
use Linked3\Includes\Http\Linked3_Safe_Remote;
use Linked3\Includes\Log\Linked3_Logger;



if (!defined('ABSPATH')) {
    exit;
}
final class Linked3_Subscription_Manager
{
    const GRACE_DAYS = 7;

    /**
     * @return void
     */
    public static function daily_check()
    : void {
        $self = new self();
        $self->check_all();
    }

    /**
     * @return void
     */
    private function check_all()
    : void {
        $license = Linked3_License_Service::instance();
        if (!$license->is_valid()) {
            return;
        }

        $status = $this->fetch_subscription_status();
        if (is_wp_error($status)) {
            // Billing server unreachable — do NOT downgrade (fail-safe).
            return;
        }

        $plan = $status['plan'] ?? 'free';
        $expires_at = (int) ($status['expires_at'] ?? 0);

        if ($plan === 'free' || $expires_at === 0) {
            return;
        }

        $now = time();
        $days_left = (int) ceil(($expires_at - $now) / DAY_IN_SECONDS);

        if ($days_left > 7) {
            // Healthy — clear any grace flags.
            delete_option(LINKED3_OPTION_PREFIX . 'grace_started_at');
            return;
        }

        if ($days_left <= 0) {
            // Expired — enter or continue grace.
            $grace_started = (int) get_option(LINKED3_OPTION_PREFIX . 'grace_started_at', 0);
            if ($grace_started === 0) {
                update_option(LINKED3_OPTION_PREFIX . 'grace_started_at', $now);
                $this->notify('grace_started', $plan);
            } elseif ($now - $grace_started > self::GRACE_DAYS * DAY_IN_SECONDS) {
                // Grace exhausted — downgrade.
                $this->downgrade_to_free();
                $this->notify('downgraded', $plan);
            }
        } else {
            // Within 7 days of expiry — notify.
            if ($days_left === 3 || $days_left === 1) {
                $this->notify('expiring', $plan, $days_left);
            }
        }
    }

    /**
     * @return array|\WP_Error
     */
    private function fetch_subscription_status() : mixed {
        // v4.7.2: use the LINKED3_BILLING_SERVER_URL constant (empty in local
        // mode). The old hardcoded 'https://billing.linked3.com' was a fake
        // domain that caused silent HTTP failures.
        $default = defined('LINKED3_BILLING_SERVER_URL') ? LINKED3_BILLING_SERVER_URL : '';
        $base = (string) apply_filters('linked3/billing_server_url', $default);

        // Skip entirely in local mode (no billing server configured).
        if ($base === '') {
            return new \WP_Error('billing_local_mode', __('本地模式:无计费服务器', 'linked3'));
        }

        $url = rtrim($base, '/') . '/api/billing/subscription/status';
        $key = Linked3_License_Service::instance()->license_key();
        $response = Linked3_Safe_Remote::post($url, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['license_key' => $key, 'site_url' => site_url()]),
            'allowed_hosts' => [wp_parse_url($url, PHP_URL_HOST)],
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($json) || empty($json['ok'])) {
            // 503 (license-server unreachable via billing proxy) or 502 →
            // fail-safe: treat as transport error, do NOT downgrade.
            return new \WP_Error('billing_status_unavailable', $json['message'] ?? 'billing status check failed');
        }
        return $json;
    }

    /**
     * @return void
     */
    private function downgrade_to_free()
    : void {
        // Revoke local license + clear plan cache.
        Linked3_License_Service::instance()->revoke();
        delete_option(LINKED3_OPTION_PREFIX . 'grace_started_at');
        Linked3_Logger::instance()->warning('billing', 'License downgraded to Free after grace period exhausted');
    }

    /**
     * @param string $event  'grace_started' | 'downgraded' | 'expiring'
     * @param string $plan
     * @param int    $days_left
     * @return void
     */
    private function notify($event, $plan, $days_left = 0)
    : void {
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }
        $subject = sprintf(__('[Linked3] Subscription %s notification', 'linked3'), $event);
        switch ($event) {
            case 'expiring':
                $message = sprintf(
                    /* translators: 1: plan, 2: days. */
                    __('您的 Linked3 %1$s 订阅将在 %2$d 天后到期,请及时续费避免服务中断。', 'linked3'),
                    ucfirst($plan),
                    $days_left
                );
                break;
            case 'grace_started':
                $message = sprintf(
                    __('您的 Linked3 %s 订阅已过期,有 7 天宽限期,之后降级为免费版。', 'linked3'),
                    ucfirst($plan)
                );
                break;
            case 'downgraded':
                $message = __('宽限期已结束,您的 Linked3 订阅已降级为免费版。', 'linked3');
                break;
            default:
                return;
        }
        wp_mail($admin_email, $subject, $message);

        // Also surface an admin notice.
        set_transient(LINKED3_OPTION_PREFIX . 'billing_notice', [
            'event' => $event,
            'plan' => $plan,
            'days_left' => $days_left,
            'message' => $message,
        ], DAY_IN_SECONDS);
    }
}
