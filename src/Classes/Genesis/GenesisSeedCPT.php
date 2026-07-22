<?php

declare(strict_types=1);
/**
 * Genesis Seed DNA CPT v8.1.0 — Seed DNA 引擎数据层
 *
 * 注册 WordPress Custom Post Type `linked3_seed` + meta 字段 + 索引
 *
 * 公理2·系统降维: Seed DNA 是分镜的"视觉基因库", 必须在分镜生成**之前**定义并持久化
 *   - 固定风格 Seed (CharacterSeed/BrandSeed) — 跨分镜不变
 *   - 可变场景 Seed (SceneSeed/PropSeed) — 随分镜推进演化
 *
 * @package Linked3\Genesis
 * @since 8.1.0
 */

namespace Linked3\Classes\Genesis;

use WP_Error;
if (!defined('ABSPATH')) exit;

class GenesisSeedCPT
{
    const POST_TYPE = 'linked3_seed';

    /** Meta 字段定义 (v8.1.0 M1.1.2) */
    const META_FIELDS = [
        'seed_id'        => 'string',  // TYPE_NUM_vN 格式, 唯一
        'seed_type'      => 'string',  // fixed|variable
        'seed_category'  => 'string',  // char|brand|scene|prop|style|soul
        'visual_dna'     => 'object',  // JSON: face/body/costume/accessory/...
        'personality_dna'=> 'object',  // JSON: personality/speech_pattern/emotion_range
        'priority'       => 'object',  // JSON: {critical:[], important:[], flexible:[]}
        'lock'           => 'array',   // 锁定项列表
        'ai_adapter'     => 'object',  // JSON: {mj:"", sd:"", flux:"", dalle:""}
        'parent_seed'    => 'string',  // 父Seed ID (版本链)
        'project_ref'    => 'string',  // 所属文章ID
    ];

    /**
     * 注册 CPT + meta + hooks (在 init hook 调用)
     */
    static function register(): void {
        self::register_post_type();
        self::register_meta_fields();
        self::register_admin_columns();
    }

    /**
     * M1.1.1: 注册 Custom Post Type
     */
    static function register_post_type(): void {
        $labels = [
            'name'               => __('Seed DNA', 'linked3'),
            'singular_name'      => __('Seed', 'linked3'),
            'add_new'            => __('新建 Seed', 'linked3'),
            'add_new_item'       => __('新建 Seed DNA', 'linked3'),
            'edit_item'          => __('编辑 Seed', 'linked3'),
            'new_item'           => __('新 Seed', 'linked3'),
            'view_item'          => __('查看 Seed', 'linked3'),
            'search_items'       => __('搜索 Seed', 'linked3'),
            'not_found'          => __('未找到 Seed', 'linked3'),
            'not_found_in_trash' => __('回收站无 Seed', 'linked3'),
            'menu_name'          => __('Seed DNA 库', 'linked3'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,  // 作为子菜单挂到 Linked3 主菜单下
            'show_in_rest'        => true,   // 支持 Block 编辑器
            'supports'            => ['title', 'editor', 'custom-fields', 'thumbnail', 'revisions'],
            'menu_icon'           => 'dashicons-id-alt',
            'hierarchical'        => false,
            'has_archive'         => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * M1.1.2: 注册 meta 字段 (register_post_meta)
     */
    private static function register_meta_fields() : mixed {
        foreach (self::META_FIELDS as $key => $type) {
            // v19.55-fix: match() is PHP 8.0+, plugin requires PHP 7.4 — convert to switch.
            switch ($type) {
                case 'string':
                    $sanitize_callback = 'sanitize_text_field';
                    break;
                case 'array':
                    $sanitize_callback = [__CLASS__, 'sanitize_array_meta'];
                    break;
                case 'object':
                    $sanitize_callback = [__CLASS__, 'sanitize_json_meta'];
                    break;
                default:
                    $sanitize_callback = 'sanitize_text_field';
                    break;
            }

            register_post_meta(self::POST_TYPE, $key, [
                'type'              => $type === 'string' ? 'string' : 'string',  // WP meta 统一存 string, JSON 编码
                'single'            => true,
                'sanitize_callback' => $sanitize_callback,
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                },
                'show_in_rest'      => true,
            ]);
        }
    }

    /**
     * M1.1.2: JSON meta 清洗
     */
    public static function sanitize_json_meta($value) : mixed     {
        if (is_array($value)) {
            return wp_json_encode($value);
        }
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $value;  // 已是合法 JSON
        }
        return '{}';
    }

    /**
     * M1.1.2: 数组 meta 清洗
     */
    public static function sanitize_array_meta($value)
    {
        if (is_array($value)) {
            return wp_json_encode(array_map('sanitize_text_field', $value));
        }
        return $value;
    }

    /**
     * M1.1.3: 注册管理列 (列表页显示分类/类型/ID)
     */
    static function register_admin_columns(): void {
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'add_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'render_column'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [__CLASS__, 'sortable_columns']);
    }

    public static function add_columns($columns)
    {
        $new = [];
        foreach ($columns as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') {
                $new['seed_id']       = __('Seed ID', 'linked3');
                $new['seed_category'] = __('分类', 'linked3');
                $new['seed_type']     = __('类型', 'linked3');
                $new['parent_seed']   = __('父 Seed', 'linked3');
            }
        }
        return $new;
    }

    static function render_column($column, $post_id): void {
        $val = get_post_meta($post_id, $column, true);
        if ($column === 'seed_category') {
            $labels = [
                'char' => '角色', 'brand' => '品牌', 'scene' => '场景',
                'prop' => '道具', 'style' => '画风', 'soul' => '灵魂风格',
            ];
            echo esc_html($labels[$val] ?? $val);
        } elseif ($column === 'seed_type') {
            if ($val === 'fixed') {
                echo '<span style="color:#16a34a;">固定</span>';
            } else {
                echo '<span style="color:#d97706;">可变</span>';
            }
        } else {
            echo esc_html($val ?: '—');
        }
    }

    public static function sortable_columns($columns)
    {
        $columns['seed_id'] = 'seed_id';
        $columns['seed_category'] = 'seed_category';
        return $columns;
    }

    // ================================================================
    // CRUD 接口 (供 SeedDNA 类和 AJAX 调用)
    // ================================================================

    /**
     * 创建 Seed (CPT post)
     *
     * @param array $data {title, seed_id, seed_type, seed_category, visual_dna, ...}
     * @return int|WP_Error post_id
     */
    public static function create(array $data): int|WP_Error
    {
        $post_id = wp_insert_post([
            'post_type'   => self::POST_TYPE,
            'post_title'  => $data['title'] ?? ($data['seed_id'] ?? 'Untitled Seed'),
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) return $post_id;

        self::update_meta($post_id, $data);
        return $post_id;
    }

    /**
     * 更新 Seed meta
     */
    static function update_meta($post_id, $data): void {
        foreach (self::META_FIELDS as $key => $type) {
            if (!isset($data[$key])) continue;
            $val = $data[$key];
            if ($type === 'object' || $type === 'array') {
                $val = is_string($val) ? $val : wp_json_encode($val);
            }
            update_post_meta($post_id, $key, $val);
        }
    }

    /**
     * 获取 Seed (含所有 meta)
     */
    public static function get($post_id)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::POST_TYPE) return null;

        $data = ['post_id' => $post_id, 'title' => $post->post_title];
        foreach (self::META_FIELDS as $key => $type) {
            $val = get_post_meta($post_id, $key, true);
            if ($type === 'object' || $type === 'array') {
                $data[$key] = $val ? json_decode($val, true) : ($type === 'array' ? [] : []);
            } else {
                $data[$key] = $val;
            }
        }
        return $data;
    }

    /**
     * 按 seed_id 查询 (向后兼容旧 option 存储)
     */
    public static function get_by_seed_id($seed_id)
    {
        $query = new \WP_Query([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'meta_key'       => 'seed_id',
            'meta_value'     => $seed_id,
        ]);
        if ($query->posts) return self::get($query->posts[0]->ID);

        // 向后兼容: 旧 option 存储
        $old = get_option(GenesisSeedDNA::SEED_OPTION, []);
        foreach ($old as $dna) {
            if (($dna['seed_id'] ?? '') === $seed_id) return $dna;
        }
        return null;
    }

    /**
     * 列出所有 Seed (v9.1.2 修复: 前端 ajax_genesis_seed_list 调用)
     *
     * 返回前端期望的格式: [{seed_id, name, category, seed_type, post_id}, ...]
     * 同时合并旧 option 存储 (向后兼容), 去重 by seed_id.
     *
     * @param int $limit 最多返回数量 (-1 = 全部)
     * @return array
     */
    public static function listAll(int $limit = 200): array
    {
        $out   = [];
        $seen  = []; // seed_id => true, 去重

        // 1) CPT 查询
        $args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_key'       => 'seed_id', // 确保有 seed_id 的才返回
        ];
        $query = new \WP_Query($args);
        foreach ($query->posts as $p) {
            $seed_id = get_post_meta($p->ID, 'seed_id', true);
            if (empty($seed_id) || isset($seen[$seed_id])) continue;
            $seen[$seed_id] = true;
            $out[] = [
                'seed_id'    => $seed_id,
                'name'       => $p->post_title,
                'category'   => get_post_meta($p->ID, 'seed_category', true),
                'seed_type'  => get_post_meta($p->ID, 'seed_type', true),
                'post_id'    => $p->ID,
                'source'     => 'cpt',
            ];
        }

        // 2) 向后兼容: 合并旧 option 存储 (GenesisSeedDNA::getAll())
        if (class_exists('\Linked3\Classes\Genesis\GenesisSeedDNA')) {
            try {
                $legacy = (array) \GenesisSeedDNA::getAll();
                foreach ($legacy as $dna) {
                    $sid = $dna['seed_id'] ?? '';
                    if (empty($sid) || isset($seen[$sid])) continue;
                    $seen[$sid] = true;
                    $out[] = [
                        'seed_id'   => $sid,
                        'name'      => $dna['name'] ?? $dna['title'] ?? $sid,
                        'category'  => $dna['seed_category'] ?? $dna['category'] ?? '',
                        'seed_type' => $dna['seed_type'] ?? 'fixed',
                        'post_id'   => 0,
                        'source'    => 'option',
                    ];
                }
            } catch (\Throwable $e) {
                // 旧存储读取失败不影响 CPT 数据返回
                if (function_exists('error_log')) {
                    error_log('[linked3] SeedDNA::getAll() legacy fallback failed: ' . $e->getMessage());
                }
            }
        }

        return $out;
    }

    /**
     * 软删除 (移至 trash)
     */
    public static function trash($post_id)
    {
        return wp_trash_post($post_id);
    }

    /**
     * 清空所有 Seed (软删除, 30天可恢复)
     */
    public static function trash_all()
    {
        $query = new \WP_Query([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $count = 0;
        foreach ($query->posts as $pid) {
            wp_trash_post($pid);
            $count++;
        }
        return $count;
    }
}
