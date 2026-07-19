<?php

declare(strict_types=1);
/**
 * Genesis Style Engine v7.4.0
 *
 * 风格参数原子化引擎 — 从 JSON 加载风格配置,替代所有硬编码
 *
 * 核心原则 (FP部公理):
 *   - 9 风格完全独立,零母版共享
 *   - 所有参数从 styles/{styleId}.json 加载
 *   - 禁止任何方法硬编码风格特定内容
 *
 * @package Linked3\Genesis
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class GenesisStyleEngine
{
    private static ?array $cache = [];

    /**
     * 加载风格配置 (带缓存)
     */
    public static function load(string $styleId): array
    {
        if (isset(self::$cache[$styleId])) {
            return self::$cache[$styleId];
        }

        $path = LINKED3_DIR . 'src/Classes/Genesis/styles/' . $styleId . '.json';
        if (!file_exists($path)) {
            // 兜底: 返回通用配置
            return self::defaultConfig($styleId);
        }

        $json = file_get_contents($path);
        if ($json === false) { return []; }
        $config = json_decode($json, true);
        if (!is_array($config)) {
            return self::defaultConfig($styleId);
        }

        self::$cache[$styleId] = $config;
        return $config;
    }

    /**
     * 获取风格中文名
     */
    public static function getNameCn(string $styleId): string
    {
        return self::load($styleId)['name_cn'] ?? $styleId;
    }

    /**
     * 获取风格英文名
     */
    public static function getNameEn(string $styleId): string
    {
        return self::load($styleId)['name_en'] ?? $styleId;
    }

    /**
     * 获取 FP 剥骨示例节点 (从 JSON 加载,不再硬编码)
     */
    public static function getFpExamples(string $styleId): array
    {
        $config = self::load($styleId);
        $examples = $config['fp_examples'] ?? [];
        if (empty($examples)) {
            // 通用兜底示例 (不绑定特定风格)
            return [
                ['core_info' => '主角登场', 'location' => '故事起点', 'characters' => ['主角'], 'action' => '主角在故事起点出现,环境交代', 'mood' => '引入氛围', 'plot_point' => '开场钩子'],
                ['core_info' => '冲突发生', 'location' => '冲突场景', 'characters' => ['主角', '对手'], 'action' => '主角遇到冲突,与对手对峙', 'mood' => '紧张升级', 'plot_point' => '冲突升级'],
                ['core_info' => '高潮对决', 'location' => '高潮场景', 'characters' => ['主角', '对手'], 'action' => '主角与对手正面交锋,决定胜负', 'mood' => '激烈高潮', 'plot_point' => '高潮战斗'],
                ['core_info' => '结局收尾', 'location' => '结局场景', 'characters' => ['主角'], 'action' => '主角完成旅程,故事收尾', 'mood' => '释然收束', 'plot_point' => '收尾闭环'],
            ];
        }
        return $examples;
    }

    /**
     * 获取风格提示词示例 (画面生成用)
     */
    public static function getPromptExample(string $styleId): string
    {
        $config = self::load($styleId);
        return $config['prompt_example'] ?? 'A lone figure stands in a dramatic scene at a decisive moment, atmospheric lighting casting deep shadows, the character captured in a tense pose with detailed expression, shot in a moody wide angle from eye level with rule of thirds composition, cinematic atmosphere with rich colors and deep contrast --ar 2:3 --s 750 --style raw --no text';
    }

    /**
     * 获取风格中文约束 (FP 剥骨用)
     */
    public static function getStyleConstraintCn(string $styleId): string
    {
        $config = self::load($styleId);
        $nameCn = $config['name_cn'] ?? $styleId;
        return $config['style_constraint_cn'] ?? "【风格强制约束】当前风格: " . $nameCn . "\n✅ 请根据故事内容适配此风格的场景\n❌ 禁止照抄示例中的场景, 必须根据故事原文提取真实场景\n氛围: 与风格匹配";
    }

    /**
     * 获取风格英文约束 (画面生成用)
     */
    public static function getStyleConstraintEn(string $styleId): string
    {
        $config = self::load($styleId);
        $nameEn = $config['name_en'] ?? $styleId;
        return $config['style_constraint_en'] ?? "IMPORTANT: Use scenes from the story content above. Do NOT use generic scenes. Adapt the scene to match the story's actual setting and the " . $nameEn . " style.";
    }

    /**
     * 获取风格 meta_prompt (综合关键词)
     */
    public static function getMetaPrompt(string $styleId): string
    {
        $config = self::load($styleId);
        return $config['meta_prompt'] ?? ($config['prompt_keywords'] ?? '');
    }

    /**
     * 获取场景白名单
     */
    public static function getSceneWhitelist(string $styleId): array
    {
        $config = self::load($styleId);
        return $config['scene_whitelist'] ?? [];
    }

    /**
     * 获取场景黑名单
     */
    public static function getSceneBlacklist(string $styleId): array
    {
        $config = self::load($styleId);
        return $config['scene_blacklist'] ?? [];
    }

    /**
     * 获取负面关键词
     */
    public static function getNegativeKeywords(string $styleId): string
    {
        $config = self::load($styleId);
        return $config['negative_keywords'] ?? '';
    }

    /**
     * 获取 prompt_keywords
     */
    public static function getPromptKeywords(string $styleId): string
    {
        $config = self::load($styleId);
        return $config['prompt_keywords'] ?? '';
    }

    /**
     * 获取所有可用风格列表
     */
    public static function getAllStyles(): array
    {
        $index = self::loadIndex();
        return $index['styles'] ?? [];
    }

    /**
     * 加载 _index.json
     */
    public static function loadIndex(): array
    {
        $path = LINKED3_DIR . 'src/Classes/Genesis/styles/_index.json';
        if (!file_exists($path)) {
            return ['version' => '7.4.0', 'total_styles' => 0, 'styles' => []];
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : ['version' => '7.4.0', 'total_styles' => 0, 'styles' => []];
    }

    /**
     * 默认配置 (风格文件不存在时兜底)
     */
    private static function defaultConfig(string $styleId): array
    {
        return [
            'name_cn' => $styleId,
            'name_en' => $styleId,
            'category' => '通用',
            'prompt_keywords' => '',
            'negative_keywords' => '',
            'scene_whitelist' => [],
            'scene_blacklist' => [],
            'fp_examples' => [],
            'prompt_example' => '',
            'style_constraint_cn' => '',
            'style_constraint_en' => '',
            'meta_prompt' => '',
        ];
    }

    // ============================================================
    // v7.6.0: 防抄袭硬编码检测层
    // ============================================================

    /**
     * v7.6.0: 检测生成结果是否被其他风格污染
     *
     * 检查项:
     *   1. location 是否在当前风格黑名单中
     *   2. location 是否包含其他风格的标志性场景词
     *   3. prompt_en 是否包含其他风格的标志性关键词
     *
     * @param string $styleId 当前风格 ID
     * @param array $node 生成节点 (含 location, prompt_en 等)
     * @return array {contaminated: bool, reason: string, suggestions: array}
     */
    public static function isStyleContaminated(string $styleId, array $node): array
    {
        $config = self::load($styleId);
        $location = $node['location'] ?? '';
        $promptEn = $node['prompt_en'] ?? '';
        $blacklist = $config['scene_blacklist'] ?? [];

        // 1. 检查 location 是否在黑名单中
        foreach ($blacklist as $bad) {
            if (mb_strpos($location, $bad) !== false || mb_strpos($bad, $location) !== false) {
                if (mb_strlen($location) >= 2) {
                    return [
                        'contaminated' => true,
                        'reason' => "场景'{$location}'在当前风格黑名单中 (匹配: {$bad})",
                        'suggestions' => array_slice($config['scene_whitelist'] ?? [], 0, 5),
                    ];
                }
            }
        }

        // 2. 跨风格标志性场景词检测
        $crossStyleMarkers = [
            'exorcism_dark_ink' => ['古宅', '雨夜', '驱魔师', '白龙', '老道长', '桃木剑'],
            'cyberpunk_neon' => ['霓虹', '黑客', '全息', '赛博', '义体', '数据港'],
            'ukiyoe_washoku' => ['武士', '艺伎', '江户', '樱花', '富士山', '浮世绘'],
            'gothic_dark_stained' => ['教堂', '彩窗', '吸血鬼', '哥特', '钟楼'],
            'steampunk_victorian' => ['蒸汽', '飞艇', '齿轮', '黄铜', '维多利亚'],
            'fashion_editorial' => ['模特', 'T台', '秀场', '时尚', '杂志'],
            'hanfu_photography' => ['书生', '汉服', '桃花林', '古道', '科举'],
        ];

        foreach ($crossStyleMarkers as $otherStyle => $markers) {
            if ($otherStyle === $styleId) continue; // 跳过自身
            foreach ($markers as $marker) {
                if (mb_strpos($location, $marker) !== false) {
                    return [
                        'contaminated' => true,
                        'reason' => "场景'{$location}'包含其他风格({$otherStyle})的标志性词'{$marker}'",
                        'suggestions' => array_slice($config['scene_whitelist'] ?? [], 0, 5),
                    ];
                }
            }
        }

        // 3. prompt_en 跨风格关键词检测
        $crossStyleEnMarkers = [
            'exorcism_dark_ink' => ['ancient mansion', 'rainy night', 'exorcist', 'peach-wood sword'],
            'cyberpunk_neon' => ['neon', 'hacker', 'holographic', 'cyberpunk', 'cybernetic'],
            'ukiyoe_washoku' => ['samurai', 'geisha', 'cherry blossom', 'ukiyo-e', 'edo'],
        ];

        foreach ($crossStyleEnMarkers as $otherStyle => $markers) {
            if ($otherStyle === $styleId) continue;
            foreach ($markers as $marker) {
                if (stripos($promptEn, $marker) !== false) {
                    return [
                        'contaminated' => true,
                        'reason' => "prompt_en 包含其他风格({$otherStyle})的标志性词'{$marker}'",
                        'suggestions' => array_slice($config['scene_whitelist'] ?? [], 0, 5),
                    ];
                }
            }
        }

        return ['contaminated' => false, 'reason' => '', 'suggestions' => []];
    }

    /**
     * v7.6.0: 批量检测节点列表,返回污染报告
     */
    public static function auditNodes(string $styleId, array $nodes): array
    {
        $report = [
            'style_id' => $styleId,
            'total_nodes' => count($nodes),
            'contaminated_count' => 0,
            'clean_count' => 0,
            'issues' => [],
        ];

        foreach ($nodes as $i => $node) {
            $check = self::isStyleContaminated($styleId, $node);
            if ($check['contaminated']) {
                $report['contaminated_count']++;
                $report['issues'][] = [
                    'node_index' => $i,
                    'location' => $node['location'] ?? '',
                    'reason' => $check['reason'],
                    'suggestions' => $check['suggestions'],
                ];
            } else {
                $report['clean_count']++;
            }
        }

        return $report;
    }
}
