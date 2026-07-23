<?php

declare(strict_types=1);
/**
 * Publish + Collect module dependency loader.
 *
 * @package Linked3
 * @subpackage Classes\Publish
 */

namespace Linked3\Classes\Publish;

if (!defined('ABSPATH')) {
    exit;
}

final class PublishCollectDependenciesLoader
{
    static function load(): void {
        $files = [
            // Publish module
            'Classes/Publish/PublishTargetInterface.php',
            'Classes/Publish/PublishConfig.php',
            'Classes/Publish/Adapter/LocalPublishTarget.php',
            'Classes/Publish/Adapter/RemoteWPPublishTarget.php',
            'Classes/Publish/Adapter/RemoteDBPublishTarget.php',
            'Classes/Publish/Adapter/CustomAPIPublishTarget.php',
            'Classes/Publish/PublishTargetRepository.php',
            'Classes/Publish/PublishManager.php',
            'Classes/Publish/Ajax/PublishBaseAjaxAction.php',
            'Classes/Publish/Ajax/Actions/PublishSaveTargetAction.php',
            'Classes/Publish/Ajax/Actions/PublishTestTargetAction.php',
            'Classes/Publish/Ajax/Actions/PublishDeleteTargetAction.php',
            'Classes/Publish/Ajax/Actions/PublishNowAction.php',
            // Collect module
            'Classes/Collect/Scraper.php',
            'Classes/Collect/CollectSourceRepository.php',
            'Classes/Collect/Rewriter/ArticleRewriter.php',
            'Classes/Collect/Ajax/CollectBaseAjaxAction.php',
            'Classes/Collect/Ajax/Actions/CollectScrapeAction.php',
            'Classes/Collect/Ajax/Actions/CollectRewriteAction.php',
            'Classes/Collect/Ajax/Actions/CollectBulkRewriteAction.php',
            // Hooks registrar
            'Classes/Publish/PublishCollectHooksRegistrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
