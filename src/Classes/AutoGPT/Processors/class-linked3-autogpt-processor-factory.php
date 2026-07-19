<?php
namespace Linked3\Classes\AutoGPT\Processors;
if (!defined('ABSPATH')) exit;

/**
 * Autogpt processor factory.
 *
 * @package    Linked3
 * @subpackage Linked3.Classes.AutoGPT.Processors
 * @since      27.1.0
 */

final class Linked3_AutoGPT_Processor_Factory
{
    private static $map = [
        'content-writing'     => Linked3_Content_Writing_Processor::class,
        'content-enhancement' => Linked3_Content_Enhancement_Processor::class,
        'content-indexing'    => Linked3_Content_Indexing_Processor::class,
        'comment-reply'       => Linked3_Comment_Reply_Processor::class,
        'collect-rewrite'     => Linked3_Collect_Rewrite_Processor::class,  // v3.2.0
    ];

    public static function make($type) : mixed {
        $class = self::$map[$type] ?? null;
        return $class ? new $class() : null;
    }
}
