<?php

declare(strict_types=1);
/**
 * Base AJAX action — shared nonce/cap/quota/plan gate for all Content Writer actions.
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter\Ajax
 */

namespace Linked3\Classes\ContentWriter\Ajax;

use Linked3\Includes\Traits\Trait_Check_Admin_Permissions;
use Linked3\Includes\Traits\Trait_Send_WP_Error;
use Linked3\Includes\Traits\Trait_Check_Plan_Access;
use Linked3\Classes\Core\TokenManager;
use Linked3\Classes\Core\AIDispatcher;




if (!defined('ABSPATH')) {
    exit;
}
abstract class ContentWriterBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_content_writer';
    const CAPABILITY = 'edit_posts';

    /**
     * Subclasses implement the actual business logic.
     *
     * @return void
     */
    abstract public function handle();

    /**
     * Verify everything, then dispatch.
     *
     * @return void
     */
    public function dispatch()
    : void {
        $this->verify(static::NONCE_ACTION, static::CAPABILITY);
        // Free 用户也可使用 (配额限制即可,不阻止)
        $this->check_quota();
        $this->handle();
    }

    /**
     * @return void
     */
    protected function check_quota()
    : void {
        $user_id = get_current_user_id();
        $check = TokenManager::instance()->check($user_id, '', 1);
        if (!$check['ok']) {
            wp_send_json_error([
                'code' => 'linked3_quota_exhausted',
                'message' => __('每日 Token 配额已用完,请升级套餐或等待每日重置。', 'linked3'),
                'quota' => $check,
            ], 429);
        }
    }

    /**
     * @return AIDispatcher
     */
    protected function dispatcher() : mixed {
        return AIDispatcher::instance();
    }

    /**
     * @return ContentTemplateManager
     */
    protected function templates()
    : Linked3 {
        return new \Linked3\Classes\ContentWriter\ContentTemplateManager();
    }
}
