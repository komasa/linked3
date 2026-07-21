<?php

declare(strict_types=1);
/**
 * HowTo schema builder.
 *
 * Parses ordered lists (<ol>) following a heading containing "教程" /
 * "步骤" / "How to" / "Guide" into a Schema.org HowTo structure.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

if (!defined('ABSPATH')) {
    exit;
}

final class SchemaHowTo implements SchemaBuilder
{
    public function type(): string
    {
        return 'HowTo';
    }

    public function build($post): ?array
    {
        if (!$post) {
            return null;
        }
        $steps = $this->extract_steps($post->post_content);
        if (empty($steps)) {
            return null;
        }
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            'name'     => (string) $post->post_title,
            'step'     => $steps,
        ];
    }

    /**
     * @param string $content
     * @return array<int,array{@type:string,name:string,text:string}>
     */
    public function extract_steps(string $content) : mixed {
        $content = (string) $content;
        $out = [];
        // Match <ol> blocks; for each, treat <li> as a step.
        if (preg_match_all('#<ol[^>]*>(.*?)</ol>#isu', $content, $ols, PREG_SET_ORDER)) {
            foreach ($ols as $ol) {
                if (preg_match_all('#<li[^>]*>(.*?)</li>#isu', $ol[1], $lis, PREG_SET_ORDER)) {
                    foreach ($lis as $li) {
                        $text = trim(wp_strip_all_tags($li[1]));
                        if ($text !== '') {
                            // Split on first colon/period into name + text.
                            if (preg_match('/^(.{1,80}?)[:：.。]\s*(.+)$/u', $text, $split)) {
                                $out[] = ['@type' => 'HowToStep', 'name' => trim($split[1]), 'text' => trim($split[2])];
                            } else {
                                $out[] = ['@type' => 'HowToStep', 'name' => mb_substr($text, 0, 40), 'text' => $text];
                            }
                        }
                    }
                }
            }
        }
        return $out;
    }
}
