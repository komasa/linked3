<?php

declare(strict_types=1);
/**
 * Linked3 SEED 统一管理层 v10.0
 *
 * 公理2实现: SEED的"不可变/可变"二分是降维核心
 * 统一三套SEED存储: lib/JSON(模板) → CPT(运行时实例), option仅留读取兼容
 *
 * SEED分类 (6类):
 *   character  角色  — 人物外貌/性格/服装
 *   scene      场景  — 地点/环境/氛围
 *   prop       道具  — 关键物品/武器/信物
 *   style      风格  — 画风/色调/笔触
 *   brand      品牌  — IP标识/水印/字体
 *   palette    色板  — 色彩方案/情绪色调
 *
 * SEED类型 (2类):
 *   fixed      不可变 — 如人物五官/体型/性格 (lock=true)
 *   variable   可变  — 如每天不同的衣服/天气/光照 (lock=false)
 *
 * @package Linked3
 * @subpackage Classes\Genesis
 * @version 10.0.0
 * @date 2026-06-23
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class SeedUnified {

    /** @var string CPT slug */
    const CPT = 'linked3_seed';

    /** @var array SEED分类定义 */
    public static $categories = [
        'character' => ['icon' => '👤', 'label' => '角色', 'desc' => '人物外貌/性格/服装', 'color' => '#3b82f6'],
        'scene'     => ['icon' => '🏞️', 'label' => '场景', 'desc' => '地点/环境/氛围', 'color' => '#10b981'],
        'prop'      => ['icon' => '⚔️', 'label' => '道具', 'desc' => '关键物品/武器/信物', 'color' => '#f59e0b'],
        'style'     => ['icon' => '🎨', 'label' => '风格', 'desc' => '画风/色调/笔触', 'color' => '#8b5cf6'],
        'brand'     => ['icon' => '🏷️', 'label' => '品牌', 'desc' => 'IP标识/水印/字体', 'color' => '#ec4899'],
        'palette'   => ['icon' => '🌈', 'label' => '色板', 'desc' => '色彩方案/情绪色调', 'color' => '#06b6d4'],
    ];

    /** @var array SEED类型定义 */
    public static $types = [
        'fixed'    => ['icon' => '🔒', 'label' => '不可变', 'desc' => '人物五官/体型/性格等恒定属性'],
        'variable' => ['icon' => '🔄', 'label' => '可变', 'desc' => '服装/天气/光照等可变属性'],
    ];

    /**
     * 获取所有SEED (统一入口, 优先CPT, 降级option, 降级lib/JSON)
     *
     * @param array $args 查询参数
     * @return array SEED列表
     */
    public static function get_all(array $args = []) : mixed {
        $defaults = [
            'category'   => '',      // 按分类过滤
            'type'       => '',      // 按类型过滤 (fixed/variable)
            'limit'      => 100,
            'orderby'    => 'date',
            'order'      => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);

        // 优先从CPT获取
        if (post_type_exists(self::CPT)) {
            $query_args = [
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => $args['limit'],
                'orderby'        => $args['orderby'],
                'order'          => $args['order'],
            ];
            if ($args['category']) {
                $query_args['tax_query'] = [[
                    'taxonomy' => 'linked3_seed_cat',
                    'field'    => 'slug',
                    'terms'    => $args['category'],
                ]];
            }
            $posts = get_posts($query_args);
            $seeds = [];
            foreach ($posts as $post) {
                $seeds[] = self::format_cpt_seed($post);
            }
            return $seeds;
        }

        // 降级: 从option获取 (v8.0兼容)
        $option_seeds = get_option('linked3_seed_dna_list', []);
        if (!empty($option_seeds)) {
            return array_map([__CLASS__, 'format_option_seed'], $option_seeds);
        }

        // 最终降级: 从lib/JSON获取 (v7模板)
        return self::get_lib_seeds($args['category']);
    }

    /**
     * 格式化CPT SEED为统一结构
     */
    private static function format_cpt_seed($post) : array {
        $seed_type = get_post_meta($post->ID, '_seed_type', true) ?: 'fixed';
        $lock      = get_post_meta($post->ID, '_lock', true);
        $lock      = ($lock === '' || $lock === null) ? ($seed_type === 'fixed') : (bool)$lock;
        $category  = get_post_meta($post->ID, '_seed_category', true) ?: 'character';
        $priority  = get_post_meta($post->ID, '_priority', true) ?: 50;
        $visual_dna= get_post_meta($post->ID, '_visual_dna', true);
        $ai_adapter= get_post_meta($post->ID, '_ai_adapter', true);
        $parent    = get_post_meta($post->ID, '_parent_seed', true);

        return [
            'seed_id'      => 'seed_' . $post->ID,
            'post_id'      => $post->ID,
            'name'         => $post->post_title,
            'description'  => $post->post_content,
            'category'     => $category,
            'seed_type'    => $seed_type,
            'lock'         => $lock,
            'priority'     => intval($priority),
            'visual_dna'   => $visual_dna ? json_decode($visual_dna, true) : null,
            'ai_adapter'   => $ai_adapter ? json_decode($ai_adapter, true) : null,
            'parent_seed'  => $parent,
            'created_at'   => $post->post_date,
            'modified_at'  => $post->post_modified,
            'source'       => 'cpt',
        ];
    }

    /**
     * 格式化option SEED (v8.0兼容)
     */
    private static function format_option_seed($raw) : array {
        return [
            'seed_id'      => $raw['seed_id'] ?? ('opt_' . md5($raw['name'] ?? '')),
            'post_id'      => 0,
            'name'         => $raw['name'] ?? '未命名',
            'description'  => $raw['description'] ?? '',
            'category'     => $raw['category'] ?? 'character',
            'seed_type'    => $raw['seed_type'] ?? 'fixed',
            'lock'         => $raw['lock'] ?? true,
            'priority'     => $raw['priority'] ?? 50,
            'visual_dna'   => $raw['visual_dna'] ?? null,
            'ai_adapter'   => $raw['ai_adapter'] ?? null,
            'parent_seed'  => $raw['parent_seed'] ?? '',
            'created_at'   => $raw['created_at'] ?? current_time('mysql'),
            'modified_at'  => $raw['modified_at'] ?? current_time('mysql'),
            'source'       => 'option',
        ];
    }

    /**
     * 从lib/seeds/ JSON获取模板SEED (v7兼容)
     */
    public static function get_lib_seeds($category = '') : mixed {
        $lib_dir = dirname(__FILE__) . '/lib/seeds/';
        $seeds = [];

        $dirs = [
            'character' => 'characters/',
            'scene'     => 'scenes/',
            'style'     => 'styles/',
        ];

        foreach ($dirs as $cat => $subdir) {
            if ($category && $category !== $cat) continue;
            $path = $lib_dir . $subdir;
            if (!is_dir($path)) continue;
            $files = glob($path . '*.json');
            foreach ($files as $file) {
                $json = json_decode(file_get_contents($file), true);
                if (!$json) continue;
                $seeds[] = [
                    'seed_id'      => 'lib_' . basename($file, '.json'),
                    'post_id'      => 0,
                    'name'         => $json['name'] ?? basename($file, '.json'),
                    'description'  => $json['description'] ?? '',
                    'category'     => $cat,
                    'seed_type'    => 'fixed',
                    'lock'         => true,
                    'priority'     => $json['priority'] ?? 50,
                    'visual_dna'   => $json,
                    'ai_adapter'   => null,
                    'parent_seed'  => '',
                    'created_at'   => date('Y-m-d H:i:s', filemtime($file)),
                    'modified_at'  => date('Y-m-d H:i:s', filemtime($file)),
                    'source'       => 'lib',
                ];
            }
        }

        return $seeds;
    }

    /**
     * 从lib模板批量导入到CPT
     *
     * @return array ['count' => int, 'errors' => array]
     */
    public static function import_from_templates() : array {
        if (!post_type_exists(self::CPT)) {
            return ['count' => 0, 'errors' => ['CPT not registered']];
        }

        $lib_seeds = self::get_lib_seeds();
        $count = 0;
        $errors = [];

        foreach ($lib_seeds as $seed) {
            // 检查是否已导入 (按名称去重)
            $existing = get_posts([
                'post_type'   => self::CPT,
                'post_status' => 'publish',
                'title'       => $seed['name'],
                'numberposts' => 1,
            ]);

            if (!empty($existing)) continue; // 跳过已存在

            $post_id = wp_insert_post([
                'post_type'    => self::CPT,
                'post_status'  => 'publish',
                'post_title'   => $seed['name'],
                'post_content' => $seed['description'],
            ]);

            if (is_wp_error($post_id)) {
                $errors[] = $seed['name'] . ': ' . $post_id->get_error_message();
                continue;
            }

            update_post_meta($post_id, '_seed_category', $seed['category']);
            update_post_meta($post_id, '_seed_type', $seed['seed_type']);
            update_post_meta($post_id, '_lock', $seed['lock']);
            update_post_meta($post_id, '_priority', $seed['priority']);
            update_post_meta($post_id, '_visual_dna', json_encode($seed['visual_dna']));
            update_post_meta($post_id, '_parent_seed', 'lib:' . $seed['seed_id']);

            $count++;
        }

        return ['count' => $count, 'errors' => $errors];
    }

    /**
     * 创建SEED
     *
     * @param array $data SEED数据
     * @return int|WP_Error
     */
    public static function create(array $data) : mixed {
        if (!post_type_exists(self::CPT)) {
            return new WP_Error('cpt_missing', 'SEED CPT未注册');
        }

        $defaults = [
            'name'        => '',
            'description' => '',
            'category'    => 'character',
            'seed_type'   => 'fixed',
            'lock'        => true,
            'priority'    => 50,
            'visual_dna'  => null,
            'ai_adapter'  => null,
            'parent_seed' => '',
        ];
        $data = wp_parse_args($data, $defaults);

        if (empty($data['name'])) {
            return new WP_Error('empty_name', 'SEED名称不能为空');
        }

        $post_id = wp_insert_post([
            'post_type'    => self::CPT,
            'post_status'  => 'publish',
            'post_title'   => $data['name'],
            'post_content' => $data['description'],
        ]);

        if (is_wp_error($post_id)) return $post_id;

        update_post_meta($post_id, '_seed_category', $data['category']);
        update_post_meta($post_id, '_seed_type', $data['seed_type']);
        update_post_meta($post_id, '_lock', (bool)$data['lock']);
        update_post_meta($post_id, '_priority', intval($data['priority']));
        if ($data['visual_dna']) {
            update_post_meta($post_id, '_visual_dna', is_string($data['visual_dna']) ? $data['visual_dna'] : json_encode($data['visual_dna']));
        }
        if ($data['ai_adapter']) {
            update_post_meta($post_id, '_ai_adapter', is_string($data['ai_adapter']) ? $data['ai_adapter'] : json_encode($data['ai_adapter']));
        }
        update_post_meta($post_id, '_parent_seed', $data['parent_seed']);

        return $post_id;
    }

    /**
     * 更新SEED
     */
    public static function update($seed_id, $data) : mixed {
        $post_id = self::extract_post_id($seed_id);
        if (!$post_id) return new WP_Error('invalid_id', '无效的SEED ID');

        $update_args = [];
        if (isset($data['name'])) $update_args['post_title'] = $data['name'];
        if (isset($data['description'])) $update_args['post_content'] = $data['description'];

        if (!empty($update_args)) {
            $update_args['ID'] = $post_id;
            wp_update_post($update_args);
        }

        if (isset($data['category'])) update_post_meta($post_id, '_seed_category', $data['category']);
        if (isset($data['seed_type'])) update_post_meta($post_id, '_seed_type', $data['seed_type']);
        if (isset($data['lock'])) update_post_meta($post_id, '_lock', (bool)$data['lock']);
        if (isset($data['priority'])) update_post_meta($post_id, '_priority', intval($data['priority']));
        if (isset($data['visual_dna'])) update_post_meta($post_id, '_visual_dna', is_string($data['visual_dna']) ? $data['visual_dna'] : json_encode($data['visual_dna']));
        if (isset($data['ai_adapter'])) update_post_meta($post_id, '_ai_adapter', is_string($data['ai_adapter']) ? $data['ai_adapter'] : json_encode($data['ai_adapter']));

        return true;
    }

    /**
     * 删除SEED
     */
    public static function delete($seed_id) : mixed {
        $post_id = self::extract_post_id($seed_id);
        if (!$post_id) return false;
        return wp_delete_post($post_id, true) !== false;
    }

    /**
     * 获取单个SEED
     */
    public static function get($seed_id) : mixed {
        $post_id = self::extract_post_id($seed_id);
        if (!$post_id) return null;
        $post = get_post($post_id);
        if (!$post) return null;
        return self::format_cpt_seed($post);
    }

    /**
     * 从seed_id提取post_id
     */
    private static function extract_post_id($seed_id) : mixed {
        if (is_numeric($seed_id)) return intval($seed_id);
        if (strpos($seed_id, 'seed_') === 0) return intval(substr($seed_id, 5));
        return 0;
    }

    /**
     * 获取SEED分类统计
     */
    public static function get_stats() : mixed {
        $all = self::get_all(['limit' => 9999]);
        $stats = [
            'total' => count($all),
            'by_category' => [],
            'by_type' => ['fixed' => 0, 'variable' => 0],
            'by_source' => ['cpt' => 0, 'option' => 0, 'lib' => 0],
        ];

        foreach (array_keys(self::$categories) as $cat) {
            $stats['by_category'][$cat] = 0;
        }

        foreach ($all as $seed) {
            $cat = $seed['category'] ?? 'character';
            if (!isset($stats['by_category'][$cat])) $stats['by_category'][$cat] = 0;
            $stats['by_category'][$cat]++;

            $type = $seed['seed_type'] ?? 'fixed';
            if (!isset($stats['by_type'][$type])) $stats['by_type'][$type] = 0;
            $stats['by_type'][$type]++;

            $src = $seed['source'] ?? 'cpt';
            if (!isset($stats['by_source'][$src])) $stats['by_source'][$src] = 0;
            $stats['by_source'][$src]++;
        }

        return $stats;
    }

    /**
     * 注册AJAX接口 (v10.0新增: 模板导入)
     */
    public static function register_ajax() : void {
        add_action('wp_ajax_linked3_genesis_seed_import_templates', [__CLASS__, 'ajax_import_templates']);
        add_action('wp_ajax_linked3_genesis_seed_create', [__CLASS__, 'ajax_create']);
        add_action('wp_ajax_linked3_genesis_seed_update', [__CLASS__, 'ajax_update']);
        add_action('wp_ajax_linked3_genesis_seed_stats', [__CLASS__, 'ajax_stats']);
    }

    public static function ajax_import_templates() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('权限不足', 'linked3-ai')]);

        $result = self::import_from_templates();
        wp_send_json_success($result);
    }

    public static function ajax_create() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('权限不足', 'linked3-ai')]);

        $data = [
            'name'        => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'category'    => sanitize_text_field($_POST['category'] ?? 'character'),
            'seed_type'   => sanitize_text_field($_POST['seed_type'] ?? 'fixed'),
            'lock'        => $_POST['lock'] ?? true,
            'priority'    => intval($_POST['priority'] ?? 50),
            'visual_dna'  => $_POST['visual_dna'] ?? null,
        ];

        $result = self::create($data);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['seed_id' => 'seed_' . $result, 'post_id' => $result]);
    }

    public static function ajax_update() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('权限不足', 'linked3-ai')]);

        $seed_id = sanitize_text_field($_POST['seed_id'] ?? '');
        $result = self::update($seed_id, $_POST);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['ok' => true]);
    }

    public static function ajax_stats() : void {
        check_ajax_referer('linked3_content_writer', 'nonce');
        // v19.3.0: 补充 capability 校验
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => esc_html__('权限不足。', 'linked3')], 403);
        }
        wp_send_json_success(self::get_stats());
    }
}

// 注册AJAX
if (class_exists('\Linked3\Classes\Genesis\SeedUnified')) {
    add_action('init', ['\Linked3\Classes\Genesis\SeedUnified', 'register_ajax']);
}
