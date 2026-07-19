<?php

declare(strict_types=1);
namespace Linked3\Classes\AutoGPT\Processors;
if (!defined('ABSPATH')) exit;

/**
 * Autogpt processor factory.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Processors
 * @since      27.1.0
 */

final class AutoGPTProcessorFactory
{
    private static $map = [
        'content-writing'     => ContentWritingProcessor::class,
        'content-enhancement' => ContentEnhancementProcessor::class,
        'content-indexing'    => ContentIndexingProcessor::class,
        'comment-reply'       => CommentReplyProcessor::class,
        'collect-rewrite'     => CollectRewriteProcessor::class,  // v3.2.0
    ];

    public static function make($type) : mixed {
        $class = self::$map[$type] ?? null;
        return $class ? new $class() : null;
    }
}
