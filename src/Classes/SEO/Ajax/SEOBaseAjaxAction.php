<?php

declare(strict_types=1);
/**
 * SEO module AJAX base action.
 *
 * Mirrors ContentWriterBaseAjaxAction: shared nonce/cap gate
 * + plan gate. Concrete subclasses declare their own NONCE_ACTION and
 * CAPABILITY constants + a handle() method.
 *
 * Plan gating specifics (per v0.4.x architecture requirements):
 *   - Push functionality: Free 100/day, Pro unlimited
 *     → use require_push_quota() in push-related actions.
 *   - SEO scorecard: Pro+
 *     → use require_plan('pro') in scorecard action.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Ajax
 */

namespace Linked3\Classes\SEO\Ajax;

use Linked3\Includes\Traits\Trait_Check_Admin_Permissions;
use Linked3\Includes\Traits\Trait_Send_WP_Error;
use Linked3\Includes\Traits\Trait_Check_Plan_Access;
use Linked3\Classes\License\LicenseService;
use Linked3\Classes\SEO\SEOConfig;




if (!defined('ABSPATH')) {
    exit;
}
use Linked3\Classes\License\PlanDefinitions; // phpcs:ignore -- reserved for future plan-based limits
abstract class SEOBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_seo';
    const CAPABILITY   = 'edit_posts';

    /**
     * Subclasses implement the actual business logic.
     *
     * @return void
     */
    abstract public function handle();

    /**
     * Verify everything, then dispatch. Subclasses MAY override to insert
     * additional plan/quota gates between verify() and handle().
     *
     * @return void
     */
    public function dispatch()
    : void {
        $this->verify(static::NONCE_ACTION, static::CAPABILITY);
        $this->handle();
    }

    /**
     * Plan-gated SEO feature (Pro+). Used by scorecard + schema config.
     *
     * @param string $plan 'pro' or 'premium'
     * @return true
     */
    protected function require_seo_plan($plan = 'pro') : mixed {
        return $this->require_plan($plan);
    }

    /**
     * Daily push quota gate. Free plan is capped at N pushes / engine / day
     * (default 100, configurable via linked3/seo_config → push.daily_cap.free).
     * Pro/Premium are unlimited (-1 in the config).
     *
     * Per-engine counter lives in a transient keyed by site + engine + day.
     *
     * @param string $engine Push engine slug (baidu|bing|google|toutiao|indexnow).
     * @return true True on allow; never returns on denial (sends 402).
     */
    protected function require_push_quota($engine)
    : bool {
        $service = LicenseService::instance();
        $plan    = $service->plan();
        $cap_map = SEOConfig::get('push.daily_cap', []);
        $cap = $cap_map[$plan] ?? ($cap_map['free'] ?? 100);
        if ($cap === -1) {
            return true; // unlimited
        }

        $bucket = LINKED3_OPTION_PREFIX . 'push_quota_' . $plan . '_' . sanitize_key($engine);
        $now    = time();
        $data   = get_transient($bucket);
        if (!is_array($data) || !isset($data['count'], $data['expires'])) {
            $data = ['count' => 0, 'expires' => strtotime('tomorrow 00:05')];
        } elseif ($now > (int) $data['expires']) {
            $data = ['count' => 0, 'expires' => strtotime('tomorrow 00:05')];
        }
        if ((int) $data['count'] >= (int) $cap) {
            wp_send_json_error([
                'code'        => 'linked3_push_quota_exhausted',
                'plan'        => $plan,
                'engine'      => $engine,
                'cap'         => $cap,
                'used'        => (int) $data['count'],
                'message'     => sprintf(
                    /* translators: 1: engine, 2: cap, 3: plan name. */
                    __('%1$s 每日推送配额已用完(%3$s 套餐每天 %2$d 次)。请升级或等待每日重置。', 'linked3'),
                    $engine,
                    $cap,
                    ucfirst($plan)
                ),
                'upgrade_url' => 'https://linked3.com/pricing',
            ], 402);
        }
        $data['count'] = (int) $data['count'] + 1;
        $ttl = max(1, (int) $data['expires'] - $now + 1);
        set_transient($bucket, $data, $ttl);
        return true;
    }

    /**
     * Increment the push-quota counter AFTER a push actually succeeded.
     * Called by push actions once the engine returns success. Quota denial
     * happens in require_push_quota() (called before the request).
     *
     * The transient bucket already has its count incremented at gate time,
     * so this method is a no-op unless an action explicitly opts into
     * post-success accounting (not currently used — kept for future
     * refund-on-failure semantics).
     *
     * @param string $engine
     * @return void
     */
    protected function refund_push_quota($engine)
    : void {
        // Reserved — quota is debited optimistically at gate time and not
        // refunded on push failure (a failed push still consumes the daily
        // slot per v2.9.6 behaviour). This stub exists so a future refactor
        // to deferred accounting can swap implementations without touching
        // every push action.
    }
}
