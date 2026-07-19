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
            'Classes/AutoGPT/AutogptTaskRepository.php',
            'Classes/AutoGPT/Processors/AutogptProcessor.php',
            'Classes/AutoGPT/Processors/ContentWritingProcessor.php',
            'Classes/AutoGPT/Processors/ContentEnhancementProcessor.php',
            'Classes/AutoGPT/Processors/ContentIndexingProcessor.php',
            'Classes/AutoGPT/Processors/CommentReplyProcessor.php',
            'Classes/AutoGPT/Processors/AutogptProcessorFactory.php',
            'Classes/AutoGPT/Cron/AutogptCron.php',
            'Classes/AutoGPT/Ajax/AutogptBaseAjaxAction.php',
            'Classes/AutoGPT/Ajax/Actions/AutogptCreateTaskAction.php',
            'Classes/AutoGPT/Ajax/Actions/AutogptListTasksAction.php',
            'Classes/AutoGPT/Ajax/Actions/AutogptToggleTaskAction.php',
            'Classes/AutoGPT/Ajax/Actions/AutogptDeleteTaskAction.php',
            'Classes/AutoGPT/AutogptHooksRegistrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) require_once $path;
        }
    }
}
