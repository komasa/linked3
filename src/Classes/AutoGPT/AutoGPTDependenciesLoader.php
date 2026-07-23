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
    static function load(): void {
        $files = [
            'Classes/AutoGPT/AutoGPTTaskRepository.php',
            'Classes/AutoGPT/Processors/AutoGPTProcessorInterface.php',
            'Classes/AutoGPT/Processors/ContentWritingProcessor.php',
            'Classes/AutoGPT/Processors/ContentEnhancementProcessor.php',
            'Classes/AutoGPT/Processors/ContentIndexingProcessor.php',
            'Classes/AutoGPT/Processors/CommentReplyProcessor.php',
            'Classes/AutoGPT/Processors/AutoGPTProcessorFactory.php',
            'Classes/AutoGPT/Cron/AutoGPTCron.php',
            'Classes/AutoGPT/Ajax/AutoGPTBaseAjaxAction.php',
            'Classes/AutoGPT/Ajax/Actions/AutoGPTCreateTaskAction.php',
            'Classes/AutoGPT/Ajax/Actions/AutoGPTListTasksAction.php',
            'Classes/AutoGPT/Ajax/Actions/AutoGPTToggleTaskAction.php',
            'Classes/AutoGPT/Ajax/Actions/AutoGPTDeleteTaskAction.php',
            'Classes/AutoGPT/AutoGPTHooksRegistrar.php',
        ];
        foreach ($files as $relative) {
            $path = LINKED3_DIR . 'src/' . $relative;
            if (file_exists($path)) require_once $path;
        }
    }
}
