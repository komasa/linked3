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
            'Classes/ContentWriter/Prompt/SystemInstructionBuilder.php',
            'Classes/ContentWriter/Prompt/UserPromptBuilder.php',
            'Classes/ContentWriter/Prompt/ExcerptPromptBuilder.php',
            'Classes/ContentWriter/Prompt/MetaPromptBuilder.php',
            'Classes/ContentWriter/Prompt/TagsPromptBuilder.php',
            'Classes/ContentWriter/Prompt/KeywordPromptBuilder.php',
            'Classes/ContentWriter/Input/InputSourceInterface.php',
            'Classes/ContentWriter/Input/RssInputSource.php',
            'Classes/ContentWriter/Input/CsvInputSource.php',
            'Classes/ContentWriter/Input/UrlInputSource.php',
            'Classes/ContentWriter/Input/ManualInputSource.php',
            'Classes/ContentWriter/ContentTemplateManager.php',
            'Classes/ContentWriter/ImageInjector.php',
            'Classes/ContentWriter/Ajax/ContentWriterBaseAjaxAction.php',
            'Classes/ContentWriter/Ajax/Actions/GenerateContentAction.php',
            'Classes/ContentWriter/Ajax/Actions/GenerateTitleAction.php',
            'Classes/ContentWriter/Ajax/Actions/GenerateMetaAction.php',
            'Classes/ContentWriter/Ajax/Actions/GenerateTagsAction.php',
            'Classes/ContentWriter/Ajax/Actions/GenerateExcerptAction.php',
            'Classes/ContentWriter/Ajax/Actions/InitStreamAction.php',
            'Classes/ContentWriter/ContentWriterHooksRegistrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
