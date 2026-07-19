<?php

declare(strict_types=1);
/**
 * Seed Admin — Export (G4.1 split from SeedAdmin).
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @since      27.5.0
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class SeedAdminExport
{
    public static function export_md($post_id) : mixed {
        $seed = GenesisSeedCPT::get($post_id);
        if (!$seed) return '';

        $sid   = $seed['seed_id'] ?? '';
        $title = $seed['title'] ?? '';
        $cat   = $seed['seed_category'] ?? '';
        $type  = $seed['seed_type'] ?? '';
        $cat_label = self::$CATEGORIES[$cat] ?? $cat;
        $parent = $seed['parent_seed'] ?? '';
        $project = $seed['project_ref'] ?? '';

        $lines = [];
        $lines[] = sprintf('# %sSeed: %s', ucfirst($cat), $sid ?: $title);
        $lines[] = '';
        $lines[] = sprintf('> %s | %s | %s', $title, $cat_label, self::$TYPES[$type] ?? $type);
        if ($parent) $lines[] = sprintf('> Parent: %s', $parent);
        if ($project) $lines[] = sprintf('> Project: %s', $project);
        $lines[] = '';
        $lines[] = sprintf('Exported: %s', current_time('mysql'));
        $lines[] = '';

        // VisualDNA
        $vdna = $seed['visual_dna'] ?? [];
        if (is_array($vdna) && !empty($vdna)) {
            $lines[] = '## VisualDNA';
            $lines[] = '';
            foreach (self::$VISUAL_FIELDS as $key => $label) {
                if (isset($vdna[$key]) && $vdna[$key] !== '') {
                    $lines[] = sprintf('- **%s**: %s', $label, $vdna[$key]);
                }
            }
            // 额外字段
            $extra = $vdna;
            foreach (self::$VISUAL_FIELDS as $k => $_) unset($extra[$k]);
            foreach ($extra as $k => $v) {
                $lines[] = sprintf('- **%s**: %s', $k, is_array($v) ? wp_json_encode($v, JSON_UNESCAPED_UNICODE) : $v);
            }
            $lines[] = '';
        }

        // PersonalityDNA
        $pdna = $seed['personality_dna'] ?? [];
        if (is_array($pdna) && !empty($pdna)) {
            $lines[] = '## PersonalityDNA';
            $lines[] = '';
            foreach (self::$PERSONALITY_FIELDS as $key => $label) {
                if (isset($pdna[$key]) && $pdna[$key] !== '') {
                    $lines[] = sprintf('- **%s**: %s', $label, $pdna[$key]);
                }
            }
            $extra = $pdna;
            foreach (self::$PERSONALITY_FIELDS as $k => $_) unset($extra[$k]);
            foreach ($extra as $k => $v) {
                $lines[] = sprintf('- **%s**: %s', $k, is_array($v) ? wp_json_encode($v, JSON_UNESCAPED_UNICODE) : $v);
            }
            $lines[] = '';
        }

        // Priority
        $priority = $seed['priority'] ?? [];
        if (is_array($priority) && !empty($priority)) {
            $lines[] = '## Priority';
            $lines[] = '';
            foreach (self::$PRIORITY_GROUPS as $key => $label) {
                $items = $priority[$key] ?? [];
                if (!empty($items)) {
                    $lines[] = sprintf('### %s', $label);
                    foreach ((array) $items as $item) {
                        $lines[] = sprintf('- %s', $item);
                    }
                    $lines[] = '';
                }
            }
        }

        // Lock
        $lock = $seed['lock'] ?? [];
        if (is_array($lock) && !empty($lock)) {
            $lines[] = '## Lock';
            $lines[] = '';
            foreach ($lock as $l) {
                $lines[] = sprintf('- `%s`', $l);
            }
            $lines[] = '';
        }

        // AI Adapter
        $adapter = $seed['ai_adapter'] ?? [];
        if (is_array($adapter) && !empty($adapter)) {
            $lines[] = '## AI Adapter';
            $lines[] = '';
            foreach (self::$AI_PLATFORMS as $key => $label) {
                if (isset($adapter[$key]) && $adapter[$key] !== '') {
                    $lines[] = sprintf('### %s', $label);
                    $lines[] = '```';
                    $lines[] = $adapter[$key];
                    $lines[] = '```';
                    $lines[] = '';
                }
            }
        }

        $lines[] = '---';
        $lines[] = sprintf('<!-- linked3_seed post_id="%d" seed_id="%s" -->', $post_id, esc_attr($sid));

        return implode("\n", $lines);
    }

    public static function export_json($post_id) : mixed     {
        $seed = GenesisSeedCPT::get($post_id);
        if (!$seed) return '{}';

        // 平铺为标准 export schema
        $export = [
            'schema'        => 'linked3.seed.v1',
            'exported_at'   => current_time('mysql'),
            'post_id'       => (int) $post_id,
            'seed_id'       => $seed['seed_id'] ?? '',
            'title'         => $seed['title'] ?? '',
            'seed_type'     => $seed['seed_type'] ?? 'fixed',
            'seed_category' => $seed['seed_category'] ?? 'char',
            'parent_seed'   => $seed['parent_seed'] ?? '',
            'project_ref'   => $seed['project_ref'] ?? '',
            'visual_dna'    => is_array($seed['visual_dna'] ?? null) ? $seed['visual_dna'] : (object) [],
            'personality_dna' => is_array($seed['personality_dna'] ?? null) ? $seed['personality_dna'] : (object) [],
            'priority'      => is_array($seed['priority'] ?? null) ? $seed['priority'] : (object) [],
            'lock'          => is_array($seed['lock'] ?? null) ? $seed['lock'] : [],
            'ai_adapter'    => is_array($seed['ai_adapter'] ?? null) ? $seed['ai_adapter'] : (object) [],
        ];

        return wp_json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function export_batch($filter)
    {
        $category = $filter['category'] ?? '';
        $project  = $filter['project'] ?? '';
        $post_ids = $filter['post_ids'] ?? [];
        $format   = $filter['format'] ?? 'md';

        $args = [
            'post_type'      => 'linked3_seed',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ];
        if (!empty($post_ids)) {
            $args['post__in'] = $post_ids;
        }
        if ($category) {
            $args['meta_query'] = [['key' => 'seed_category', 'value' => $category]];
        }
        if ($project) {
            $args['meta_query'] = $args['meta_query'] ?? ['relation' => 'AND'];
            $args['meta_query'][] = ['key' => 'project_ref', 'value' => $project];
        }

        $query = new \WP_Query($args);
        $files = [];
        $tmp_dir = self::ensure_tmp_dir();
        if (!$tmp_dir) return $files;

        foreach ($query->posts as $pid) {
            $seed = GenesisSeedCPT::get($pid);
            if (!$seed) continue;
            $sid = $seed['seed_id'] ?: ('pid-' . $pid);
            $safe = sanitize_file_name($sid);
            if ($format === 'json') {
                $content = self::export_json($pid);
                $ext = 'json';
            } else {
                $content = self::export_md($pid);
                $ext = 'md';
            }
            $path = $tmp_dir . '/' . $safe . '.' . $ext;
            file_put_contents($path, $content);
            $files[] = $path;
        }

        return $files;
    }

}
