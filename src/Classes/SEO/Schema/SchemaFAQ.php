<?php

declare(strict_types=1);
/**
 * FAQ schema builder — extracts Q&A pairs from post content.
 *
 * Mirrors v2.9.6 extract_faq_smartly. Heuristics:
 *   1) <details><summary>Q</summary>…A…</details> blocks
 *   2) Headings starting with "Q:" / "问:" / "问题:" followed by a paragraph
 *   3) <!-- FAQ: question | answer --> HTML comments
 *
 * Falls back to null when no Q&A pairs are detected — the orchestrator
 * then skips emitting FAQPage.
 *
 * @package Linked3
 * @subpackage Classes\SEO\Schema
 */

namespace Linked3\Classes\SEO\Schema;

if (!defined('ABSPATH')) {
    exit;
}

final class SchemaFAQ implements SchemaBuilder
{
    public function type(): string
    {
        return 'FAQPage';
    }

    public function build(WP_Post $post): ?array
    {
        if (!$post) {
            return null;
        }
        $pairs = $this->extract_faq($post->post_content);
        if (empty($pairs)) {
            return null;
        }
        $entities = [];
        foreach ($pairs as $p) {
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $p['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $p['answer'],
                ],
            ];
        }
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'FAQPage',
            'mainEntity'      => $entities,
        ];
    }

    /**
     * @param string $content
     * @return array<int,array{question:string,answer:string}>
     */
    public function extract_faq(string $content) : mixed {
        $content = (string) $content;
        $out = [];

        // 1) <details><summary>Q</summary>A</details>
        if (preg_match_all('#<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#isu', $content, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $q = trim(wp_strip_all_tags($row[1]));
                $a = trim(wp_strip_all_tags($row[2]));
                if ($q !== '' && $a !== '') {
                    $out[] = ['question' => $q, 'answer' => $a];
                }
            }
        }

        // 2) Headings starting with Q:/问:/问题:.
        if (preg_match_all('#<(h[2-4])[^>]*>\s*(Q:|问:|问题:|Q\s*[:：])\s*(.*?)</\1>#isu', $content, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $q = trim(wp_strip_all_tags($row[3]));
                // Answer = everything until the next heading of same/higher level.
                $start_pos = strpos($content, $row[0]) + strlen($row[0]);
                $rest = substr($content, $start_pos);
                $a = '';
                if (preg_match('#^((?:.|\n)*?)(?:<h[2-4]|$)#i', $rest, $am)) {
                    $a = trim(wp_strip_all_tags($am[1]));
                }
                if ($q !== '' && $a !== '') {
                    $out[] = ['question' => $q, 'answer' => $a];
                }
            }
        }

        // 3) HTML comments: <!-- FAQ: Q | A -->.
        if (preg_match_all('#<!--\s*FAQ:\s*(.*?)\s*\|\s*(.*?)\s*-->#isu', $content, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $q = trim(wp_strip_all_tags($row[1]));
                $a = trim(wp_strip_all_tags($row[2]));
                if ($q !== '' && $a !== '') {
                    $out[] = ['question' => $q, 'answer' => $a];
                }
            }
        }

        return $out;
    }
}
