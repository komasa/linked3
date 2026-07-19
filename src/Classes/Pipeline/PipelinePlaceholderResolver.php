<?php

declare(strict_types=1);
/**
 * Pipeline Placeholder Resolver — unified {placeholder} substitution.
 *
 * v5.1.5: replaces {keyword} {topic} {title} {outline} etc. in pipeline
 * template prompts with values from the pipeline context.
 *
 * Supports:
 *   - Basic placeholders: {keyword} {topic} {title} {word_count} {tone} ...
 *   - v15 placeholders (v5.2+): {brand} {signature} {color} {mood} ...
 *   - Filterable context via apply_filters('linked3/pipeline/placeholders')
 *   - Unmatched placeholder warning (logged, not fatal)
 *
 * @package Linked3
 * @subpackage Classes\Pipeline
 */

namespace Linked3\Classes\Pipeline;

if (!defined('ABSPATH')) {
    exit;
}

final class PipelinePlaceholderResolver
{
    /**
     * Replace all {placeholder} tokens in $text with values from $context.
     *
     * @param string $text    The template text containing {placeholders}.
     * @param array  $context Key-value map of placeholder name → value.
     * @return string The resolved text.
     */
    public static function resolve(string $text, array $context): string
    {
        if (empty($text)) {
            return '';
        }

        // Allow modules to inject additional placeholders.
        $context = (array) apply_filters('linked3/pipeline/placeholders', $context);

        // Replace each {key} with the context value.
        $resolved = $text;
        $unmatched = [];

        foreach ($context as $key => $value) {
            $token = '{' . $key . '}';
            if (strpos($resolved, $token) !== false) {
                $resolved = str_replace($token, (string) $value, $resolved);
            }
        }

        // Detect unmatched placeholders (for debugging).
        if (preg_match_all('/\{([a-z_]+)\}/', $resolved, $matches)) {
            $unmatched = $matches[1];
        }

        if (!empty($unmatched) && class_exists('\\Linked3\\Includes\\Log\\Logger')) {
            \Linked3\Includes\Log\Logger::instance()->debug(
                'pipeline',
                'Unmatched placeholders in template',
                ['unmatched' => $unmatched]
            );
        }

        return $resolved;
    }

    /**
     * Get the list of all supported placeholder names.
     *
     * @return array
     */
    public static function supported_placeholders(): array
    {
        // Basic placeholders (v5.1)
        $basic = [
            'keyword', 'topic', 'title', 'outline', 'prev_summary',
            'word_count', 'tone', 'complexity', 'language',
            'sections', 'prompt', 'provider', 'model',
            'temperature', 'max_tokens',
        ];

        // v15 placeholders (v5.2+ — reserved for future use)
        $v15 = [
            'brand', 'signature', 'color', 'mood', 'culture',
            'platform', 'density', 'product_type',
            'info_seed', 'id_seed', 'character_seed', 'chart_dna',
            'script', 'arc', 'endpoint', 'footer',
        ];

        return array_merge($basic, $v15);
    }
}
