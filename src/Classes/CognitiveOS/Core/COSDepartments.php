<?php

declare(strict_types=1);
/**
 * Cognitive Operating System — 五部门引擎 (v20.4)
 *
 * 五部门协同架构 (FP/EX/C/O/A):
 *   FP 部 (Foundational Premise)  — 定义公理和信息核
 *   EX 部 (Exploration)           — 生成方案种群 (真实 AI 生成 + 结构化降级)
 *   C  部 (Culling)               — 绞杀弱者 (风险>8 或可行<4 直接抹杀)
 *   O  部 (Observation)           — 检测盲区与幻觉
 *   A  部 (Archive)               — 结晶锁定 MVP, 提取固化规则, 物理归档
 *
 * v20.4 修复:
 *   - EX 部: 用真实 AI 调用替代 rand() 占位, 方案携带真实 approach 文本
 *   - A  部: 从 MVP 提取真实固化规则 (rules), 不再是空数组
 *   - AI 不可用时降级为结构化模板, 保证流水线不中断
 *
 * v20.4-fix3 拆分:
 *   - EX 部 (260行, 46%) 提取为独立类 COSExDepartment
 *   - 10 个 fallback 策略模板外置为 config/fallback-strategies.yaml
 *   - 本类保留为五部门门面, ex_department 委托 COSExDepartment::generate()
 *   - 4 个调用方 (COSEvolution/COSEngine/COSAjax/COSAjaxEvolve) 零修改
 *
 * @package Linked3\CognitiveOS\Core
 * @since   20.0
 * @patched 20.4
 * @split   20.4-fix3
 */

namespace Linked3\Classes\CognitiveOS\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class COSDepartments
 *
 * 五部门引擎 — 每个部门是一个独立的执行单元。
 */
class COSDepartments
{
    /**
     * FP 部 — 定义公理和信息核。
     *
     * @param array $input { problem: string, context: array }
     * @return array { department, status, deliverables, message }
     */
    public static function fp_department(array $input): array
    {
        $problem = $input['problem'] ?? '';
        $context = $input['context'] ?? [];

        if (empty($problem)) {
            return [
                'department'   => 'FP',
                'status'       => 'fail',
                'deliverables' => [],
                'message'      => __('FP部: 问题描述为空, 无法定义信息核', 'linked3-ai'),
            ];
        }

        // 信息核: 问题 + 上下文 + 约束
        $info_core = [
            'problem'       => $problem,
            'context'       => $context,
            'constraints'   => $context['constraints'] ?? [],
            'success_criteria' => $context['success_criteria'] ?? [],
            'entropy_before' => $context['entropy_before'] ?? 10,
        ];

        return [
            'department'   => 'FP',
            'status'       => 'pass',
            'deliverables' => ['info_core' => $info_core],
            'message'      => __('FP部: 信息核已定义', 'linked3-ai'),
        ];
    }

    /**
     * EX 部 — 生成方案种群 (真实 AI 生成 + 结构化降级)。
     *
     * v20.4-fix3: 逻辑提取至 COSExDepartment, 本方法保留为1行委托,
     * 确保 COSEvolution 等调用方零修改。
     *
     * @param array $input { info_core: array, generation: string, baseline: array }
     * @return array { department, status, deliverables, message }
     */
    public static function ex_department(array $input): array
    {
        return COSExDepartment::generate($input);
    }

    /**
     * C 部 — 绞杀弱者 (风险>8 或可行<4 直接抹杀)。
     *
     * @param array $input { variants: array }
     * @return array { department, status, deliverables, message }
     */
    public static function c_department(array $input): array
    {
        $variants = $input['variants'] ?? [];
        $survivors = [];
        $killed    = [];

        foreach ($variants as $v) {
            $score       = $v['score'] ?? [];
            $risk        = (int) ($score['risk'] ?? 0);
            $feasibility = (int) ($score['feasibility'] ?? 0);

            // 证伪至死: 风险>8 或 可行<4 直接抹杀
            if ($risk > 8 || $feasibility < 4) {
                $killed[] = [
                    'id'     => $v['id'],
                    'reason' => sprintf('风险%d>8 或 可行%d<4', $risk, $feasibility),
                ];
            } else {
                $survivors[] = $v;
            }
        }

        $status = !empty($survivors) ? 'pass' : 'kill';

        return [
            'department'   => 'C',
            'status'       => $status,
            'deliverables' => ['survivors' => $survivors, 'killed' => $killed],
            'message'      => sprintf('C部: 绞杀 %d 个, 存活 %d 个', count($killed), count($survivors)),
        ];
    }

    /**
     * O 部 — 盲区与用户观测站 (降维, 脱离行业语境查幻觉)。
     *
     * @param array $input { survivors: array }
     * @return array { department, status, deliverables, message }
     */
    public static function o_department(array $input): array
    {
        $survivors = $input['survivors'] ?? [];

        $blind_spots    = self::detect_blind_spots($survivors);
        $hallucinations = self::check_hallucinations($survivors);

        return [
            'department'   => 'O',
            'status'       => 'pass',
            'deliverables' => ['blind_spots' => $blind_spots, 'hallucinations' => $hallucinations],
            'message'      => sprintf('O部: 检测到 %d 个盲区, %d 个幻觉', count($blind_spots), count($hallucinations)),
        ];
    }

    /**
     * A 部 — 统筹与交付中心 (结晶, 锁定 MVP, 提取固化规则, 物理归档)。
     *
     * v20.4: 从 MVP 的 approach + steps 提取真实固化规则 (rules),
     * 不再是空数组或占位文本。
     *
     * @param array $input { survivors: array, generation: string, problem: string }
     * @return array { department, status, deliverables, message }
     */
    public static function a_department(array $input): array
    {
        $survivors  = $input['survivors'] ?? [];
        $generation = $input['generation'] ?? 'G1';
        $problem     = $input['problem'] ?? '';

        if (empty($survivors)) {
            return [
                'department'   => 'A',
                'status'       => 'fail',
                'deliverables' => [],
                'message'      => __('A部: 无存活方案, 无法结晶', 'linked3-ai'),
            ];
        }

        // 计算适应度 = sum(score)
        $best = null;
        $best_score = -1;
        foreach ($survivors as $v) {
            $score = array_sum($v['score'] ?? []);
            if ($score > $best_score) {
                $best_score = $score;
                $best = $v;
            }
        }

        // v20.4: 从 MVP 提取真实固化规则
        $rules = self::extract_rules($best);

        // 锁定 MVP
        $mvp = [
            'id'           => $best['id'],
            'generation'   => $generation,
            'problem'      => $problem,
            'approach'     => $best['approach'],
            'steps'        => $best['steps'] ?? '',
            'score'        => $best['score'],
            'fitness'      => $best_score,
            'rules'        => $rules,
            'source'       => $best['source'] ?? 'unknown',
            'locked_at'    => current_time('mysql'),
        ];

        return [
            'department'   => 'A',
            'status'       => 'pass',
            'deliverables' => ['mvp' => $mvp],
            'message'      => sprintf('A部: MVP 已锁定 (%s, 适应度 %d, 规则 %d 条)', $mvp['id'], $mvp['fitness'], count($rules)),
        ];
    }

    /**
     * v20.4: 从 MVP 方案提取固化规则。
     *
     * 将 approach + steps 拆解为可执行的规则列表,
     * 供 system_prompt 注入和后续生成器复用。
     *
     * @param array $mvp
     * @return array 规则字符串数组
     */
    public static function extract_rules(array $mvp): array
    {
        $rules = [];
        $approach = $mvp['approach'] ?? '';
        $steps    = $mvp['steps'] ?? '';

        // 从 steps 提取规则 (分号或换行分隔)
        if (!empty($steps)) {
            $step_arr = preg_split('/[;；\n]+/u', $steps);
            foreach ($step_arr as $idx => $step) {
                $step = trim($step);
                if (mb_strlen($step) >= 2) {
                    $rules[] = sprintf('步骤%d: %s', $idx + 1, $step);
                }
            }
        }

        // 从 approach 提取核心思路作为总纲规则
        if (!empty($approach) && mb_strlen($approach) > 10) {
            // 提取【标签】部分作为策略名
            if (preg_match('/【([^】]+)】/u', $approach, $m)) {
                $rules[] = '策略: ' . $m[1];
            }
            // 截取 approach 前 120 字作为核心思路
            $summary = mb_substr($approach, 0, 120);
            $rules[] = '核心思路: ' . $summary;
        }

        // 如果规则仍为空, 至少返回一条兜底
        if (empty($rules)) {
            $rules[] = '执行方案: ' . mb_substr($approach, 0, 100);
        }

        return $rules;
    }

    /**
     * 盲区检测 — 脱离行业语境, 检查隐性约束。
     */
    private static function detect_blind_spots(array $survivors): array
    {
        $spots = [];
        if (count($survivors) < 3) {
            $spots[] = '存活方案过少 (<3), 可能存在未探索的方案空间';
        }
        // 检查是否所有方案都来自同一思路
        $approaches = array_unique(array_map(function($v) { return $v['approach'] ?? ''; }, $survivors));
        if (count($approaches) < count($survivors) * 0.5) {
            $spots[] = '方案同质化严重, 缺乏多样性';
        }
        $spots[] = '未考虑失败案例的反向验证';
        return $spots;
    }

    /**
     * 幻觉检测 — 检查方案中是否存在脱离实际的假设。
     */
    private static function check_hallucinations(array $survivors): array
    {
        $hallucinations = [];
        foreach ($survivors as $v) {
            $approach = $v['approach'] ?? '';
            // 简单启发式: 检查是否包含"100%"、"绝对"、"一定"等过度自信词汇
            if (preg_match('/(100%|绝对|一定|必然|不可能失败)/u', $approach)) {
                $hallucinations[] = $v['id'] . ': 包含过度自信表述';
            }
        }
        return $hallucinations;
    }
}
