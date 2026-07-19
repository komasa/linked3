<?php

declare(strict_types=1);
/**
 * Cognitive Operating System — 演化循环引擎 (v20.0)
 *
 * 三代演化架构:
 *   G1 初代涌现 — 生成 10 个方案, 绞杀弱者, 结晶归档
 *   G2 重组变异 — 以 G1 结晶为基线, 交叉突变, 再次绞杀
 *   G3 终极坍缩 — 以 G2 结晶为基线, 收敛到 MVP
 *
 * 每代结晶后物理归档, 作为下一代变异的基线。
 *
 * @package Linked3\CognitiveOS\Core
 * @since   20.0
 */

namespace Linked3\Classes\CognitiveOS\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class COSEvolution
 *
 * 演化循环引擎 — 驱动 G1 → G2 → G3 三代演化。
 */
class COSEvolution
{
    /**
     * 运行完整三代演化。
     *
     * @param string $problem  待解决的问题
     * @param array  $context  上下文
     * @return array {
     *     final_status: string,
     *     final_mvp: array|null,
     *     generations: array,
     *     axiom_results: array,
     *     sla_results: array
     * }
     */
    public static function evolve(string $problem, array $context = []): array
    {
        $generations   = [];
        $axiom_results = [];
        $sla_results   = [];
        $final_mvp     = null;
        $final_status  = 'failed';

        $baseline = null; // 上一代结晶, 作为下一代变异基线

        foreach (['G1', 'G2', 'G3'] as $gen) {
            $gen_result = self::run_generation($gen, $problem, $context, $baseline);
            $generations[] = $gen_result;

            // 收集公理与 SLA 结果
            if (isset($gen_result['axiom_check'])) {
                $axiom_results[$gen] = $gen_result['axiom_check'];
            }
            if (isset($gen_result['sla_check'])) {
                $sla_results[$gen] = $gen_result['sla_check'];
            }

            if ($gen_result['status'] === 'pass' && !empty($gen_result['mvp'])) {
                $baseline   = $gen_result['mvp']; // 结晶作为下一代基线
                $final_mvp  = $gen_result['mvp'];
                $final_status = 'success';
            } else {
                $final_status = 'failed_at_' . $gen;
                break; // 任一代失败则终止
            }
        }

        // v20.4: 构建代际摘要 (供 Skill 库存储和前端展示)
        $generations_summary = [];
        foreach ($generations as $g) {
            $generations_summary[] = [
                'generation'      => $g['generation'] ?? '',
                'status'          => $g['status'] ?? '',
                'variants_count'  => $g['variants_count'] ?? 0,
                'survivors_count' => $g['survivors_count'] ?? 0,
                'killed_count'    => $g['killed_count'] ?? 0,
                'mvp_id'          => $g['mvp']['id'] ?? '',
                'mvp_fitness'     => $g['mvp']['fitness'] ?? 0,
                'mvp_approach'    => mb_substr($g['mvp']['approach'] ?? '', 0, 200),
            ];
        }

        return [
            'final_status'        => $final_status,
            'final_mvp'           => $final_mvp,
            'generations'         => $generations,
            'generations_summary' => $generations_summary,
            'axiom_results'       => $axiom_results,
            'sla_results'         => $sla_results,
            'evolved_at'          => current_time('mysql'),
        ];
    }

    /**
     * 运行单代演化 (FP → EX → C → O → A)。
     *
     * @param string $gen      代际标识 (G1/G2/G3)
     * @param string $problem  问题
     * @param array  $context  上下文
     * @param array|null $baseline  上一代结晶 (G2/G3 用)
     * @return array
     */
    public static function run_generation(string $gen, string $problem, array $context, ?array $baseline): array
    {
        // ── FP 部: 定义信息核 ──
        $fp = COSDepartments::fp_department([
            'problem' => $problem,
            'context' => array_merge($context, ['generation' => $gen, 'baseline' => $baseline]),
        ]);
        if ($fp['status'] !== 'pass') {
            return ['generation' => $gen, 'status' => 'fail', 'message' => $fp['message'], 'failed_at' => 'FP'];
        }
        $info_core = $fp['deliverables']['info_core'];

        // SLA: FP → EX
        $sla_fp_ex = COSSLA::validate('FP_to_EX', ['info_core' => $info_core]);
        if (!$sla_fp_ex['passed']) {
            return ['generation' => $gen, 'status' => 'fail', 'message' => $sla_fp_ex['message'], 'failed_at' => 'FP', 'sla_rollback' => $sla_fp_ex['rollback_to']];
        }

        // ── EX 部: 生成方案种群 (v20.4: 传入 baseline 供 G2/G3 变异) ──
        $ex = COSDepartments::ex_department([
            'info_core'  => $info_core,
            'generation' => $gen,
            'baseline'   => $baseline ?? [],
        ]);
        if ($ex['status'] !== 'pass') {
            return ['generation' => $gen, 'status' => 'fail', 'message' => $ex['message'], 'failed_at' => 'EX'];
        }
        $variants = $ex['deliverables']['variants'];

        // SLA: EX → C
        $sla_ex_c = COSSLA::validate('EX_to_C', ['variants' => $variants]);
        if (!$sla_ex_c['passed']) {
            return ['generation' => $gen, 'status' => 'fail', 'message' => $sla_ex_c['message'], 'failed_at' => 'EX', 'sla_rollback' => $sla_ex_c['rollback_to']];
        }

        // ── C 部: 绞杀弱者 ──
        $c = COSDepartments::c_department(['variants' => $variants]);
        $survivors = $c['deliverables']['survivors'];
        $killed    = $c['deliverables']['killed'];

        // SLA: C → O
        $sla_c_o = COSSLA::validate('C_to_O', ['survivors' => $survivors]);
        if (!$sla_c_o['passed']) {
            return ['generation' => $gen, 'status' => 'fail', 'message' => $sla_c_o['message'], 'failed_at' => 'C', 'sla_rollback' => $sla_c_o['rollback_to']];
        }

        // ── O 部: 盲区检测 ──
        $o = COSDepartments::o_department(['survivors' => $survivors]);
        $blind_spots    = $o['deliverables']['blind_spots'];
        $hallucinations = $o['deliverables']['hallucinations'];

        // SLA: O → A
        $sla_o_a = COSSLA::validate('O_to_A', ['blind_spots' => $blind_spots]);
        if (!$sla_o_a['passed']) {
            return ['generation' => $gen, 'status' => 'fail', 'message' => $sla_o_a['message'], 'failed_at' => 'O', 'sla_rollback' => $sla_o_a['rollback_to']];
        }

        // ── A 部: 结晶锁定 MVP ──
        $a = COSDepartments::a_department([
            'survivors'  => $survivors,
            'generation' => $gen,
            'problem'    => $problem,
        ]);
        if ($a['status'] !== 'pass') {
            return ['generation' => $gen, 'status' => 'fail', 'message' => $a['message'], 'failed_at' => 'A'];
        }
        $mvp = $a['deliverables']['mvp'];

        // SLA: A → 归档
        $sla_a_arch = COSSLA::validate('A_to_archive', ['mvp' => $mvp]);
        if (!$sla_a_arch['passed']) {
            return ['generation' => $gen, 'status' => 'fail', 'message' => $sla_a_arch['message'], 'failed_at' => 'A', 'sla_rollback' => $sla_a_arch['rollback_to']];
        }

        // ── 双公理验证 (v20.4: 用 steps 作为可操作步骤) ──
        $op_steps = !empty($mvp['steps'])
            ? array_filter(array_map('trim', preg_split('/[;；\n]+/u', $mvp['steps'])))
            : [$mvp['approach']];
        $axiom = COSAxioms::validate_both([
            'entropy_before'    => count($variants),
            'entropy_after'     => count($survivors),
            'operational_steps' => $op_steps,
            'high_dim_concept'  => $problem,
            'is_cyclic'         => true,
        ]);

        return [
            'generation'   => $gen,
            'status'        => 'pass',
            'message'       => sprintf('%s 完成: %s', $gen, $a['message']),
            'mvp'           => $mvp,
            'variants_count' => count($variants),
            'survivors_count' => count($survivors),
            'killed_count'  => count($killed),
            'blind_spots'   => $blind_spots,
            'hallucinations' => $hallucinations,
            'axiom_check'   => $axiom,
            'sla_check'     => [
                'FP_to_EX'  => $sla_fp_ex,
                'EX_to_C'   => $sla_ex_c,
                'C_to_O'    => $sla_c_o,
                'O_to_A'    => $sla_o_a,
                'A_to_archive' => $sla_a_arch,
            ],
        ];
    }
}
