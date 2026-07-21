<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class GenesisFPUtils
{
    public static function genesisFPExtractCores(string $script, int $targetPanels, string $styleName, bool $isAuto = false, string $styleId = ''): array
    {
        $config = self::buildFpConfig($targetPanels, $isAuto);
        $prompt = self::buildFpPrompt($script, $styleName, $styleId, $config);
        $result = self::callFpAI($prompt, $config);

        if (empty($result)) {
            return [];
        }

        $nodes = self::parseFPNodesJson($result['content'] ?? '');

        // Retry if not enough nodes
        $minAccept = $isAuto ? $config['minPanels'] : max(2, intval($config['maxPanels'] / 2));
        if (count($nodes) < $minAccept) {
            $retryNodes = self::retryFpExtraction($prompt, count($nodes), $config);
            if (count($retryNodes) > count($nodes)) {
                $nodes = $retryNodes;
            }
        }

        return $nodes;
    }

    /**
     * Build FP extraction configuration (panel count limits).
     */
    private static function buildFpConfig(int $targetPanels, bool $isAuto): array
    {
        if ($isAuto) {
            return [
                'maxPanels'    => min(15, $targetPanels + 2),
                'minPanels'    => max(3, $targetPanels - 2),
                'isAuto'       => true,
                'targetPanels' => $targetPanels,
            ];
        }
        return [
            'maxPanels'    => $targetPanels,
            'minPanels'    => max(3, intval($targetPanels / 2)),
            'isAuto'       => false,
            'targetPanels' => $targetPanels,
        ];
    }

    /**
     * Build the FP extraction prompt with style examples and constraints.
     */
    private static function buildFpPrompt(string $script, string $styleName, string $styleId, array $config): string
    {
        $maxPanels = $config['maxPanels'];
        $minPanels = $config['minPanels'];
        $isAuto    = $config['isAuto'];
        $targetPanels = $config['targetPanels'];

        $exampleCount  = min($maxPanels, 4);
        $styleExamples = self::getStyleAdaptiveExamples($styleId, $styleName);
        $exampleNodes  = [];
        for ($i = 0; $i < $exampleCount; $i++) {
            $ex = $styleExamples[$i % count($styleExamples)];
            $exampleNodes[] = [
                'node_id'    => $i + 1,
                'core_info'  => $ex['core_info'],
                'location'   => $ex['location'],
                'characters' => $ex['characters'],
                'action'     => $ex['action'],
                'mood'       => $ex['mood'],
                'shot'       => ['远景', '中景', '近景', '特写'][$i],
                'angle'      => ['平视', '仰视', '俯视', '平视'][$i],
                'comp'       => ['三分法', '对称式', '对角线', '引导线'][$i],
                'plot_point' => $ex['plot_point'],
            ];
        }
        $exampleJson = json_encode(['nodes' => $exampleNodes], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $styleHint   = self::getStyleHint($styleId, $styleName);

        $countInstruction = $isAuto
            ? "将上述故事拆分为 {$minPanels}-{$maxPanels} 个语义核节点。**按故事实际情节节奏决定节点数**, 故事短就少拆, 故事长就多拆, 不要硬拆。\n"
            : "将上述故事拆分为正好 {$maxPanels} 个语义核节点。\n";

        $constraint = $isAuto
            ? "1. 必须返回 {$minPanels}-{$maxPanels} 个节点 (按故事节奏决定, 短故事 3-5 个, 长故事 10-15 个)\n"
              . "2. 每个 node_id 必须从 1 开始连续递增\n"
              . "3. **不要硬拆**: 如果故事只有 2-3 个独立瞬间, 就只返回 2-3 个节点, 不要为凑数重复内容\n"
            : "1. 必须返回正好 {$maxPanels} 个节点 (不可少 1 个, 不可多 1 个)\n"
              . "2. 每个 node_id 必须是 1 到 {$maxPanels} 的连续整数\n";

        $finalInstruction = $isAuto
            ? "现在请按上述故事的实际节奏, 生成 {$minPanels}-{$maxPanels} 个语义核节点 (建议 {$targetPanels} 个左右)。根据故事内容决定, 短故事少分镜, 长故事多分镜。只返回 JSON。"
            : "现在请为上述故事生成正好 {$maxPanels} 个语义核节点。只返回 JSON。";

        return sprintf(
            "你是 FP 部语义溯源院的解构师。任务: 剥离一切修饰, 提取纯语义核, 按故事时间线拆分为独立节点。\n\n"
            . "【FP 部剥骨原则 — 严格遵循 (借鉴 deai_5d)】\n"
            . "- 假设原文皆为机器伪装\n"
            . "- 剥离一切修饰词/形容词/副词/连接词/感叹词\n"
            . "- 提取纯语义核: 人物 + 事件 + 地点 (必有要素)\n"
            . "- 语义零遗漏: 故事的所有情节推进点必须保留\n"
            . "- 每个语义核节点 = 故事的一个独立情节瞬间 (不同地点/动作/角色)\n\n"
            . "%s\n\n"
            . "【重要】location 字段必须从故事原文中提取真实场景, 不要照抄示例中的场景!\n"
            . "【重要】如果故事原文没有明确场景, 根据故事内容推断符合风格的场景, 不要用古宅/雨夜等固定场景!\n\n"
            . "【故事内容】\n%s\n\n"
            . "【任务】\n%s"
            . "每个节点是故事的不同瞬间。\n\n"
            . "【输出格式 — 严格遵守】\n"
            . "返回 JSON 对象, 包含 nodes 数组:\n"
            . "{\"nodes\":[\n"
            . "  {\"node_id\":1,\"core_info\":\"纯语义核(人物+事件+地点,≤30字)\",\"location\":\"4-10字场景位置\",\"characters\":[\"角色名\"],\"action\":\"20-50字具体动作\",\"mood\":\"2-6字氛围\",\"shot\":\"远景/全景/中景/近景/特写\",\"angle\":\"平视/仰视/俯视/鸟瞰\",\"comp\":\"三分法/对角线/中心构图/对称式/引导线\",\"plot_point\":\"本节点在故事中的情节推进作用\"},\n"
            . "  {\"node_id\":2,...},\n"
            . "  ...\n"
            . "]}\n\n"
            . "【强约束 — 违反则失败】\n%s"
            . "4. 每个节点的 location/action/mood 都不能和其他节点相同\n"
            . "5. node_id 顺序必须按故事时间线推进\n"
            . "6. core_info 必须是去修饰的纯信息 (≤30字)\n"
            . "7. 只返回 JSON, 不要 markdown 代码块, 不要解释文字\n"
            . "8. **location 必须从故事原文提取, 禁止照抄示例场景**\n\n"
            . "【示例 (%d 个节点) — 仅参考格式, 不要照抄内容】\n"
            . "%s\n\n"
            . "%s",
            $styleName, $styleHint, $script,
            $countInstruction,
            $constraint,
            $exampleCount, $exampleJson,
            $finalInstruction
        );
    }

    /**
     * Call AI for FP extraction.
     */
    private static function callFpAI(string $prompt, array $config): array
    {
        $provider     = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model        = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
        $maxTokens    = max(3000, $config['maxPanels'] * 200 + 500);

        try {
            return \AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.7, 'max_tokens' => $maxTokens, 'module' => 'genesis'],
                ['fallback_providers' => ['deepseek', 'zhipu'], 'force_bypass_circuit' => true]
            );
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[linked3 genesis] FP extract cores failed: ' . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Retry FP extraction with higher temperature when first attempt returned too few nodes.
     */
    private static function retryFpExtraction(string $prompt, int $currentCount, array $config): array
    {
        $provider     = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model        = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';
        $maxTokens    = max(3000, $config['maxPanels'] * 200 + 500);

        $target = $config['isAuto']
            ? "至少返回 {$config['minPanels']} 个节点"
            : "必须返回正好 {$config['maxPanels']} 个节点";

        $retryPrompt = $prompt . "\n\n【重要提醒】上次只返回了 {$currentCount} 个节点, 不够。这次{$target}, node_id 从 1 开始连续。";

        try {
            $retry = \AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $retryPrompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.9, 'max_tokens' => $maxTokens, 'module' => 'genesis'],
                ['fallback_providers' => ['deepseek', 'zhipu'], 'force_bypass_circuit' => true]
            );
            return self::parseFPNodesJson($retry['content'] ?? '');
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function parseFPNodesJson(string $raw): array
    {
        if (empty($raw)) return [];
        $text = trim($raw);

        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        // 1. 直接 JSON 解析
        $nodes = self::parseFromDirectJson($text);
        if ($nodes !== null) {
            return $nodes;
        }

        // 2. 从文本中提取嵌入的 JSON 对象
        $nodes = self::parseFromEmbeddedJson($text);
        if ($nodes !== null) {
            return $nodes;
        }

        // 3. 正则提取单个 node_id 对象
        $nodes = self::parseFromRegexNodes($text);
        if ($nodes !== null) {
            return $nodes;
        }

        return [];
    }

    /**
     * 直接 JSON 解码整个文本.
     */
    private static function parseFromDirectJson(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            return null;
        }
        $nodes = $decoded['nodes'] ?? $decoded['panels'] ?? $decoded['scenes'] ?? $decoded;
        if (is_array($nodes) && !empty($nodes)) {
            return self::normalizeFPNodes($nodes);
        }
        return null;
    }

    /**
     * 从文本中正则提取嵌入的 JSON 对象.
     */
    private static function parseFromEmbeddedJson(string $text): ?array
    {
        if (!preg_match('/\{[\s\S]*\}/', $text, $m)) {
            return null;
        }
        $obj = json_decode($m[0], true);
        if (!is_array($obj)) {
            return null;
        }
        $nodes = $obj['nodes'] ?? $obj['panels'] ?? $obj['scenes'] ?? [];
        if (is_array($nodes) && !empty($nodes)) {
            return self::normalizeFPNodes($nodes);
        }
        return null;
    }

    /**
     * 正则逐个提取 node_id 对象.
     */
    private static function parseFromRegexNodes(string $text): ?array
    {
        if (!preg_match_all('/\{[^{}]*"node_id"[^{}]*\}/', $text, $matches)) {
            return null;
        }
        $nodes = [];
        foreach ($matches[0] as $m) {
            $decoded = json_decode($m, true);
            if (is_array($decoded) && !empty($decoded['action'])) {
                $nodes[] = $decoded;
            }
        }
        if (count($nodes) >= 2) {
            return self::normalizeFPNodes($nodes);
        }
        return null;
    }

    public static function normalizeFPNodes(array $nodes): array
    {
        $result  = [];
        $shots   = ['远景', '全景', '中景', '近景', '特写'];
        $angles  = ['平视', '仰视', '俯视', '鸟瞰'];
        $comps   = ['三分法', '对角线', '中心构图', '对称式', '引导线'];
        $i       = 1;
        foreach ($nodes as $n) {
            if (!is_array($n)) continue;
            if (empty($n['action']) && empty($n['core_info'])) continue;
            $result[] = [
                'node_id'    => $n['node_id'] ?? $i,
                'core_info'  => $n['core_info'] ?? mb_substr($n['action'] ?? '', 0, 30),
                'location'   => $n['location'] ?? ('场景' . $i),
                'characters' => $n['characters'] ?? [],
                'action'     => $n['action'] ?? $n['core_info'] ?? '',
                'mood'       => $n['mood'] ?? '紧张',
                'shot'       => $n['shot'] ?? $shots[($i - 1) % count($shots)],
                'angle'      => $n['angle'] ?? $angles[($i - 1) % count($angles)],
                'comp'       => $n['comp'] ?? $comps[($i - 1) % count($comps)],
                'plot_point' => $n['plot_point'] ?? '',
            ];
            $i++;
        }
        return $result;
    }

}
