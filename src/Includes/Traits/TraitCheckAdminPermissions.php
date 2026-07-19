<?php

declare(strict_types=1);
/**
 * Trait: enforce admin capability + nonce on AJAX handlers.
 *
 * Usage in an Action class:
 *   class My_Action {
 *       use TraitCheckAdminPermissions;
 *       public function handle() : void {
 *           $this->verify('my_action_nonce');
 *           // ... business logic
 *       }
 *   }
 *
 * @package Linked3
 * @subpackage Includes\Traits
 */

namespace Linked3\Includes\Traits;

if (!defined('ABSPATH')) {
    exit;
}

trait TraitCheckAdminPermissions
{
    /**
     * @param string      $action     Nonce action name.
     * @param string|null $capability Required capability. Default 'manage_options'.
     * @param string      $nonce_key  $_POST/$_GET key holding the nonce. Default 'nonce'.
     * @return true Always true on success; wp_send_json_error on failure.
     */
    protected function verify($action, $capability = null, $nonce_key = 'nonce')
    : bool {
        // Constitution §2: global 60 req/min/IP ceiling on every Linked3 AJAX.
        \Linked3\Classes\Security\RateLimiter::gate('ajax');

        $capability = $capability ?: 'manage_options';

        if (!current_user_can($capability)) {
            $this->forbidden(__('您没有权限执行此操作。', 'linked3'));
        }

        $nonce = isset($_REQUEST[$nonce_key]) ? sanitize_text_field(wp_unslash($_REQUEST[$nonce_key])) : '';
        if (!wp_verify_nonce($nonce, $action)) {
            $this->forbidden(__('安全校验失败,请刷新页面重试。', 'linked3'));
        }

        return true;
    }

    /**
     * @param string $message
     * @return never
     */
    protected function forbidden($message)
    : void {
        wp_send_json_error(
            ['code' => 'linked3_forbidden', 'message' => $message],
            403
        );
    }
}
