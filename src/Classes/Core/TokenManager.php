<?php

declare(strict_types=1);
/**
 * Token Manager — quota facade.
 *
 * Three-track quota:
 *   - Logged-in users: per-user meta, reset daily by cron
 *   - Anonymous guests: linked3_guest_token_usage table, reset daily
 *   - Per-module context: independent counts (chat / content_writer / agent)
 *
 * Mirrors aipower's Token Manager but leaner — no Composer deps.
 *
 * @package Linked3
 * @subpackage Classes\Core
 */

namespace Linked3\Classes\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class TokenManager
{
    const META_USED_TODAY = 'linked3_tokens_used_today';
    const META_RESET_AT   = 'linked3_tokens_reset_at';

    /** @var self|null */
    private static $instance;

    /** @var array Default daily quotas per plan. */
    private $plan_quotas;

    private function __construct() {
        $this->plan_quotas = (array) apply_filters('linked3/plan_quotas', [
            'free'     => 50000,
            'pro'      => 3000000,
            'premium'  => 50000000,
        ]);
    }

    /**
     * @return self
     */
    public static function instance() : mixed {
        if (null === self::$instance) {
            // v4.4.2: delegate to the DI container when available (enables
            // test overrides via set_instance()).
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
     * v4.4.2: used by the container's factory to avoid infinite recursion.
     *
     * @return self
     * @internal
     */
    public static function instance_without_container() : mixed     {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param int $user_id
     * @return string 'free' | 'pro' | 'premium'
     */
    public function user_plan($user_id) : mixed {
        // v0.2.5 will wire real License Service; for v0.1.x everyone is Free.
        $plan = (string) get_user_meta($user_id, 'linked3_plan', true);
        return $plan !== '' ? $plan : 'free';
    }

    /**
     * @param string $plan
     * @return int
     */
    public function quota_for_plan($plan) : mixed     {
        return (int) ($this->plan_quotas[$plan] ?? $this->plan_quotas['free']);
    }

    /**
     * @param int    $user_id  0 for anonymous (uses session_id).
     * @param string $session_id
     * @return int Tokens used today.
     */
    public function used_today($user_id, $session_id = '')
    {
        if ($user_id > 0) {
            $this->maybe_reset_user($user_id);
            return (int) get_user_meta($user_id, self::META_USED_TODAY, true);
        }
        // Guest — SUM tokens_used across all bot_ids for this session
        // (a guest may chat with multiple bots; each bot has its own row
        // because the UNIQUE key is (session_id, bot_id)). The previous
        // `LIMIT 1` query only counted one bot's tokens.
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_guest_token_usage';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(tokens_used), 0) AS total, MAX(reset_at) AS reset_at FROM {$table} WHERE session_id = %s",
            $session_id
        ), ARRAY_A);
        if (!$row || $row['total'] === null) {
            return 0;
        }
        // Reset if 24h passed since the most-recent reset_at.
        if (!empty($row['reset_at']) && strtotime($row['reset_at']) < time() - DAY_IN_SECONDS) {
            return 0;
        }
        return (int) $row['total'];
    }

    /**
     * Check if user/guest has enough quota remaining.
     *
     * @param int    $user_id
     * @param string $session_id
     * @param int    $tokens_needed
     * @return array{ok:bool, used:int, quota:int, remaining:int}
     */
    public function check($user_id, $session_id, $tokens_needed = 1)
    : array {
        $plan = $this->user_plan($user_id);
        $quota = $this->quota_for_plan($plan);
        $used = $this->used_today($user_id, $session_id);
        $remaining = max(0, $quota - $used);
        return [
            'ok'        => $remaining >= $tokens_needed,
            'used'      => $used,
            'quota'     => $quota,
            'remaining' => $remaining,
        ];
    }

    /**
     * Record token usage after a successful AI call.
     *
     * NOTE: the `requests` counter for guests is incremented atomically by
     * Chat_Manager::check_quota() BEFORE the AI dispatch (so the count is
     * enforced even on AI failure). This method only updates `tokens_used`
     * for guests — it deliberately does NOT bump `requests` to avoid
     * double-counting.
     *
     * @param int    $user_id
     * @param string $session_id
     * @param int    $tokens
     * @param int    $bot_id  Optional — required for accurate per-bot guest
     *                       token accounting (defaults to 0 for backward compat).
     * @return void
     */
    public function record($user_id, $session_id, $tokens, $bot_id = 0)
    : void {
        $tokens = max(0, (int) $tokens);
        if ($tokens === 0) {
            return;
        }
        if ($user_id > 0) {
            $this->maybe_reset_user($user_id);
            $cur = (int) get_user_meta($user_id, self::META_USED_TODAY, true);
            update_user_meta($user_id, self::META_USED_TODAY, $cur + $tokens);
        } else {
            $this->record_guest($session_id, $tokens, (int) $bot_id);
        }
    }

    /**
     * Update guest token usage. Only touches `tokens_used` (NOT `requests`,
     * which is owned by Chat_Manager::check_quota). Writes to the
     * (session_id, bot_id) row so the per-bot counter matches.
     *
     * @param string $session_id
     * @param int    $tokens
     * @param int    $bot_id
     * @return void
     */
    private function record_guest($session_id, $tokens, $bot_id = 0)
    : void {
        global $wpdb;
        $table = $wpdb->prefix . 'linked3_guest_token_usage';
        $now = current_time('mysql', true); // UTC stamp
        // Upsert via INSERT ... ON DUPLICATE KEY UPDATE. The row may already
        // exist (created by Chat_Manager::check_quota's atomic increment).
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (session_id, bot_id, tokens_used, requests, reset_at)
             VALUES (%s, %d, %d, 0, %s)
             ON DUPLICATE KEY UPDATE
               tokens_used = tokens_used + %d",
            $session_id,
            $bot_id,
            $tokens,
            $now,
            $tokens
        );
        $wpdb->query($sql); // $wpdb->prepare applied above
    }

    /**
     * Reset a user's daily counter if 24h have passed.
     *
     * @param int $user_id
     * @return void
     */
    private function maybe_reset_user($user_id)
    : void {
        $reset_at = (int) get_user_meta($user_id, self::META_RESET_AT, true);
        if ($reset_at === 0 || $reset_at < time() - DAY_IN_SECONDS) {
            update_user_meta($user_id, self::META_USED_TODAY, 0);
            update_user_meta($user_id, self::META_RESET_AT, time());
        }
    }

    /**
     * Daily cron — reset ALL users + prune expired guest rows.
     *
     * @return void
     */
    public static function daily_reset()
    : void {
        global $wpdb;

        // 1) Bulk-reset all users via direct SQL (faster than iterating meta).
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->usermeta} SET meta_value = '0' WHERE meta_key = %s", self::META_USED_TODAY)); // phpcs:ignore
        $now = time();
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->usermeta} SET meta_value = %d WHERE meta_key = %s",
            $now,
            self::META_RESET_AT
        ));

        // 2) Delete guest rows older than 48h.
        // ── FIX v16.0.1: Use PHP-computed timestamp for SQLite compatibility ──
        $table = $wpdb->prefix . 'linked3_guest_token_usage';
        $cutoff = date('Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS);
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE reset_at < %s", $cutoff)); // phpcs:ignore
    }
}
