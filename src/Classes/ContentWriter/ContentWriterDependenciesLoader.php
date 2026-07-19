<?php

declare(strict_types=1);
/**
 * Content Writer module — dependency loader.
 *
 * @package Linked3
 * @subpackage Classes\ContentWriter
 */

namespace Linked3\Classes\ContentWriter;

if (!defined('ABSPATH')) {
    exit;
}

final class ContentWriterDependenciesLoader
{
    public static function load()
    : void {
        $files = [
            'Classes/ContentWriter/Prompt/class-linked3-system-instruction-builder.php',
            'Classes/ContentWriter/Prompt/class-linked3-user-prompt-builder.php',
            'Classes/ContentWriter/Prompt/class-linked3-excerpt-prompt-builder.php',
            'Classes/ContentWriter/Prompt/class-linked3-meta-prompt-builder.php',
            'Classes/ContentWriter/Prompt/class-linked3-tags-prompt-builder.php',
            'Classes/ContentWriter/Prompt/class-linked3-keyword-prompt-builder.php',
            'Classes/ContentWriter/Input/interface-linked3-input-source.php',
            'Classes/ContentWriter/Input/class-linked3-rss-input-source.php',
            'Classes/ContentWriter/Input/class-linked3-csv-input-source.php',
            'Classes/ContentWriter/Input/class-linked3-url-input-source.php',
            'Classes/ContentWriter/Input/class-linked3-manual-input-source.php',
            'Classes/ContentWriter/class-linked3-content-template-manager.php',
            'Classes/ContentWriter/class-linked3-image-injector.php',
            'Classes/ContentWriter/Ajax/class-linked3-content-writer-base-ajax-action.php',
            'Classes/ContentWriter/Ajax/Actions/class-linked3-generate-content-action.php',
            'Classes/ContentWriter/Ajax/Actions/class-linked3-generate-title-action.php',
            'Classes/ContentWriter/Ajax/Actions/class-linked3-generate-meta-action.php',
            'Classes/ContentWriter/Ajax/Actions/class-linked3-generate-tags-action.php',
            'Classes/ContentWriter/Ajax/Actions/class-linked3-generate-excerpt-action.php',
            'Classes/ContentWriter/Ajax/Actions/class-linked3-init-stream-action.php',
            'Classes/ContentWriter/class-linked3-content-writer-hooks-registrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
