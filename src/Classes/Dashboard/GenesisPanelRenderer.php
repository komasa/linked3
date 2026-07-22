<?php

declare(strict_types=1);
namespace Linked3\Classes\Dashboard;
if (!defined('ABSPATH')) exit;
class GenesisPanelRenderer
{
    public static function enforcePanelCount(array $nodes, int $targetPanels, string $script, string $styleName): array
    {
        $current = count($nodes);
        if ($current === $targetPanels) return $nodes;
        if ($targetPanels < 1) $targetPanels = 1;
        if ($targetPanels > 200) $targetPanels = 200;

        if ($current > $targetPanels) {
            $result = [];
            if ($targetPanels === 1) {
                return [$nodes[0]];
            }
            $result[] = $nodes[0]; // 首节点
            $step = ($current - 1) / ($targetPanels - 1);
            for ($i = 1; $i < $targetPanels - 1; $i++) {
                $idx = (int)round($i * $step);
                $result[] = $nodes[$idx];
            }
            $result[] = $nodes[$current - 1]; // 尾节点
            foreach ($result as $i => &$n) {
                $n['node_id'] = $i + 1;
            }
            return $result;
        }

        $need = $targetPanels - $current;
        $sentences = array_filter(array_map('trim', preg_split('/[。！？\n.!?]+/u', $script)));
        $sentences = array_filter($sentences, function($s) { return mb_strlen($s) >= 10; });
        $sentences = array_values($sentences);

        $existingActions = array_map(fn($n) => $n['action'] ?? '', $nodes);
        $added = 0;
        foreach ($sentences as $s) {
            if ($added >= $need) break;
            $dup = false;
            foreach ($existingActions as $a) {
                if (mb_strpos($a, $s) !== false || mb_strpos($s, $a) !== false) {
                    $dup = true; break;
                }
            }
            if ($dup) continue;

            $nodes[] = [
                'node_id'    => count($nodes) + 1,
                'core_info'  => mb_substr($s, 0, 30),
                'location'   => mb_substr($s, 0, 10),
                'characters' => [],
                'action'     => $s,
                'mood'       => '紧张',
                'shot'       => ['远景','中景','近景','特写'][count($nodes) % 4],
                'angle'      => ['平视','仰视','俯视'][count($nodes) % 3],
                'comp'       => ['三分法','对角线','中心构图'][count($nodes) % 3],
                'plot_point' => '补充节点',
            ];
            $added++;
        }

        while (count($nodes) < $targetPanels) {
            $src = $nodes[count($nodes) % max(1, $current)];
            $src['node_id'] = count($nodes) + 1;
            $src['plot_point'] = '复制补充';
            $nodes[] = $src;
        }

        return $nodes;
    }

    public static function splitByChapters(string $script, string $marker = 'auto'): array
    {
        if ($marker === 'auto') {
            $marker = self::detectChapterMarker($script);
        }

        $rawChapters = match ($marker) {
            'bracket'     => self::splitByBracket($script),
            'chapter_cn'  => self::splitByChapterCn($script),
            'chapter_en'  => self::splitByChapterEn($script),
            'separator'   => self::splitBySeparator($script),
            default       => self::splitByBlankLine($script),
        };

        $chapters = [];
        foreach (array_values($rawChapters) as $i => $ch) {
            if (mb_strlen($ch['content']) < 5) continue;
            $chapters[] = self::buildChapterNode($i + 1, $ch['title'], $ch['content'], $ch['mood'] ?? '紧张');
        }
        return $chapters;
    }

    /**
     * 自动检测章节标记类型
     */
    private static function detectChapterMarker(string $script): string
    {
        if (preg_match('/【[^】]+】/', $script)) return 'bracket';
        if (preg_match('/第[一二三四五六七八九十百千\d]+[章节回]/u', $script)) return 'chapter_cn';
        if (preg_match('/Chapter\s+[\dIVX]+/i', $script)) return 'chapter_en';
        if (strpos($script, "---") !== false) return 'separator';
        return 'blank_line';
    }

    /**
     * 构建标准化的章节节点
     */
    private static function buildChapterNode(int $id, string $title, string $content, string $mood): array
    {
        return [
            'node_id'    => $id,
            'core_info'  => mb_substr($title ?: $content, 0, 30),
            'location'   => mb_substr($title ?: $content, 0, 10),
            'characters' => [],
            'action'     => mb_substr($content, 0, 100),
            'mood'       => $mood,
            'shot'       => ['远景','中景','近景','特写'][($id - 1) % 4],
            'angle'      => ['平视','仰视','俯视'][($id - 1) % 3],
            'comp'       => ['三分法','对角线','中心构图'][($id - 1) % 3],
            'plot_point' => $title,
        ];
    }

    /** @return array<int, array{title:string, content:string}> */
    private static function splitByBracket(string $script): array
    {
        preg_match_all('/【([^】]+)】([^【]*)/u', $script, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $match) {
            $out[] = ['title' => trim($match[1]), 'content' => trim($match[2])];
        }
        return $out;
    }

    /** @return array<int, array{title:string, content:string}> */
    private static function splitByChapterCn(string $script): array
    {
        preg_match_all('/(第[一二三四五六七八九十百千\d]+[章节回])\s*([^\n]*)\n?([^第]*)/u', $script, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $match) {
            $title = trim($match[1] . ' ' . $match[2]);
            $out[] = ['title' => $title, 'content' => trim($match[3])];
        }
        return $out;
    }

    /** @return array<int, array{title:string, content:string, mood:string}> */
    private static function splitByChapterEn(string $script): array
    {
        preg_match_all('/(Chapter\s+[\dIVX]+)\s*:?\s*([^\n]*)\n?([^C]*)/i', $script, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $match) {
            $title = trim($match[1] . ' ' . $match[2]);
            $out[] = ['title' => $title, 'content' => trim($match[3]), 'mood' => 'tense'];
        }
        return $out;
    }

    /** @return array<int, array{title:string, content:string}> */
    private static function splitBySeparator(string $script): array
    {
        $parts = array_filter(array_map('trim', preg_split('/^-{3,}$/m', $script)));
        $out = [];
        foreach ($parts as $content) {
            $out[] = ['title' => '', 'content' => $content];
        }
        return $out;
    }

    /** @return array<int, array{title:string, content:string}> */
    private static function splitByBlankLine(string $script): array
    {
        $parts = array_filter(array_map('trim', preg_split('/\n\s*\n/', $script)));
        $out = [];
        foreach ($parts as $content) {
            $out[] = ['title' => '', 'content' => $content];
        }
        return $out;
    }

    public static function genesisRefineAndSplit(string $script, int $targetPanels, string $styleName, string $styleId = ''): array
    {
        $maxPanels = max(3, min(50, $targetPanels));
        $minPanels = $maxPanels;

        $styleExamples = self::getStyleAdaptiveExamples($styleId, $styleName);
        $exampleCount = min($maxPanels, 4);
        $exampleNodes = [];
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

        $styleHint = self::getStyleHint($styleId, $styleName);

        $prompt = sprintf(
            "你是故事精炼师。任务: 将长故事精炼为 %d 个核心分镜节点。\n\n" .
            "【精炼原则 — 严格遵循】\n" .
            "- 读取全文, 理解完整故事脉络\n" .
            "- 提炼故事核心情节链: 开端 → 发展 → 高潮 → 结局\n" .
            "- 从情节链中均匀选取 %d 个关键转折点作为分镜\n" .
            "- 每个分镜代表故事的一个重要瞬间, 不是细节描摹\n" .
            "- 长文精炼: 5000 字故事 → 5 个分镜 (每个分镜浓缩 1000 字的情节)\n" .
            "- 短文精炼: 500 字故事 → 5 个分镜 (每个分镜浓缩 100 字的情节)\n\n" .
            "%s\n\n" .
            "【重要】location 字段必须从故事原文中提取真实场景, 不要照抄示例中的场景!\n" .
            "【重要】如果故事原文没有明确场景, 根据故事内容推断符合风格的场景, 不要用古宅/雨夜等固定场景!\n\n" .
            "【故事内容】\n%s\n\n" .
            "【任务】\n" .
            "将上述故事精炼为正好 %d 个分镜节点。每个节点是故事的关键转折点, 均匀分布在开端→发展→高潮→结局的情节链上。\n\n" .
            "【输出格式 — 严格遵守】\n" .
            "返回 JSON 对象, 包含 nodes 数组:\n" .
            "{\"nodes\":[\n" .
            "  {\"node_id\":1,\"core_info\":\"纯语义核(人物+事件+地点,≤30字)\",\"location\":\"4-10字场景位置\",\"characters\":[\"角色名\"],\"action\":\"20-50字具体动作\",\"mood\":\"2-6字氛围\",\"shot\":\"远景/全景/中景/近景/特写\",\"angle\":\"平视/仰视/俯视/鸟瞰\",\"comp\":\"三分法/对角线/中心构图/对称式/引导线\",\"plot_point\":\"本节点在故事中的情节推进作用\"},\n" .
            "  {\"node_id\":2,...},\n" .
            "  ...\n" .
            "]}\n\n" .
            "【强约束 — 违反则失败】\n" .
            "1. 必须返回正好 %d 个节点 (不可少 1 个, 不可多 1 个)\n" .
            "2. 每个 node_id 必须是 1 到 %d 的连续整数\n" .
            "3. 节点按故事时间线推进: node_id=1 是开端, node_id=%d 是结局\n" .
            "4. 每个节点的 location/action/mood 都不能和其他节点相同\n" .
            "5. node_id 顺序必须按故事时间线推进\n" .
            "6. core_info 必须是去修饰的纯信息 (≤30字)\n" .
            "7. 只返回 JSON, 不要 markdown 代码块, 不要解释文字\n" .
            "8. **location 必须从故事原文提取, 禁止照抄示例场景**\n\n" .
            "【示例 (%d 个节点) — 仅参考格式, 不要照抄内容】\n" .
            "%s\n\n" .
            "现在请将上述故事精炼为正好 %d 个分镜节点。只返回 JSON。",
            $maxPanels,
            $maxPanels,
            $styleName, $styleHint, $script,
            $maxPanels,
            $maxPanels, $maxPanels, $maxPanels,
            $exampleCount, $exampleJson,
            $maxPanels
        );

        $provider    = get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow');
        $saved_models = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_models', []);
        $model       = $saved_models[$provider] ?? 'Qwen/Qwen2.5-7B-Instruct';

        $maxTokens = max(3000, $maxPanels * 200 + 500);

        try {
            $result = \AIDispatcher::instance()->chat(
                [['role' => 'user', 'content' => $prompt]],
                ['provider' => $provider, 'model' => $model, 'temperature' => 0.6, 'max_tokens' => $maxTokens, 'module' => 'genesis'],
                ['fallback_providers' => ['deepseek', 'zhipu'], 'force_bypass_circuit' => true]
            );

            $raw   = $result['content'] ?? '';
            $nodes = self::parseFPNodesJson($raw);

            if (count($nodes) < $minPanels) {
                $retryPrompt = $prompt . "\n\n【重要提醒】上次只返回了 " . count($nodes) . " 个节点, 不够。这次必须返回正好 " . $maxPanels . " 个节点, node_id 从 1 开始连续。";
                try { // v19.3.0: AI 调用容错
                $retry = \AIDispatcher::instance()->chat(
                    [['role' => 'user', 'content' => $retryPrompt]],
                    ['provider' => $provider, 'model' => $model, 'temperature' => 0.8, 'max_tokens' => $maxTokens, 'module' => 'genesis'],
                    ['fallback_providers' => ['deepseek', 'zhipu'], 'force_bypass_circuit' => true]
                );
                } catch (\Throwable $e) {
                    wp_send_json_error(['message' => __('AI 调用失败: ', 'linked3-ai') . $e->getMessage()], 502);
                }
                $retryNodes = self::parseFPNodesJson($retry['content'] ?? '');
                if (count($retryNodes) > count($nodes)) {
                    $nodes = $retryNodes;
                }
            }

            return $nodes;
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[linked3 genesis] refine and split failed: ' . $e->getMessage());
            }
            return [];
        }
    }

    public static function genesisFPExtractCores(string $script, int $targetPanels, string $styleName, bool $isAuto = false, string $styleId = '') : mixed { return GenesisFPUtils::genesisFPExtractCores($script, $targetPanels, $styleName, $isAuto, $styleId); }

}
