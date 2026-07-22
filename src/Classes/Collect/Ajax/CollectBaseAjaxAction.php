<?php

declare(strict_types=1);
namespace Linked3\Classes\Collect\Ajax;
    use \Linked3\Includes\Traits\TraitCheckAdminPermissions;
    use \Linked3\Includes\Traits\TraitCheckPlanAccess;
    use \Linked3\Includes\Traits\TraitSendWPError;


if (!defined('ABSPATH')) exit;

/**
 * Collect base ajax action.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.Collect.Ajax
 * @since      27.1.0
 */

abstract class CollectBaseAjaxAction
{
    const NONCE_ACTION = 'linked3_collect';
    const CAPABILITY = 'edit_posts';

    abstract public function handle();

    public function dispatch(): void {
        $this->verify(static::NONCE_ACTION, static::CAPABILITY);
        // Free 用户也可使用 (配额限制)
        $this->handle();
    }

    protected function scraper() {
        return new \Linked3\Classes\Collect\Scraper();
    }

    protected function rewriter() {
        return new \Linked3\Classes\Collect\Rewriter\ArticleRewriter();
    }
}
