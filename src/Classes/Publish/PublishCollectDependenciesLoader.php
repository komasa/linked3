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
    public static function load()
    : void {
        $files = [
            // Publish module
            'Classes/Publish/interface-linked3-publish-target.php',
            'Classes/Publish/class-linked3-publish-config.php',
            'Classes/Publish/Adapter/class-linked3-local-publish-target.php',
            'Classes/Publish/Adapter/class-linked3-remote-wp-publish-target.php',
            'Classes/Publish/Adapter/class-linked3-remote-db-publish-target.php',
            'Classes/Publish/Adapter/class-linked3-custom-api-publish-target.php',
            'Classes/Publish/class-linked3-publish-target-repository.php',
            'Classes/Publish/class-linked3-publish-manager.php',
            'Classes/Publish/Ajax/class-linked3-publish-base-ajax-action.php',
            'Classes/Publish/Ajax/Actions/class-linked3-publish-save-target-action.php',
            'Classes/Publish/Ajax/Actions/class-linked3-publish-test-target-action.php',
            'Classes/Publish/Ajax/Actions/class-linked3-publish-delete-target-action.php',
            'Classes/Publish/Ajax/Actions/class-linked3-publish-now-action.php',
            // Collect module
            'Classes/Collect/class-linked3-scraper.php',
            'Classes/Collect/class-linked3-collect-source-repository.php',
            'Classes/Collect/Rewriter/class-linked3-article-rewriter.php',
            'Classes/Collect/Ajax/class-linked3-collect-base-ajax-action.php',
            'Classes/Collect/Ajax/Actions/class-linked3-collect-scrape-action.php',
            'Classes/Collect/Ajax/Actions/class-linked3-collect-rewrite-action.php',
            'Classes/Collect/Ajax/Actions/class-linked3-collect-bulk-rewrite-action.php',
            // Hooks registrar
            'Classes/Publish/class-linked3-publish-collect-hooks-registrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
