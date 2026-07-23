<?php

declare(strict_types=1);
/**
 * Cognitive Operating System — 双公理系统 (v20.0)
 *
 * 公理一·信息熵减: 每次操作后任务空间的不确定性必须降低
 * 公理二·系统降维: 将高维概念降维为可操作的循环
 *
 * 任何决策都必须经过双公理验证才能进入下一步。
 * 公理刚性不可违 — 证伪至死, 任一公理违反即抹杀。
 *
 * @package Linked3\CognitiveOS\Core
 * @since   20.0
 */

namespace Linked3\Classes\CognitiveOS\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class COSAxioms
 *
 * 双公理系统 — COS 的地基。
 */
class COSAxioms
{
    /**
     * 公理一: 信息熵减 — 操作后任务空间的不确定性必须降低。
     *
     * @param array $target {
     *     entropy_before: int  操作前的可能性数量
     *     entropy_after:  int  操作后的可能性数量
     *     operational_steps: array  可操作步骤列表
     * }
     * @return array { passed: bool, results: array, message: string }
     */
    public static function axiom_entropy_reduction(array $target): array
    {
        $before = (int) ($target['entropy_before'] ?? 0);
        $after  = (int) ($target['entropy_after'] ?? 0);
        $steps  = $target['operational_steps'] ?? [];

        $results = [
            'entropy_before'    => $before,
            'entropy_after'     => $after,
            'reduction_ratio'   => $before > 0 ? round(($before - $after) / $before, 4) : 0,
            'has_operational'   => !empty($steps),
        ];

        // 公理刚性: 熵必须减少 (after < before) 且有可操作步骤
        $passed = ($after < $before) && !empty($steps);
        $message = $passed
            ? sprintf('熵减通过: %d → %d (减 %.1f%%)', $before, $after, $results['reduction_ratio'] * 100)
            : sprintf('熵减失败: %d → %d (未降低或无可操作步骤)', $before, $after);

        return [
            'passed'  => $passed,
            'results'  => $results,
            'message' => $message,
        ];
    }

    /**
     * 公理二: 系统降维 — 高维概念必须降维为可操作的循环。
     *
     * @param array $target {
     *     high_dim_concept: string  高维概念描述
     *     operational_steps: array  降维后的可操作步骤
     *     is_cyclic: bool  是否形成循环
     * }
     * @return array { passed: bool, results: array, message: string }
     */
    public static function axiom_system_reduction(array $target): array
    {
        $concept = $target['high_dim_concept'] ?? '';
        $steps   = $target['operational_steps'] ?? [];
        $cyclic  = (bool) ($target['is_cyclic'] ?? false);

        $results = [
            'concept'         => $concept,
            'steps_count'     => count($steps),
            'is_cyclic'       => $cyclic,
            'avg_step_words'  => empty($steps) ? 0 : round(array_sum(array_map('str_word_count', array_map('strval', $steps))) / max(1, count($steps)), 1),
        ];

        // 公理刚性: 必须有 ≥2 个可操作步骤且形成循环
        $passed = count($steps) >= 2 && $cyclic;
        $message = $passed
            ? sprintf('降维通过: %d 步可操作循环', count($steps))
            : sprintf('降维失败: 仅 %d 步或未形成循环', count($steps));

        return [
            'passed'  => $passed,
            'results'  => $results,
            'message' => $message,
        ];
    }

    /**
     * 双公理联合验证 — 任一公理违反即抹杀 (证伪至死)。
     *
     * @param array $target
     * @return array { passed: bool, axiom1: array, axiom2: array, message: string }
     */
    public static function validate_both(array $target): array
    {
        $a1 = self::axiom_entropy_reduction($target);
        $a2 = self::axiom_system_reduction($target);

        $passed = $a1['passed'] && $a2['passed'];
        $message = $passed
            ? '双公理验证通过'
            : sprintf('双公理验证失败 — %s | %s', $a1['message'], $a2['message']);

        return [
            'passed'  => $passed,
            'axiom1'  => $a1,
            'axiom2'  => $a2,
            'message' => $message,
        ];
    }
}
