<?php

declare(strict_types=1);
/**
 * Linked3 Skeleton Library v8.1.0 — M2.2 场景骨架库
 *
 * 12 套预置骨架 + 用户自定义骨架 (WP option 持久化)
 *
 * 设计原则:
 *   - en_template 全英文, 占位符统一: {subject}{action}{location}{mood}{camera}{ratio}{stylize}{color}{signature}{negative}
 *   - {negative} 占位由 negative_default 自动替换 (用户可在 option 中覆盖)
 *   - 预置骨架不可被覆盖; 用户骨架通过 linked3_prompt_skeletons option 追加/覆盖同 ID
 *   - 路由: 按 L1 类型 / L2 栏目 / L3 灵魂三维度路由到具体骨架 ID
 *
 * @package Linked3\Genesis
 * @since 8.1.0
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class SkeletonLibrary
{
    /** @var array|null 骨架缓存 (preset + custom 合并后) */
    private static ?array $cache = null;

    /** @var string 预置骨架文件路径 */
    private const PRESET_FILE = __DIR__ . '/skeletons/preset_skeletons.json';

    /** @var string 用户自定义骨架的 WP option 键 */
    private const CUSTOM_OPTION = 'linked3_prompt_skeletons';

    /** @var string 默认骨架 ID (兜底) */
    private const DEFAULT_SKELETON = 'documentary_photo';

    /**
     * 路由表 (L1 类型 / L2 栏目 / L3 灵魂 → skeleton_id)
     * 优先级: L3 灵魂 > L2 栏目 > L1 类型
     */
    private const ROUTE_TABLE = [
        // L3 灵魂 (最高优先级, 覆盖 L1/L2 默认)
        'l3' => [
            '宫崎骏'    => 'watercolor_healing',
            'Miyazaki'  => 'watercolor_healing',
            'Mucha'     => 'floral_column',
            '穆夏'      => 'floral_column',
            '赛博朋克'  => 'cyber_neon',
            'Cyberpunk' => 'cyber_neon',
            '国潮'      => 'eastern_guocho',
            'Guochao'   => 'eastern_guocho',
            '无灵魂'    => 'documentary_photo',  // 无灵魂 = 默认走纪实
            '宫崎骏治愈' => 'watercolor_healing',
        ],

        // L2 栏目
        'l2' => [
            '纪实'      => 'documentary_photo',
            '新闻'      => 'documentary_photo',
            '民生'      => 'documentary_photo',
            '基建'      => 'documentary_photo',
            '治愈'      => 'watercolor_healing',
            '宫崎骏'    => 'watercolor_healing',
            '暗黑'      => 'noir_comic',
            '悬疑'      => 'noir_comic',
            '犯罪'      => 'noir_comic',
            '幽默'      => 'flat_humor',
            '段子'      => 'flat_humor',
            '科普'      => 'flat_humor',
            '花色'      => 'floral_column',
            '装饰'      => 'floral_column',
            '唯美'      => 'floral_column',
            '赛博'      => 'cyber_neon',
            '霓虹'      => 'cyber_neon',
            '未来'      => 'cyber_neon',
            '国风'      => 'eastern_guocho',
            '传统'      => 'eastern_guocho',
            '萌宠'      => 'pet_cute',
            '动物'      => 'pet_cute',
            '商业'      => 'business_clean',
            '企业'      => 'business_clean',
            '产品'      => 'business_clean',
            '科技'      => 'business_clean',
            '儿童'      => 'child_picture_book',
            '绘本'      => 'child_picture_book',
            '童话'      => 'child_picture_book',
            '史诗'      => 'epic_cinematic',
            '电影'      => 'epic_cinematic',
            '极简'      => 'minimalist_zen',
            '禅意'      => 'minimalist_zen',
        ],

        // L1 类型 (最低优先级, 兜底)
        'l1' => [
            '纪实人文'  => 'documentary_photo',
            '治愈系'    => 'watercolor_healing',
            '暗黑悬疑'  => 'noir_comic',
            '幽默搞笑'  => 'flat_humor',
            '花色专栏'  => 'floral_column',
            '赛博霓虹'  => 'cyber_neon',
            '东方国潮'  => 'eastern_guocho',
            '萌宠治愈'  => 'pet_cute',
            '商业简洁'  => 'business_clean',
            '儿童绘本'  => 'child_picture_book',
            '史诗电影感' => 'epic_cinematic',
            '极简禅意'  => 'minimalist_zen',
        ],
    ];

    /**
     * 取单套骨架 (合并 preset + custom)
     *
     * @param string $skeleton_id 骨架 ID
     * @return array 骨架配置 (含 en_template, *_default 等), 找不到时回退默认骨架
     */
    public static function get(string $skeleton_id): array
    {
        self::load_all();
        return self::$cache[$skeleton_id] ?? self::$cache[self::DEFAULT_SKELETON] ?? [];
    }

    /**
     * 取全部骨架 (preset + custom 合并)
     *
     * @return array [skeleton_id => config]
     */
    public static function get_all(): array
    {
        self::load_all();
        return self::$cache;
    }

    /**
     * 取全部骨架 ID (供前端下拉)
     *
     * @return string[]
     */
    public static function get_ids(): array
    {
        self::load_all();
        $ids = array_keys(self::$cache);
        // 过滤掉 _meta 键
        return array_values(array_filter($ids, fn($id) => $id !== '_meta' && is_string($id) && !str_starts_with($id, '_')));
    }

    /**
     * 按场景类型路由骨架 (S13 骨架路由)
     *
     * 三维度路由优先级: L3 灵魂 > L2 栏目 > L1 类型
     * 任一维度命中即返回; 全部未命中回退默认骨架
     *
     * 用户实例路由示例:
     *   - 荆荆高铁 (纪实人文 × 基建 × 无灵魂) → documentary_photo
     *   - 治愈系插画 (治愈系 × 治愈 × 宫崎骏) → watercolor_healing
     *   - 花色专栏 (花色专栏 × 花色 × Mucha) → floral_column
     *
     * @param string $l1_type L1 大类型 (如 "纪实人文" "治愈系")
     * @param string $l2_column L2 栏目 (如 "基建" "治愈" "花色")
     * @param string $l3_soul L3 灵魂 (如 "无灵魂" "宫崎骏" "Mucha")
     * @return string 骨架 ID
     */
    public static function route(string $l1_type, string $l2_column = '', string $l3_soul = ''): string
    {
        self::load_all();

        // L3 灵魂优先 (最高优先级)
        if ($l3_soul !== '') {
            $l3 = trim($l3_soul);
            if (isset(self::ROUTE_TABLE['l3'][$l3])) {
                return self::ROUTE_TABLE['l3'][$l3];
            }
        }

        // L2 栏目
        if ($l2_column !== '') {
            $l2 = trim($l2_column);
            if (isset(self::ROUTE_TABLE['l2'][$l2])) {
                return self::ROUTE_TABLE['l2'][$l2];
            }
        }

        // L1 类型
        if ($l1_type !== '') {
            $l1 = trim($l1_type);
            if (isset(self::ROUTE_TABLE['l1'][$l1])) {
                return self::ROUTE_TABLE['l1'][$l1];
            }
        }

        // 兜底
        return self::DEFAULT_SKELETON;
    }

    /**
     * 渲染骨架模板 (替换所有占位符)
     *
     * @param string $skeleton_id 骨架 ID
     * @param array  $vars 占位符值 {subject, action, location, mood, camera, ratio, stylize, color, signature, negative}
     * @return string 渲染后的英文 prompt
     */
    public static function render(string $skeleton_id, array $vars = []): string
    {
        $skel = self::get($skeleton_id);
        if (empty($skel) || empty($skel['en_template'])) {
            return '';
        }

        // 合并默认值: 缺失的占位符用 *_default 兜底
        $defaults = [
            '{subject}'   => $vars['subject']   ?? '',
            '{action}'    => $vars['action']    ?? '',
            '{location}'  => $vars['location']  ?? '',
            '{mood}'      => $vars['mood']      ?? '',
            '{camera}'    => $vars['camera']    ?? $skel['camera_default'] ?? '',
            '{ratio}'     => $vars['ratio']     ?? $skel['ar_default'] ?? '3:2',
            '{stylize}'   => $vars['stylize']   ?? (string)($skel['s_default'] ?? 250),
            '{color}'     => $vars['color']     ?? $skel['color_default'] ?? '',
            '{signature}' => $vars['signature'] ?? $skel['signature_default'] ?? '',
            // {negative} 占位用 negative_default 替换 (任务要求)
            '{negative}'  => $vars['negative']  ?? $skel['negative_default'] ?? '',
        ];

        $out = $skel['en_template'];
        foreach ($defaults as $placeholder => $value) {
            $out = str_replace($placeholder, (string)$value, $out);
        }
        return $out;
    }

    /**
     * 写入用户自定义骨架 (覆盖同 ID 预置骨架)
     *
     * @param string $skeleton_id 骨架 ID
     * @param array  $config 骨架配置
     * @return bool 是否写入成功
     */
    public static function save_custom(string $skeleton_id, array $config): bool
    {
        if ($skeleton_id === '' || $skeleton_id === '_meta') {
            return false;
        }
        $config['skeleton_id'] = $skeleton_id;
        $config['is_custom'] = true;

        $custom = (array) get_option(self::CUSTOM_OPTION, []);
        $custom[$skeleton_id] = $config;

        $ok = update_option(self::CUSTOM_OPTION, $custom, false);
        if ($ok) {
            // 失效缓存, 下次 get/get_all 重新加载
            self::$cache = null;
        }
        return (bool) $ok;
    }

    /**
     * 删除用户自定义骨架
     *
     * @param string $skeleton_id 骨架 ID
     * @return bool
     */
    public static function delete_custom(string $skeleton_id): bool
    {
        $custom = (array) get_option(self::CUSTOM_OPTION, []);
        if (!isset($custom[$skeleton_id])) {
            return false;
        }
        unset($custom[$skeleton_id]);
        $ok = update_option(self::CUSTOM_OPTION, $custom, false);
        if ($ok) {
            self::$cache = null;
        }
        return (bool) $ok;
    }

    /**
     * 加载全部骨架 (preset + custom 合并), 写入缓存
     */
    private static function load_all(): void
    {
        if (self::$cache !== null) {
            return;
        }

        // 预置骨架
        $preset = [];
        if (is_readable(self::PRESET_FILE)) {
            $json = file_get_contents(self::PRESET_FILE);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $preset = $decoded;
            }
        }

        // 用户自定义骨架
        $custom = (array) get_option(self::CUSTOM_OPTION, []);

        // 合并: custom 覆盖 preset (允许用户微调同 ID 骨架)
        self::$cache = array_merge($preset, $custom);
    }

    /**
     * 路由表查询 (供前端调试 / 文档展示)
     *
     * @return array
     */
    public static function get_route_table(): array
    {
        return self::ROUTE_TABLE;
    }

    /**
     * 重置缓存 (测试用)
     */
    public static function reset_cache(): void
    {
        self::$cache = null;
    }
}
