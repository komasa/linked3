<?php

declare(strict_types=1);
/**
 * Cognitive Operating System — EX 部门 (Exploration)
 *
 * 从 COSDepartments 提取的方案种群生成部门 (v20.4-fix3 拆分)。
 *
 * 职责:
 *   - 优先调用 AI 生成真实方案文本
 *   - AI 不可用时降级为 YAML 配置的结构化模板
 *
 * 原代码位于 COSDepartments::ex_department / generate_variants_via_ai /
 * generate_variants_fallback, 拆分后逻辑等价, 签名兼容。
 *
 * @package Linked3\CognitiveOS\Core
 * @since   20.4
 * @extracted from COSDepartments
 */

namespace Linked3\Classes\CognitiveOS\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class COSExDepartment
 *
 * EX 部门 — 方案种群生成 (真实 AI + 结构化降级)。
 *
 * 调用方通过 COSDepartments::ex_department() 门面委托, 也可直接调用
 * COSExDepartment::generate()。
 */
class COSExDepartment
{
    /**
     * 降级策略模板缓存 (避免每次调用都读 YAML)。
     *
     * @var array|null
     */
    private static $strategies_cache = null;

    /**
     * 生成方案种群 (EX 部入口)。
     *
     * 原 COSDepartments::ex_department(), 签名兼容。
     *
     * @param array $input { info_core: array, generation: string, baseline: array }
     * @return array { department, status, deliverables, message }
     */
    public static function generate(array $input): array
    {
        $info_core  = $input['info_core'] ?? [];
        $generation = $input['generation'] ?? 'G1';
        $baseline   = $input['baseline'] ?? [];

        $problem = $info_core['problem'] ?? '未定义问题';

        // v20.4-fix9: 大幅减少方案数量, 确保单次 AI 调用在 60 秒内完成
        // G1: 初代涌现 5 个方案 (原 10)
        // G2: 重组变异 3 个 (原 6)
        // G3: 终极坍缩 2 个 (原 3)
        $count = $generation === 'G1' ? 5 : ($generation === 'G2' ? 3 : 2);

        // v20.4: 优先用真实 AI 生成方案
        $variants = self::generate_via_ai($problem, $generation, $count, $baseline, $info_core);

        // AI 不可用或返回不足时, 降级为结构化模板
        if (count($variants) < 2) {
            $variants = self::generate_fallback($problem, $generation, $count, $baseline);
        }

        return [
            'department'   => 'EX',
            'status'       => 'pass',
            'deliverables' => ['variants' => $variants],
            'message'      => sprintf('EX部: %s 生成 %d 个方案', $generation, count($variants)),
        ];
    }

    /**
     * v20.4: 通过真实 AI 调用生成方案种群。
     *
     * 向 AI 发送结构化 prompt, 要求返回 JSON 数组, 每个元素包含
     * approach (方案描述) / steps (操作步骤) / risk / feasibility / novelty。
     * AI 不可用时返回空数组, 由 fallback 接管。
     *
     * 原 COSDepartments::generate_variants_via_ai()。
     *
     * @param string $problem
     * @param string $generation
     * @param int    $count
     * @param array  $baseline  上一代 MVP (G2/G3 用于变异基线)
     * @param array  $info_core
     * @return array
     */
    private static function generate_via_ai(string $problem, string $generation, int $count, array $baseline, array $info_core): array
    {
        // 检查 AI Dispatcher 是否可用
        if (!class_exists('\Linked3\Classes\Core\AIDispatcher')) {
            return [];
        }

        $domain = $info_core['context']['domain'] ?? '通用';

        // 构建 system prompt — 教 AI 如何生成多样化方案
        $sys_prompt = "你是一位认知操作系统 (COS) 的方案生成专家。"
            . "你的任务是为给定问题生成 {$count} 个**不同思路**的解决方案。\n\n"
            . "要求:\n"
            . "1. 每个方案必须有独立的 approach (方案描述, 50-100字)\n"
            . "2. 每个方案必须有 steps (3-5个可操作步骤, 用分号分隔)\n"
            . "3. 对每个方案给出 risk (风险1-10) / feasibility (可行性1-10) / novelty (创新性1-10) 评分\n"
            . "4. 方案之间思路必须差异化\n"
            . "5. 必须严格输出 JSON 数组格式\n\n"
            . "输出格式 (纯JSON, 不要markdown代码块):\n"
            . '[{"approach":"方案描述","steps":"步骤1;步骤2;步骤3","risk":5,"feasibility":8,"novelty":7}]';

        // 构建 user prompt
        $user_prompt = "问题: {$problem}\n";
        $user_prompt .= "领域: {$domain}\n";
        $user_prompt .= "代际: {$generation} (需生成 {$count} 个方案)\n";

        if (!empty($baseline) && $generation !== 'G1') {
            $baseline_text = $baseline['approach'] ?? '';
            $user_prompt .= "\n上一代 MVP 方案 (作为变异基线, 请在其基础上重组/变异/优化, 但也要有全新思路):\n";
            $user_prompt .= $baseline_text . "\n";
        }

        $user_prompt .= "\n请生成 {$count} 个差异化方案, 严格输出 JSON 数组。";

        $messages = [
            ['role' => 'system', 'content' => $sys_prompt],
            ['role' => 'user',   'content' => $user_prompt],
        ];

        // v20.4-fix15: 降级模型 72B→32B, max_tokens 1200→800, timeout 40→35
        // 72B 太慢 (40-60s), 32B 平衡质量与速度 (15-25s)
        $options = [
            'temperature' => 0.9,
            'max_tokens'  => 800,
            'module'      => 'cos_ex',
            'user_id'     => get_current_user_id(),
            'timeout'     => 35,
            'model'       => 'Qwen/Qwen2.5-32B-Instruct',
        ];
        // v20.4-fix14: 只把已配置 API Key 的 provider 作为 fallback (与 run_lever 一致)
        $default_provider = function_exists('get_option')
            ? get_option(LINKED3_OPTION_PREFIX . 'default_provider', 'siliconflow')
            : 'siliconflow';
        $saved_keys = function_exists('get_option')
            ? (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', [])
            : [];
        $candidate_pool = ['siliconflow', 'deepseek', 'qwen', 'openai', 'kimi'];
        $diverse_fallbacks = [];
        foreach ($candidate_pool as $p) {
            if ($p !== $default_provider && (!empty($saved_keys[$p]) || $p === 'siliconflow')) {
                $diverse_fallbacks[] = $p;
            }
        }
        $diverse_fallbacks = array_slice($diverse_fallbacks, 0, 2);
        $config = [
            'fallback_providers'    => $diverse_fallbacks,
            'force_bypass_circuit'  => true,
        ];

        try {
            $dispatcher = \Linked3\Classes\Core\AIDispatcher::instance();
            $result = $dispatcher->chat($messages, $options, $config);
            $content = $result['content'] ?? '';
        } catch (\Exception $e) {
            // AI 调用失败, 返回空让 fallback 接管
            return [];
        }

        if (empty($content)) {
            return [];
        }

        // 解析 JSON (容错: 去除 markdown 代码块包裹)
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            // 尝试提取第一个 JSON 数组
            if (preg_match('/\[.*\]/s', $content, $m)) {
                $parsed = json_decode($m[0], true);
            }
        }

        if (!is_array($parsed) || empty($parsed)) {
            return [];
        }

        // 构建 variants
        $variants = [];
        $i = 1;
        foreach ($parsed as $item) {
            if ($i > $count) {
                break;
            }
            if (!is_array($item) || empty($item['approach'])) {
                continue;
            }

            $approach = (string) $item['approach'];
            $steps    = (string) ($item['steps'] ?? '');
            $risk       = max(1, min(10, (int) ($item['risk'] ?? 5)));
            $feasibility = max(1, min(10, (int) ($item['feasibility'] ?? 6)));
            $novelty    = max(1, min(10, (int) ($item['novelty'] ?? 5)));

            $variants[] = [
                'id'         => $generation . '_V' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                'generation' => $generation,
                'approach'   => $approach,
                'steps'      => $steps,
                'score'      => [
                    'risk'       => $risk,
                    'feasibility' => $feasibility,
                    'novelty'    => $novelty,
                ],
                'source'     => 'ai',
            ];
            $i++;
        }

        return $variants;
    }

    /**
     * v20.4: 结构化降级方案生成 (AI 不可用时使用)。
     *
     * 不再使用 rand() 随机分数, 而是基于问题文本生成有意义的差异化方案,
     * 分数基于方案特征启发式计算。
     *
     * 策略模板从 config/fallback-strategies.yaml 加载, 运营可直接编辑 YAML
     * 调整策略池, 无需改动代码。
     *
     * 原 COSDepartments::generate_variants_fallback()。
     *
     * @param string $problem
     * @param string $generation
     * @param int    $count
     * @param array  $baseline
     * @return array
     */
    private static function generate_fallback(string $problem, string $generation, int $count, array $baseline): array
    {
        $strategies = self::load_strategies();

        // 兜底: YAML 加载失败时也不允许中断流水线
        if (empty($strategies)) {
            return [];
        }

        // 根据代际选择策略子集
        if ($generation === 'G1') {
            $selected = array_slice($strategies, 0, min($count, count($strategies)));
        } elseif ($generation === 'G2' && !empty($baseline)) {
            // G2: 在基线基础上重组变异
            $selected = array_slice($strategies, 2, min($count, count($strategies) - 2));
        } else {
            $selected = array_slice($strategies, 0, min($count, count($strategies)));
        }

        $variants = [];
        $i = 1;
        foreach ($selected as $s) {
            $approach = "【{$s['tag']}】针对「{$problem}」, {$s['desc']}。核心动作: {$s['steps']}。";
            $variants[] = [
                'id'         => $generation . '_V' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                'generation' => $generation,
                'approach'   => $approach,
                'steps'      => $s['steps'],
                'score'      => [
                    'risk'       => $s['risk'],
                    'feasibility' => $s['feas'],
                    'novelty'    => $s['nov'],
                ],
                'source'     => 'fallback',
            ];
            $i++;
        }

        return $variants;
    }

    /**
     * 从 YAML 配置加载降级策略模板。
     *
     * 优先使用 yaml 扩展; 不可用时用内置简易解析器 (仅针对本文件格式)。
     * 结果缓存于静态属性, 避免重复 I/O。
     *
     * @return array
     */
    private static function load_strategies(): array
    {
        if (self::$strategies_cache !== null) {
            return self::$strategies_cache;
        }

        $yaml_path = __DIR__ . '/config/fallback-strategies.yaml';

        if (!file_exists($yaml_path)) {
            self::$strategies_cache = [];
            return self::$strategies_cache;
        }

        $strategies = [];

        if (function_exists('yaml_parse_file')) {
            $parsed = yaml_parse_file($yaml_path);
            $strategies = is_array($parsed) && isset($parsed['strategies'])
                ? $parsed['strategies']
                : [];
        } else {
            // fallback: 简易 YAML 解析 (仅支持本文件格式: strategies 列表)
            $strategies = self::parse_strategies_yaml($yaml_path);
        }

        self::$strategies_cache = $strategies;
        return self::$strategies_cache;
    }

    /**
     * 简易 YAML 解析器 (仅针对 fallback-strategies.yaml 的格式)。
     *
     * 支持:
     *   - "strategies:" 顶层键
     *   - "  - tag: ..." 列表项开始
     *   - "    key: value" 标量字段 (字符串/整数)
     *
     * 不支持: 嵌套映射、多行字符串、引号转义等复杂 YAML 特性。
     * 如需复杂配置, 请安装 yaml 扩展。
     *
     * @param string $path
     * @return array
     */
    private static function parse_strategies_yaml(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $lines = explode("\n", $content);
        $strategies = [];
        $current = null;
        $in_strategies = false;

        foreach ($lines as $line) {
            // 跳过空行和注释
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            // 检测 strategies: 顶层键
            if ($trimmed === 'strategies:') {
                $in_strategies = true;
                continue;
            }

            if (!$in_strategies) {
                continue;
            }

            // 检测列表项开始: "  - tag: ..." (允许前导缩进)
            if (preg_match('/^\s*-\s+tag:\s*(.+)$/', $line, $m)) {
                if ($current !== null) {
                    $strategies[] = $current;
                }
                $current = ['tag' => trim($m[1])];
                continue;
            }

            // 检测字段: "    key: value" (至少1个前导空格, 排除列表项)
            if ($current !== null && preg_match('/^\s+(\w+):\s*(.*)$/', $line, $m)) {
                $key = $m[1];
                $val = trim($m[2]);
                // 整数字段
                if (in_array($key, ['risk', 'feas', 'nov'], true)) {
                    $current[$key] = (int) $val;
                } else {
                    $current[$key] = $val;
                }
            }
        }

        if ($current !== null) {
            $strategies[] = $current;
        }

        return $strategies;
    }
}
