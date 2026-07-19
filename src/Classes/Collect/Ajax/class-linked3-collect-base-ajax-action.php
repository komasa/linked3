<?php
namespace Linked3\Classes\Collect\Ajax;
    use \Linked3\Includes\Traits\Trait_Check_Admin_Permissions;
    use \Linked3\Includes\Traits\Trait_Check_Plan_Access;
    use \Linked3\Includes\Traits\Trait_Send_WP_Error;


if (!defined('ABSPATH')) exit;

/**
 * Collect base ajax action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Collect.Ajax
 * @since      27.1.0
 */

abstract class Linked3_Collect_Base_Ajax_Action
{
    const NONCE_ACTION = 'linked3_collect';
    const CAPABILITY = 'edit_posts';

    abstract public function handle();

    public function dispatch()
    : void {
        $this->verify(static::NONCE_ACTION, static::CAPABILITY);
        // Free 用户也可使用 (配额限制)
        $this->handle();
    }

    protected function scraper()
    : Linked3 {
        return new \Linked3\Classes\Collect\Linked3_Scraper();
    }

    protected function rewriter()
    : Linked3 {
        return new \Linked3\Classes\Collect\Rewriter\Linked3_Article_Rewriter();
    }
}
