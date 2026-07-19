<?php

declare(strict_types=1);
/**
 * AutoGPT module — dependency loader.
 *
 * @package Linked3
 * @subpackage Classes\AutoGPT
 */

namespace Linked3\Classes\AutoGPT;

if (!defined('ABSPATH')) {
    exit;
}

final class AutoGPTDependenciesLoader
{
    public static function load()
    : void {
        $files = [
            'Classes/AutoGPT/class-linked3-autogpt-task-repository.php',
            'Classes/AutoGPT/Processors/interface-linked3-autogpt-processor.php',
            'Classes/AutoGPT/Processors/class-linked3-content-writing-processor.php',
            'Classes/AutoGPT/Processors/class-linked3-content-enhancement-processor.php',
            'Classes/AutoGPT/Processors/class-linked3-content-indexing-processor.php',
            'Classes/AutoGPT/Processors/class-linked3-comment-reply-processor.php',
            'Classes/AutoGPT/Processors/class-linked3-autogpt-processor-factory.php',
            'Classes/AutoGPT/Cron/class-linked3-autogpt-cron.php',
            'Classes/AutoGPT/Ajax/class-linked3-autogpt-base-ajax-action.php',
            'Classes/AutoGPT/Ajax/Actions/class-linked3-autogpt-create-task-action.php',
            'Classes/AutoGPT/Ajax/Actions/class-linked3-autogpt-list-tasks-action.php',
            'Classes/AutoGPT/Ajax/Actions/class-linked3-autogpt-toggle-task-action.php',
            'Classes/AutoGPT/Ajax/Actions/class-linked3-autogpt-delete-task-action.php',
            'Classes/AutoGPT/class-linked3-autogpt-hooks-registrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) require_once $path;
        }
    }
}
