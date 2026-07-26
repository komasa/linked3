<?php

declare(strict_types=1);
/**
 * EcosystemKeywordTrait — Keyword generation + library + tail-used + hot-collect.
 *
 * @package Linked3\Content
 */

namespace Linked3\Classes\Content;

if (!defined('ABSPATH')) exit;

trait EcosystemKeywordTrait
{
    /**
     * 关键词生成
     */
    public static function ajax_keywords() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        // v27.8.5-fix: 增加超时到120s (multi模式多次AI调用需要更多时间)
        @set_time_limit(120);
        @ini_set('max_execution_time', '120');

        $seed = sanitize_text_field($_POST['seed'] ?? '');
        $count = intval($_POST['count'] ?? 20);
        $multi_seeds_raw = isset($_POST['seeds']) ? wp_unslash($_POST['seeds']) : '';
        $mode = sanitize_key($_POST['mode'] ?? 'single');

        if (empty($seed) && empty($multi_seeds_raw)) {
            wp_send_json_error(['message' => __('请输入种子词或选择热词库', 'linked3')]);
        }

        try {
            [$all_keywords, $all_long_tail, $seed_count] = self::generate_keywords_by_mode(
                $mode, $seed, $multi_seeds_raw, $count
            );

            $classified = EcosystemKeywordService::classify_keywords($all_keywords);

            wp_send_json_success([
                'keywords' => $all_keywords,
                'classified' => $classified,
                'long_tail' => $all_long_tail,
                'mode' => $mode,
                'seed_count' => $seed_count,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => __('关键词生成失败: ', 'linked3') . $e->getMessage()]);
        }
    }

    /**
     * 根据模式生成关键词 (单种子/多种子)
     * @return array{0:array,1:array,2:int} [keywords, long_tail, seed_count]
     */
    private static function generate_keywords_by_mode(string $mode, string $seed, string $multi_seeds_raw, int $count): array {
        $all_keywords = [];
        $all_long_tail = [];

        if ($mode === 'multi' && !empty($multi_seeds_raw)) {
            $seeds = array_filter(array_map('trim', explode("\n", $multi_seeds_raw)));
            $per_seed_count = max(3, intval($count / max(1, count($seeds))));
            foreach ($seeds as $s) {
                $s = sanitize_text_field($s);
                if (empty($s)) continue;
                $kw = EcosystemKeywordService::generate_keywords($s, $per_seed_count);
                foreach ($kw as $k) {
                    if (!in_array($k, $all_keywords)) {
                        $all_keywords[] = $k;
                        if (mb_strlen($k) > 8) $all_long_tail[] = $k;
                    }
                }
                if (count($all_keywords) >= $count) break;
            }
            $all_keywords = array_slice($all_keywords, 0, $count);
            return [$all_keywords, $all_long_tail, count($seeds)];
        }

        // 单种子词模式 (兼容原逻辑)
        $all_keywords = EcosystemKeywordService::generate_keywords($seed, $count);
        $all_long_tail = array_filter($all_keywords, fn($k) => mb_strlen($k) > 8);
        return [$all_keywords, $all_long_tail, 1];
    }

    /**
     * v10.7.1 热词采集 — 多源采集 (百度/搜狗/360/知乎/微博/抖音)
     */
    public static function ajax_hot_collect() : void { EcosystemAjaxAdvanced::ajax_hot_collect(); }

    /**
     * v10.7.1 关键词库保存 (热词库/长尾词库)
     */
    public static function ajax_keywords_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $type = sanitize_key($_POST['type'] ?? 'hot');
        $keywords_raw = wp_unslash($_POST['keywords'] ?? '');
        $keywords = array_filter(array_map('sanitize_text_field', explode("\n", $keywords_raw)));
        $keywords = array_slice($keywords, 0, 500);

        $option_key = LINKED3_OPTION_PREFIX . ($type === 'tail' ? 'tail_keywords' : 'hot_keywords');
        update_option($option_key, $keywords, false);

        wp_send_json_success([
            'type' => $type,
            'count' => count($keywords),
            'message' => ($type === 'tail' ? '长尾词库' : '热词库') . '已保存: ' . count($keywords) . '个',
        ]);
    }

    /**
     * v16.0.14 [公理α: H↓ 消除"用过没"不确定性] [公理β: dim↓ 0维自动持久化]
     * 长尾词使用状态保存 — 记录哪些长尾词已用于生成文章
     */
    public static function ajax_tail_used_save() : void {
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('无权限', 'linked3')], 403);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'linked3_content_writer')) wp_send_json_error(['message' => __('安全校验失败', 'linked3')], 403);

        $used_map_raw = wp_unslash($_POST['used_map'] ?? '{}');
        $used_map = json_decode($used_map_raw, true);
        if (!is_array($used_map)) {
            $used_map = [];
        }

        // 清洗: 只保留 keyword => 1 的键值对, 键名 sanitize
        $clean = [];
        foreach ($used_map as $kw => $val) {
            $kw_clean = sanitize_text_field($kw);
            if ($kw_clean !== '') {
                $clean[$kw_clean] = 1;
            }
        }
        // 限流: 最多 2000 条, 防止 option 膨胀
        if (count($clean) > 2000) {
            $clean = array_slice($clean, -2000, null, true);
        }

        update_option(LINKED3_OPTION_PREFIX . 'tail_keywords_used', $clean, false);

        wp_send_json_success([
            'count' => count($clean),
            'message' => __('使用状态已保存: ', 'linked3') . count($clean) . '个已用',
        ]);
    }
}
