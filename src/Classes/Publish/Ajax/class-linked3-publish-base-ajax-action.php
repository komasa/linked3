<?php
/**
 * Base AJAX action for Publish module.
 *
 * @package Linked3
 * @subpackage Classes\Publish\Ajax
 */

namespace Linked3\Classes\Publish\Ajax;

use Linked3\Includes\Traits\Trait_Check_Admin_Permissions;
use Linked3\Includes\Traits\Trait_Check_Plan_Access;
use Linked3\Includes\Traits\Trait_Send_WP_Error;




if (!defined('ABSPATH')) {
    exit;
}
abstract class Linked3_Publish_Base_Ajax_Action
{
    const NONCE_ACTION = 'linked3_publish';
    const CAPABILITY = 'edit_posts';

    abstract public function handle();

    public function dispatch()
    : void {
        $this->verify(static::NONCE_ACTION, static::CAPABILITY);
        // Free 用户也可使用 (目标数量限制)
        $this->handle();
    }

    protected function manager() : mixed {
        return \Linked3\Classes\Publish\Linked3_Publish_Manager::instance();
    }

    protected function repo()
    : Linked3 {
        return new \Linked3\Classes\Publish\Linked3_Publish_Target_Repository();
    }
}
