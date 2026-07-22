<?php

declare(strict_types=1);
/**
 * Pipeline Stage — base implementation with shared utilities.
 *
 * v5.1.0: provides common helpers (logging, error formatting, data merging)
 * that concrete stages can use without duplicating boilerplate.
 *
 * @package Linked3
 * @subpackage Classes\Pipeline
 */

namespace Linked3\Classes\Pipeline;

if (!defined('ABSPATH')) {
    exit;
}

// 确保接口在实现类之前加载 (glob_scan 按字母排序, interface- 排在 class- 之后)
if (!interface_exists('Linked3\\Classes\\Pipeline\\PipelineStageInterface')) {
    require_once __DIR__ . '/PipelineStage.php';
}

abstract class PipelineStage implements PipelineStageInterface
{
    /**
     * Create a success result.
     *
     * @param array  $data
     * @param string $message
     * @return array{ok:bool, data:array, message:string}
     */
    protected function success(array $data, string $message = ''): array
    {
        return ['ok' => true, 'data' => $data, 'message' => $message];
    }

    /**
     * Log a stage event.
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (class_exists('\\Linked3\\Includes\\Log\\Logger')) {
            \Linked3\Includes\Log\Logger::instance()->log('pipeline', $level, $message, $context);
        }
    }
}
