<?php
namespace Linked3\Classes\AutoGPT\Ajax;
    use \Linked3\Includes\Traits\Trait_Check_Admin_Permissions;
    use \Linked3\Includes\Traits\Trait_Check_Plan_Access;
    use \Linked3\Includes\Traits\Trait_Send_WP_Error;


if (!defined('ABSPATH')) exit;

/**
 * Autogpt base ajax action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Ajax
 * @since      27.1.0
 */

abstract class Linked3_AutoGPT_Base_Ajax_Action
{
    const NONCE_ACTION = 'linked3_autogpt';
    const CAPABILITY = 'manage_options';

    abstract public function handle();

    public function dispatch()
    : void {
        $this->verify(static::NONCE_ACTION, static::CAPABILITY);
        // Free 用户也可使用 (Agent 数量限制)
        $this->handle();
    }

    protected function repo()
    : Linked3 {
        return new \Linked3\Classes\AutoGPT\Linked3_AutoGPT_Task_Repository();
    }
}
