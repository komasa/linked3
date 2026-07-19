<?php
/**
 * Cognitive Operating System — SLA 契约系统 (v20.0)
 *
 * 五部门之间的严格 SLA 契约:
 *   FP → EX: 信息核必须包含 problem + context (违约 → 回退到 FP)
 *   EX → C:  方案种群必须 ≥3 个 (违约 → 回退到 EX)
 *   C  → O:  存活方案必须 ≥1 个 (违约 → 回退到 EX, 增加变异)
 *   O  → A:  盲区必须 ≤2 个 (违约 → 回退到 EX, 补充方案)
 *   A  → 归档: MVP 必须有 fitness ≥10 (违约 → 回退到 G1)
 *
 * @package Linked3\CognitiveOS\Core
 * @since   20.0
 */

namespace Linked3\Classes\CognitiveOS\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Linked3_COS_SLA
 *
 * SLA 契约系统 — 部门间的服务级别协议。
 */
class Linked3_COS_SLA
{
    /**
     * SLA 契约表 — 每个契约定义违约条件和回退动作。
     */
    const SLA_CONTRACTS = [
        'FP_to_EX' => [
            'check'        => 'info_core_has_problem',
            'violation'    => '信息核缺少 problem 字段',
            'rollback_to'  => 'FP',
        ],
        'EX_to_C' => [
            'check'        => 'variants_count_gte_2',
            'violation'    => '方案种群少于 2 个',
            'rollback_to'  => 'EX',
        ],
        'C_to_O' => [
            'check'        => 'survivors_count_gte_1',
            'violation'    => '无存活方案',
            'rollback_to'  => 'EX',
        ],
        'O_to_A' => [
            'check'        => 'blind_spots_lte_2',
            'violation'    => '盲区超过 2 个',
            'rollback_to'  => 'EX',
        ],
        'A_to_archive' => [
            'check'        => 'mvp_fitness_gte_10',
            'violation'    => 'MVP 适应度低于 10',
            'rollback_to'  => 'G1',
        ],
    ];

    /**
     * 验证指定契约。
     *
     * @param string $contract_key 契约键 (如 'FP_to_EX')
     * @param array  $data         交付物数据
     * @return array { passed: bool, contract: string, message: string, rollback_to: string|null }
     */
    public static function validate(string $contract_key, array $data): array
    {
        if (!isset(self::SLA_CONTRACTS[$contract_key])) {
            return [
                'passed'       => false,
                'contract'     => $contract_key,
                'message'      => __('未知契约: ', 'linked3-ai') . $contract_key,
                'rollback_to'  => null,
            ];
        }

        $contract = self::SLA_CONTRACTS[$contract_key];
        $check    = $contract['check'];
        $passed   = self::$check($data);

        return [
            'passed'       => $passed,
            'contract'     => $contract_key,
            'message'      => $passed ? 'SLA 通过' : $contract['violation'],
            'rollback_to'  => $passed ? null : $contract['rollback_to'],
        ];
    }

    // ── 契约检查函数 ──────────────────────────────────────────

    private static function info_core_has_problem(array $data): bool
    {
        $core = $data['info_core'] ?? [];
        return !empty($core['problem']);
    }

    private static function variants_count_gte_2(array $data): bool
    {
        return count($data['variants'] ?? []) >= 2;
    }

    private static function survivors_count_gte_1(array $data): bool
    {
        return count($data['survivors'] ?? []) >= 1;
    }

    private static function blind_spots_lte_2(array $data): bool
    {
        return count($data['blind_spots'] ?? []) <= 2;
    }

    private static function mvp_fitness_gte_10(array $data): bool
    {
        $mvp = $data['mvp'] ?? [];
        return (int) ($mvp['fitness'] ?? 0) >= 10;
    }
}
