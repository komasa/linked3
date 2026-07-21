<?php

declare(strict_types=1);
/**
 * Base AJAX action for Publish module.
 *
 * @package Linked3
 * @subpackage Classes\Publish\Ajax
 */

namespace Linked3\Classes\Publish\Ajax;

use Linked3\Includes\Traits\TraitCheckAdminPermissions;
use Linked3\Includes\Traits\TraitCheckPlanAccess;
use Linked3\Includes\Traits\TraitSendWPError;




if (!defined('ABSPATH')) {
    exit;
}
abstract class PublishBaseAjaxAction
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
        return \Linked3\Classes\Publish\PublishManager::instance();
    }

    protected function repo()
    : Linked3 {
        return new \Linked3\Classes\Publish\PublishTargetRepository();
    }
}
