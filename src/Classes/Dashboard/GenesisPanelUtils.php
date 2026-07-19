<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class GenesisPanelUtils
{
        public static function enforcePanelCount(array $nodes, int $targetPanels, string $script, string $styleName) : mixed { return GenesisPanelRenderer::enforcePanelCount($nodes, $targetPanels, $script, $styleName); }

        public static function splitByChapters(string $script, string $marker = 'auto') : mixed { return GenesisPanelRenderer::splitByChapters($script, $marker); }

        public static function genesisRefineAndSplit(string $script, int $targetPanels, string $styleName, string $styleId = '') : mixed { return GenesisPanelRenderer::genesisRefineAndSplit($script, $targetPanels, $styleName, $styleId); }

        public static function genesisFPExtractCores(string $script, int $targetPanels, string $styleName, bool $isAuto = false, string $styleId = '') : mixed { return GenesisPanelRenderer::genesisFPExtractCores($script, $targetPanels, $styleName, $isAuto, $styleId); }

        public static function parseFPNodesJson(string $raw) : mixed { return GenesisFPUtils::parseFPNodesJson($raw); }

        public static function normalizeFPNodes(array $nodes) : mixed { return GenesisFPUtils::normalizeFPNodes($nodes); }

    public static function v7ParsePanels(string $raw): array
    {
        if (empty($raw)) return [];
        $text = trim($raw);

        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $panels = $decoded['panels'] ?? $decoded['scenes'] ?? $decoded;
            if (is_array($panels) && count($panels) >= 2) {
                return self::normalizePanels($panels);
            }
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                $panels = $decoded['panels'] ?? $decoded['scenes'] ?? $decoded;
                if (is_array($panels) && count($panels) >= 2) {
                    return self::normalizePanels($panels);
                }
            }
        }

        if (preg_match_all('/\{[^{}]*"scene_id"[^{}]*\}/', $text, $matches)) {
            $panels = [];
            foreach ($matches[0] as $m) {
                $decoded = json_decode($m, true);
                if (is_array($decoded) && !empty($decoded['action'])) {
                    $panels[] = $decoded;
                }
            }
            if (count($panels) >= 2) return self::normalizePanels($panels);
        }

        if (preg_match_all('/"location"\s*:\s*"([^"]+)".*?"action"\s*:\s*"([^"]+)"/s', $text, $locs, $acts)) {
            $panels = [];
            for ($i = 0; $i < count($locs[1]); $i++) {
                $panels[] = [
                    'scene_id' => 'S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                    'location' => $locs[1][$i],
                    'action' => $acts[1][$i],
                    'mood' => '紧张', 'shot' => '中景', 'angle' => '平视', 'comp' => '三分法',
                    'prompt_en' => '',
                ];
            }
            if (count($panels) >= 2) return $panels;
        }

        return [];
    }

    public static function normalizePanels(array $panels): array
    {
        $result = [];
        foreach ($panels as $i => $p) {
            if (!is_array($p)) continue;
            $result[] = [
                'scene_id'   => $p['scene_id'] ?? ('S' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT)),
                'location'   => $p['location'] ?? '场景' . ($i + 1),
                'characters' => $p['characters'] ?? [],
                'action'     => $p['action'] ?? '',
                'mood'       => $p['mood'] ?? '紧张',
                'shot'       => $p['shot'] ?? '中景',
                'angle'      => $p['angle'] ?? '平视',
                'comp'       => $p['comp'] ?? '三分法',
                'prompt_en'  => $p['prompt_en'] ?? $p['prompt'] ?? '',
            ];
        }
        return $result;
    }

    public static function genesisAIGeneratePanels(string $script, int $targetPanels, string $styleId, bool $isAuto): array
    {
        if (!class_exists('\Linked3\Classes\Core\AIDispatcher')) {
            return [];
        }

        $styleIndex = \Linked3_Genesis_AtomIndex::instance();
        $styleConfig = $styleIndex->getStyleConfig($styleId);
        $styleName = $styleConfig['name_cn'] ?? $styleId;

        $scriptTrimmed = mb_substr($script, 0, 4000);

        $actualTarget = $isAuto ? 10 : max(5, $targetPanels);

        $prompt = sprintf(
            "你是漫画分镜导演。请将以下故事拆分为 %d 个漫画分镜。\n\n" .
            "【核心要求 — 每个分镜必须是故事的不同瞬间】\n" .
            "❌ 错误: 3个分镜都是同一场景同一动作, 只是构图不同\n" .
            "✅ 正确: 每个分镜是故事的不同情节推进, 不同地点/不同动作/不同角色\n\n" .
            "【视觉风格】%s\n\n" .
            "【故事内容】\n%s\n\n" .
            "【每个分镜的字段 — 必须全部填写, 每个分镜内容不同】\n" .
            "- scene_id: S001, S002, S003... (每递增一个就是新场景)\n" .
            "- location: 4-10字场景位置 (P001和P002的location必须不同)\n" .
            "- characters: 出场角色列表\n" .
            "- action: 20-50字这个分镜里具体发生了什么 (P001和P002的action必须不同)\n" .
            "- mood: 2-6字氛围\n" .
            "- shot: 远景/全景/中景/近景/特写\n" .
            "- angle: 平视/仰视/俯视/鸟瞰\n" .
            "- comp: 三分法/对角线/中心构图/对称式/引导线\n\n" .
            "【示例 — 注意每个分镜都是不同情节】\n" .
            '{"panels":[{"scene_id":"S001","location":"雨夜古宅门前","characters":["驱魔师","白龙"],"action":"驱魔师撑伞站在古宅门前,白龙从雨中现身","mood":"阴森神秘","shot":"远景","angle":"平视","comp":"三分法"},{"scene_id":"S002","location":"古宅内堂","characters":["驱魔师","老道长"],"action":"驱魔师与老道长在内堂对峙,老道长手持拂尘","mood":"肃杀紧张","shot":"中景","angle":"仰视","comp":"对称式"},{"scene_id":"S003","location":"古宅后院","characters":["驱魔师","妖魔"],"action":"驱魔师与妖魔战斗,桃木剑出鞘,剑身符文发光","mood":"恐怖压迫","shot":"近景","angle":"仰视","comp":"对角线"}]}' . "\n\n" .
            "上面示例有3个分镜, 每个都是故事的不同场景。你必须生成 %d 个分镜。\n" .
            "关键: 每个分镜的 scene_id/location/action/mood 都不能和前一个相同。\n" .
            "只返回JSON, 不要markdown, 不要解释文字。",
            $actualTarget, $styleName, $scriptTrimmed, $actualTarget
        );

        try {
            $provider = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
            $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
            $model = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

            $maxTokens = max(4000, $actualTarget * 200 + 500);

            $result = \AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.8, 'max_tokens' => $maxTokens, 'module' => 'genesis'],
                ['fallback_providers' => ['deepseek', 'zhipu'], 'force_bypass_circuit' => true]
            );

            $panels = self::parseGenesisPanelsJson($result['content'] ?? '');

            if (count($panels) < 3) {
                $panels = self::fallbackParsePanels($result['content'] ?? '', $script);
            }

            return $panels;
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[linked3 genesis] AI split failed: ' . $e->getMessage());
            }
            return [];
        }
    }

    public static function fallbackParsePanels(string $raw, string $originalScript): array
    {
        if (empty($raw)) return [];

        if (preg_match_all('/\{[^{}]*"scene_id"[^{}]*\}/', $raw, $matches)) {
            $panels = [];
            foreach ($matches[0] as $m) {
                $decoded = json_decode($m, true);
                if (is_array($decoded) && !empty($decoded['action'])) {
                    $panels[] = [
                        'scene_id'   => $decoded['scene_id'] ?? ('S' . str_pad((string)(count($panels) + 1), 3, '0', STR_PAD_LEFT)),
                        'location'   => $decoded['location'] ?? '场景',
                        'characters' => $decoded['characters'] ?? [],
                        'action'     => $decoded['action'] ?? '',
                        'mood'       => $decoded['mood'] ?? '紧张',
                        'shot'       => $decoded['shot'] ?? '中景',
                        'angle'      => $decoded['angle'] ?? '平视',
                        'comp'       => $decoded['comp'] ?? '三分法',
                    ];
                }
            }
            if (count($panels) >= 2) return $panels;
        }

        $parts = preg_split('/(?=S\d{3}|scene_id|"S00)/', $raw);
        if (count($parts) >= 3) {
            $panels = [];
            foreach ($parts as $part) {
                if (preg_match('/"location"\s*:\s*"([^"]+)"/', $part, $m1) &&
                    preg_match('/"action"\s*:\s*"([^"]+)"/', $part, $m2)) {
                    $panels[] = [
                        'scene_id' => 'S' . str_pad((string)(count($panels) + 1), 3, '0', STR_PAD_LEFT),
                        'location' => $m1[1],
                        'characters' => [],
                        'action' => $m2[1],
                        'mood' => '紧张',
                        'shot' => '中景', 'angle' => '平视', 'comp' => '三分法',
                    ];
                }
            }
            if (count($panels) >= 2) return $panels;
        }

        return [];
    }

    public static function parseGenesisPanelsJson(string $raw): array
    {
        if (empty($raw)) return [];
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (!is_array($decoded)) return [];

        $panels = $decoded['panels'] ?? $decoded['scenes'] ?? $decoded;
        if (!is_array($panels)) return [];

        $result = [];
        foreach ($panels as $p) {
            if (!is_array($p)) continue;
            $result[] = [
                'scene_id'   => $p['scene_id'] ?? ('S' . str_pad((string)(count($result) + 1), 3, '0', STR_PAD_LEFT)),
                'location'   => $p['location'] ?? '场景',
                'characters' => $p['characters'] ?? [],
                'action'     => $p['action'] ?? '',
                'mood'       => $p['mood'] ?? '',
                'shot'       => $p['shot'] ?? '中景',
                'angle'      => $p['angle'] ?? '平视',
                'comp'       => $p['comp'] ?? '三分法',
            ];
        }
        return $result;
    }

    public static function formatGenesisPanel(array $panel, array $assembled, array $pqs): array
    {
        return [
            'panel_id'   => $panel['panel_id'],
            'scene_id'   => $panel['scene_id'],
            'location'   => $panel['location'],
            'action'     => $panel['action'],
            'mood'       => $panel['mood'],
            'focus'      => $panel['focus'] ?? '',
            'shot'       => $panel['shot'] ?? '中景',
            'angle'      => $panel['angle'] ?? '平视',
            'comp'       => $panel['comp'] ?? '三分法',
            'characters' => $panel['characters'] ?? [],
            'prompt_en'  => $assembled['prompt_en'],
            'prompt_with_params' => $assembled['prompt_with_params'],
            'style'      => $assembled['style'],
            'style_name' => $assembled['style_name'],
            'platform'   => $assembled['platform'],
            'platform_params' => $assembled['platform_params'],
            'character_details' => $assembled['characters'],
            'scene_detail' => $assembled['scene']['scene_name'] ?? '',
            'pqs'        => $pqs,
        ];
    }

}
