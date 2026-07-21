<?php

declare(strict_types=1);
/**
 * Linked3 Genesis Recommendation AJAX Handler v1.1.0
 *
 * G7 Track F 推荐引擎的AJAX接口注册
 * 提供前端调用AI推荐引擎的REST端点
 *
 * 端点:
 *   wp_ajax_linked3_genesis_recommend         - 获取推荐
 *   wp_ajax_linked3_genesis_modes             - 获取所有推荐模式
 *   wp_ajax_linked3_genesis_styles_filtered   - 获取风格列表(支持视图过滤, v1.1修复重复注册)
 *   wp_ajax_linked3_genesis_engine            - 9引擎快捷调用
 *
 * v1.1.0 修复:
 *   - BUG: linked3_genesis_styles 与 Dashboard_Ajax_Registrar 重复注册, 视图过滤被覆盖失效
 *   - 修复: 改名为 linked3_genesis_styles_filtered, 消除冲突
 *   - 重构: 4类分类体系从"重叠字段过滤"改为"互斥用途分类"
 *
 * @package Linked3\Genesis
 * @since 16.0.27
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisRecommendationAjax
{
    public static function init(): void
    {
        add_action('wp_ajax_linked3_genesis_recommend', [__CLASS__, 'ajax_recommend']);
        add_action('wp_ajax_linked3_genesis_modes', [__CLASS__, 'ajax_modes']);
        // v1.1: 改名避免与 Dashboard_Ajax_Registrar::ajax_genesis_styles 冲突
        add_action('wp_ajax_linked3_genesis_styles_filtered', [__CLASS__, 'ajax_styles_filtered']);
        // G7: 9引擎快捷调用入口
        add_action('wp_ajax_linked3_genesis_engine', [__CLASS__, 'ajax_engine_call']);
    }

    /**
     * AJAX: 9引擎快捷调用
     * POST: engine (auto/beginner/designer/market/industry/accessible/conversion/complex/cross-platform), content, industry
     */
    public static function ajax_engine_call(): void
    {
        check_ajax_referer('linked3_content_writer', 'nonce');
        // v19.3.0: 补充 capability 校验（原仅校验 nonce，订阅者可消耗 AI 额度）
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => esc_html__('权限不足。', 'linked3')], 403);
        }

        $engine = sanitize_text_field($_POST['engine'] ?? 'auto');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $industry = sanitize_text_field($_POST['industry'] ?? 'general');

        if (empty($content)) {
            wp_send_json_error(['message' => esc_html__('请输入内容描述', 'linked3')]);
        }

        if (!class_exists('\Linked3\Classes\Genesis\Genesis9Engines')) {
            wp_send_json_error(['message' => esc_html__('9引擎类未加载', 'linked3')]);
        }

        $valid_engines = ['auto','beginner','designer','market','industry','accessible','conversion','complex','cross-platform'];
        if (!in_array($engine, $valid_engines, true)) {
            wp_send_json_error(['message' => esc_html__('无效引擎', 'linked3')]);
        }

        try {
            $method = $engine === 'cross-platform' ? 'crossPlatform' : $engine;
            if ($engine === 'industry') {
                $result = Genesis9Engines::$method($content, $industry);
            } elseif ($engine === 'market') {
                $result = Genesis9Engines::$method($content, $industry);
            } else {
                $result = Genesis9Engines::$method($content);
            }
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: 获取推荐
     * POST: content, mode, industry
     */
    public static function ajax_recommend(): void
    {
        check_ajax_referer('linked3_content_writer', 'nonce');
        // v19.3.0: 补充 capability 校验
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => esc_html__('权限不足。', 'linked3')], 403);
        }

        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $mode = sanitize_text_field($_POST['mode'] ?? 'auto');
        $industry = sanitize_text_field($_POST['industry'] ?? 'general');

        if (empty($content)) {
            wp_send_json_error(['message' => esc_html__('请输入内容描述', 'linked3')]);
        }

        if (!class_exists('\Linked3\Classes\Genesis\GenesisRecommendationEngine')) {
            wp_send_json_error(['message' => esc_html__('推荐引擎未加载', 'linked3')]);
        }

        try {
            $engine = new GenesisRecommendationEngine();
            $result = $engine->recommend($content, $mode, $industry);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: 获取所有推荐模式
     */
    public static function ajax_modes(): void
    {
        check_ajax_referer('linked3_content_writer', 'nonce');
        // v19.3.0: 补充 capability 校验
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => esc_html__('权限不足。', 'linked3')], 403);
        }

        if (!class_exists('\Linked3\Classes\Genesis\GenesisRecommendationEngine')) {
            wp_send_json_error(['message' => esc_html__('推荐引擎未加载', 'linked3')]);
        }

        $engine = new GenesisRecommendationEngine();
        wp_send_json_success(['modes' => $engine->getModes()]);
    }

    /**
     * AJAX: 获取风格列表(支持视图过滤) — v1.1 改名避免冲突
     * POST: view (all/infographic/illustration/photography/concept)
     *
     * v1.2 分类体系重构 (按usage_code编号前缀互斥分类):
     *   - infographic:  F01XX~F57XX 信息图示类 (57个) → 知识图谱/流程图/脑图/数据图
     *   - illustration: Y01XX~Y05XX 艺术插画类 (5个)  → 漫画/绘本/水墨/水彩/和风
     *   - photography:  S01XX~S06XX 商业摄影类 (6个)  → 真人写真/古风/影视/时尚/暗黑/纪实
     *   - concept:      G01XX~G03XX 概念实验类 (3个)  → 赛博朋克/哥特/蒸汽朋克
     *
     * v1.1→v1.2 修复:
     *   - 旧版用category字段模糊匹配, 导致4类结果重叠或失衡
     *   - 新版用usage_code前缀(F/Y/S/G)精确匹配, 互斥不重叠
     */
    public static function ajax_styles_filtered(): void
    {
        check_ajax_referer('linked3_content_writer', 'nonce');
        // v19.3.0: 补充 capability 校验
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => esc_html__('权限不足。', 'linked3')], 403);
        }

        $view = sanitize_text_field($_POST['view'] ?? 'all');

        if (!class_exists('\Linked3\Classes\Genesis\GenesisStyleEngine')) {
            wp_send_json_error(['message' => esc_html__('风格引擎未加载', 'linked3')]);
        }

        $all_styles = GenesisStyleEngine::getAllStyles();
        $filtered = self::filterByView($all_styles, $view);

        $result = [];
        foreach ($filtered as $sid => $sinfo) {
            $usage_code = $sinfo['usage_code'] ?? '';
            $label = $sinfo['name_cn'] ?? ($sinfo['name_en'] ?? $sid);
            // v1.2: 标签前缀显示编号 (如 [F01] 建筑信息图)
            if ($usage_code) {
                $label = '[' . $usage_code . '] ' . $label;
            }
            if (!empty($sinfo['category'])) {
                $label .= ' [' . $sinfo['category'] . ']';
            }
            $result[$sid] = [
                'label' => $label,
                'usage_code' => $usage_code,
                'usage_type' => self::classifyUsageType($usage_code),
                'category' => $sinfo['category'] ?? '',
                'wondershare_ready' => $sinfo['wondershare_ready'] ?? false,
                'industry' => $sinfo['industry'] ?? '',
                'g7_track' => $sinfo['g7_track'] ?? '',
            ];
        }

        wp_send_json_success([
            'styles' => $result,
            'count' => count($result),
            'view' => $view,
        ]);
    }

    /**
     * v1.2: 互斥用途分类 — 基于 usage_code 前缀 (F/Y/S/G) 精确判定
     * 返回值: infographic / illustration / photography / concept / other
     *
     * @param string $usage_code 风格编号 (如 F01, Y02, S03, G01)
     */
    public static function classifyUsageType(string $usage_code): string
    {
        if ($usage_code === '') {
            return 'other';
        }
        $prefix = strtoupper($usage_code[0]);
        $map = [
            'F' => 'infographic',   // 信息图示
            'Y' => 'illustration',  // 艺术插画
            'S' => 'photography',   // 商业摄影
            'G' => 'concept',       // 概念实验
        ];
        return $map[$prefix] ?? 'other';
    }

    /**
     * v1.2: 视图过滤 — 基于 usage_code 前缀, 互斥不重叠
     *
     * 映射关系:
     *   view=infographic  → usage_code 以 F 开头 (57个)
     *   view=illustration → usage_code 以 Y 开头 (5个)
     *   view=photography  → usage_code 以 S 开头 (6个)
     *   view=concept      → usage_code 以 G 开头 (3个)
     *   view=all          → 全部 (71个)
     */
    private static function filterByView(array $styles, string $view): array
    {
        if ($view === 'all') {
            return $styles;
        }

        // view → usage_code前缀 映射
        $view_prefix_map = [
            'infographic'  => 'F',
            'illustration' => 'Y',
            'photography'  => 'S',
            'concept'      => 'G',
        ];

        if (!isset($view_prefix_map[$view])) {
            return $styles; // 未知view, 返回全部
        }

        $target_prefix = $view_prefix_map[$view];
        $filtered = [];
        foreach ($styles as $sid => $sinfo) {
            $usage_code = $sinfo['usage_code'] ?? '';
            if ($usage_code !== '' && strtoupper($usage_code[0]) === $target_prefix) {
                $filtered[$sid] = $sinfo;
            }
        }

        return $filtered;
    }
}

// G7 AJAX注册: 通过init钩子延迟注册，确保WordPress的add_action就绪
// 既有模式: Hooks_Registrar在init钩子中注册AJAX，此处遵循同样时机
add_action('init', [GenesisRecommendationAjax::class, 'init'], 20);
