<?php

declare(strict_types=1);
namespace Linked3\Classes\AutoGPT\Ajax;
    use \Linked3\Includes\Traits\TraitCheckAdminPermissions;
    use \Linked3\Includes\Traits\TraitCheckPlanAccess;
    use \Linked3\Includes\Traits\TraitSendWPError;


if (!defined('ABSPATH')) exit;

/**
 * Autogpt base ajax action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Ajax
 * @since      27.1.0
 */

abstract class AutoGPTBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_autogpt';
    const CAPABILITY = 'manage_options';

    abstract public function handle();

    public function dispatch(): void {
        $this->verify(static::NONCE_ACTION, static::CAPABILITY);
        // Free 用户也可使用 (Agent 数量限制)
        $this->handle();
    }

    protected function repo(): Linked3 {
        return new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
    }
}
