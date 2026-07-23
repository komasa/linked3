<?php

declare(strict_types=1);
/**
 * Scene Axis System v8.2.0 — M4 场景三轴 + 骨架路由
 *
 * 公理4·三轴正交: 任意分镜 = L1 通用场景 × L2 垂直专栏 × L3 灵魂风格
 *   - L1: 44 通用场景 (V14 体系, 7 大类) — 解决"画什么"
 *   - L2: 8 垂直商业专栏 — 解决"卖什么" (治愈/幽默/花色/悬疑/萌宠/国潮/赛博/纪实)
 *   - L3: 12 灵魂风格 — 解决"像谁画的" (宫崎骏/大友克洋/莫奈/张择端/梵高/葛饰北斋/...)
 *
 * route_skeleton(L1, L2, L3) → skeleton_id
 *   - 优先调用 SkeletonLibrary::route() (v8.1.0 引用但尚未实现, 此处防御性 class_exists)
 *   - 兜底: 用 L1+L2 简单查表
 *
 * @package Linked3\Genesis
 * @since 8.2.0
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class SceneAxis
{
    /**
     * 3.5 L1: 44 通用场景 (V14 体系, 7 大类)
     *
     * @return array 7 大类, 每类 N 个场景, 总计 44
     *               {category_id, category_name, scenes: [{id, name, name_en, prompt_keywords}]}
     */
        public static function get_l1_types() : mixed { return SceneAxisUtils::get_l1_types(); }

    /**
     * 3.5 L2: 8 垂直商业专栏
     *
     * 每个: {id, name, meta_signature, prompt_skeleton_id, color_system}
     *   - meta_signature: 专栏签名指纹 (注入 Prompt L1 META 层的 signature 字段)
     *   - prompt_skeleton_id: 推荐骨架 ID (供 Skeleton_Library 兜底路由)
     *   - color_system: 专栏默认色彩体系 [{role, hex}]
     *
     * @return array
     */
        public static function get_l2_columns() : mixed { return SceneAxisUtils::get_l2_columns(); }

    /**
     * 3.5 L3: 12 灵魂风格 (艺术家签名画风)
     *
     * 每个: {id, name, description, preview_image, applicable_scenes:[], disclaimer}
     *
     * @return array
     */
        public static function get_l3_souls() : mixed { return SceneAxisUtils::get_l3_souls(); }

    /**
     * 3.6 骨架路由 (S13) — 三轴选择 → 自动路由骨架
     *
     * 路由优先级:
     *   1. 调用 SkeletonLibrary::route(l1, l2, l3) (若存在)
     *   2. L3 → L2 → L1 兜底查表
     *
     * @param string $l1 L1 场景 ID (street_cafe / mountain_mist / ...)
     * @param string $l2 L2 专栏 ID (healing / humor / ...)
     * @param string $l3 L3 灵魂 ID (miyazaki_watercolor / ...)
     * @return string skeleton_id (与 Genesis/styles/_index.json 风格 ID 对齐)
     */
        public static function route_skeleton(string $l1, string $l2, string $l3) : mixed { return SceneAxisUtils::route_skeleton($l1, $l2, $l3); }

    /**
     * 三轴快速预览 (供 UI 渲染选择器)
     *
     * @return array {l1: {categories:[]}, l2: [], l3: []}
     */
    public static function get_all_axes(): array
    {
        return [
            'l1' => self::get_l1_types(),
            'l2' => self::get_l2_columns(),
            'l3' => self::get_l3_souls(),
        ];
    }

    /**
     * AJAX: 获取三轴选项 (一次性返回 L1+L2+L3 全量)
     *
     * POST: nonce
     * Return: {l1, l2, l3}
     */
        public static function ajax_get_axes() : mixed { return SceneDetector::ajax_get_axes(); }

    /**
     * AJAX: 三轴 → 骨架路由
     *
     * POST: l1, l2, l3, nonce
     * Return: {skeleton_id, l1, l2, l3}
     */
    static function ajax_route_skeleton(): void {
        check_ajax_referer('linked3_scene_axis', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('权限不足, 需要 edit_posts 能力。', 'linked3')], 403);
        }

        $l1 = isset($_POST['l1']) ? sanitize_key(wp_unslash($_POST['l1'])) : '';
        $l2 = isset($_POST['l2']) ? sanitize_key(wp_unslash($_POST['l2'])) : '';
        $l3 = isset($_POST['l3']) ? sanitize_key(wp_unslash($_POST['l3'])) : '';

        if ($l1 === '' && $l2 === '' && $l3 === '') {
            wp_send_json_error(['message' => __('至少需要提供 L1/L2/L3 之一。', 'linked3')], 400);
        }

        $skeleton_id = self::route_skeleton($l1, $l2, $l3);

        wp_send_json_success([
            'skeleton_id' => $skeleton_id,
            'l1'          => $l1,
            'l2'          => $l2,
            'l3'          => $l3,
        ]);
    }

    /**
     * v9.1.3: 从剧本文本自动推断 L1/L2/L3 三轴
     *
     * 基于关键词匹配, 分析剧本内容推断最合适的场景类型、垂直专栏和灵魂风格。
     * 如果无法确定, 回落到默认值 (city_life / documentary / none)。
     *
     * @param string $script 剧本文本
     * @return array {l1, l2, l3}
     */
    public static function auto_detect_from_script(string $script): array
    {
        $text = mb_strtolower($script);
        $scores = ['l1' => [], 'l2' => [], 'l3' => []];

        // L1 关键词映射
        $l1_keywords = [
            'city_life' => ['城市', '街头', '咖啡', '地铁', ' office', '办公室', '商场', '街道', '都市', '写字楼', '餐厅', '酒吧', '夜店', 'urban', 'city', 'street'],
            'nature'    => ['山', '森林', '海', '湖', '河流', '草原', '沙漠', '田野', '乡村', '花园', '自然', '野外', 'mountain', 'forest', 'ocean', 'nature'],
            'indoor'    => ['室内', '房间', '客厅', '卧室', '厨房', '地下室', '阁楼', '走廊', '室内', 'indoor', 'room', 'house'],
            'business'  => ['商业', '公司', '会议', '谈判', '办公室', '工厂', '车间', '仓库', '商店', '市场', 'business', 'office', 'factory'],
            'culture'   => ['博物馆', '美术馆', '图书馆', '剧院', '音乐厅', '文化', '艺术', '展览', '古董', 'museum', 'gallery', 'art', 'culture'],
            'outdoor'   => ['公园', '广场', '体育场', '校园', '游乐场', '户外', '野外', '旅行', 'park', 'square', 'stadium', 'outdoor'],
            'industry'  => ['工厂', '工业', '机械', '车间', '生产线', '仓库', '码头', '工地', 'factory', 'industrial', 'machine'],
        ];

        // L2 关键词映射
        $l2_keywords = [
            'documentary' => ['纪实', '真实', '记录', '新闻', '报道', 'documentary', 'real', 'news'],
            'healing'     => ['治愈', '温暖', '温馨', '日常', '生活', '陪伴', '关怀', 'healing', 'warm', 'cozy'],
            'humor'       => ['搞笑', '幽默', '喜剧', '荒诞', '讽刺', 'funny', 'humor', 'comedy'],
            'floral'      => ['花', '花卉', '植物', '园艺', '自然', 'floral', 'flower', 'plant'],
            'suspense'    => ['悬疑', '恐怖', '惊悚', '黑暗', '阴谋', '犯罪', 'suspense', 'horror', 'thriller', 'dark'],
            'pet'         => ['猫', '狗', '宠物', '动物', '萌', 'pet', 'cat', 'dog', 'animal', 'cute'],
            'guochao'     => ['国潮', '中国', '传统', '古风', '水墨', '汉服', '东方', 'chinese', 'traditional', 'oriental'],
            'cyber'       => ['赛博', '未来', '科技', '机械', 'AI', '机器人', '虚拟', 'cyber', 'future', 'tech', 'robot'],
        ];

        // L3 关键词映射
        $l3_keywords = [
            'miyazaki'      => ['宫崎骏', '水彩', '童话', '少女', '飞行', '自然', '温柔', 'miyazaki', 'ghibli'],
            'otomo'         => ['大友克洋', '线稿', '赛博', '都市', '机械', 'otomo', 'akira'],
            'monet'         => ['莫奈', '印象', '光斑', '花园', '睡莲', 'monet', 'impressionist'],
            'zhangzeduan'   => ['张择端', '工笔', '清明上河', '宋代', '卷轴', 'gongbi'],
            'vangogh'       => ['梵高', '星空', '向日葵', '笔触', '浓烈', 'van gogh', 'expressionist'],
            'hokusai'       => ['葛饰北斋', '浮世绘', '浪', '日本', '木版', 'hokusai', 'ukiyoe'],
            'ando'          => ['安藤忠雄', '极简', '混凝土', '光影', '建筑', 'ando', 'minimal'],
            'kusama'        => ['草间弥生', '波点', '南瓜', '无限', 'kusama', 'polka'],
            'mucha'         => ['慕夏', '繁花', '装饰', '新艺术', 'mucha', 'art nouveau'],
            'hokusai_wave'  => ['神奈川', '冲浪', '巨浪', 'wave', 'tsunami'],
            'klimt'         => ['克里姆特', '金色', '吻', '装饰', 'klimt', 'gold'],
            'banksy'        => ['banksy', '涂鸦', '反讽', '街头艺术', 'graffiti', 'street art'],
        ];

        // 计算各轴得分
        foreach ($l1_keywords as $key => $words) {
            $scores['l1'][$key] = 0;
            foreach ($words as $w) {
                $count = substr_count($text, mb_strtolower($w));
                $scores['l1'][$key] += $count;
            }
        }
        foreach ($l2_keywords as $key => $words) {
            $scores['l2'][$key] = 0;
            foreach ($words as $w) {
                $count = substr_count($text, mb_strtolower($w));
                $scores['l2'][$key] += $count;
            }
        }
        foreach ($l3_keywords as $key => $words) {
            $scores['l3'][$key] = 0;
            foreach ($words as $w) {
                $count = substr_count($text, mb_strtolower($w));
                $scores['l3'][$key] += $count;
            }
        }

        // 取最高分 (无匹配则用默认值)
        $l1 = !empty($scores['l1']) && max($scores['l1']) > 0
            ? array_keys($scores['l1'], max($scores['l1']))[0]
            : 'city_life';
        $l2 = !empty($scores['l2']) && max($scores['l2']) > 0
            ? array_keys($scores['l2'], max($scores['l2']))[0]
            : 'documentary';
        $l3 = !empty($scores['l3']) && max($scores['l3']) > 0
            ? array_keys($scores['l3'], max($scores['l3']))[0]
            : 'none';

        return ['l1' => $l1, 'l2' => $l2, 'l3' => $l3];
    }
}
