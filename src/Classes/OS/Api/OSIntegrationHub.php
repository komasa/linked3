<?php

declare(strict_types=1);
/**
 * Linked3 V18 Integration Hub v13.0.0
 *
 * V18方法论全量集成中心 — 统一调度10个v12.x模块
 *
 * 来源: V18 全维教科书 (10篇46章) → Linked3 v13.0.0
 *
 * 集成的10个模块:
 *   v12.0.0 OSReverseEngine          逆向8维度通用框架引擎
 *   v12.1.0 OSCapabilityLock      李善友能所结构融入
 *   v12.2.0 OSVisualAnalytics          SVG原子级meta统计引擎
 *   v12.3.0 OSConsciousnessLayer 三层能观Badge频率标注
 *   v12.4.0 OSOnboardingTracker          入流四状态100天追踪器
 *   v12.5.0 OSEngineerRegistry 31类逆向工程师注册中心
 *   v12.6.0 OSTextCreation   逆向文本创作8维度
 *   v12.7.0 OSMomentumFlywheel       洪流公式出图飞轮量化
 *   v12.8.0 OSCapabilityStages   能知三阶认知层级映射
 *   v12.9.0 OSQualityGate    逆向质量门禁系统
 *
 * 核心能力:
 *   1. get_all_modules(): 获取所有已集成模块
 *   2. run_full_pipeline(): 运行V18全量集成流水线
 *   3. get_integration_status(): 获取集成状态
 *   4. health_check(): 全模块健康检查
 *
 * @package Linked3\Integration
 * @since 13.0.0
 * @version 13.0.0
 */

namespace Linked3\Classes\OS\Api;

/**
 * OS Module — Integration Hub
 *
 * Migrated from V18 实验室 in v27.0.0.
 * Original file: src/Classes/V18/Api/V18IntegrationHub.php
 * Original class: OSIntegrationHub
 *
 * @package Linked3\Classes\OS
 */




if (!defined('ABSPATH')) exit;

class OSIntegrationHub {

    /**
     * 10个已集成模块清单
     */
    const INTEGRATED_MODULES = [
        'v12.0.0' => [
            'class' => 'OSReverseEngine',
            'file' => 'ReverseEngine.php',
            'path' => 'src/Classes/Reverse/',
            'title' => '逆向8维度通用框架引擎',
            'v18_source' => '道篇2.3+术篇8.2',
        ],
        'v12.1.0' => [
            'class' => 'OSCapabilityLock',
            'file' => 'NengSuoStructure.php',
            'path' => 'src/Classes/Philosophy/',
            'title' => '李善友能所结构融入',
            'v18_source' => '道篇2.4',
        ],
        'v12.2.0' => [
            'class' => 'OSVisualAnalytics',
            'file' => 'SvgMetaStats.php',
            'path' => 'src/Classes/SvgStats/',
            'title' => 'SVG原子级meta统计引擎',
            'v18_source' => '法篇6+术篇13',
        ],
        'v12.3.0' => [
            'class' => 'OSConsciousnessLayer',
            'file' => 'ThreeLayerConsciousness.php',
            'path' => 'src/Classes/Philosophy/',
            'title' => '三层能观Badge频率标注',
            'v18_source' => '道篇2.5',
        ],
        'v12.4.0' => [
            'class' => 'OSOnboardingTracker',
            'file' => 'RuLiuTracker.php',
            'path' => 'src/Classes/Philosophy/',
            'title' => '入流四状态100天追踪器',
            'v18_source' => '道篇2.7+行篇33.3',
        ],
        'v12.5.0' => [
            'class' => 'OSEngineerRegistry',
            'file' => 'ReverseEngineerRegistry.php',
            'path' => 'src/Classes/Reverse/',
            'title' => '31类逆向工程师注册中心',
            'v18_source' => '道篇2.3+附录A',
        ],
        'v12.6.0' => [
            'class' => 'OSTextCreation',
            'file' => 'ReverseTextCreation.php',
            'path' => 'src/Classes/Reverse/',
            'title' => '逆向文本创作8维度',
            'v18_source' => '用篇28-30',
        ],
        'v12.7.0' => [
            'class' => 'OSMomentumFlywheel',
            'file' => 'HongLiuFlywheel.php',
            'path' => 'src/Classes/Philosophy/',
            'title' => '洪流公式出图飞轮量化',
            'v18_source' => '道篇2.6',
        ],
        'v12.8.0' => [
            'class' => 'OSCapabilityStages',
            'file' => 'NengZhiThreeStages.php',
            'path' => 'src/Classes/Philosophy/',
            'title' => '能知三阶认知层级映射',
            'v18_source' => '道篇2.5',
        ],
        'v12.9.0' => [
            'class' => 'OSQualityGate',
            'file' => 'ReverseQualityGate.php',
            'path' => 'src/Classes/Reverse/',
            'title' => '逆向质量门禁系统',
            'v18_source' => '验篇41.4',
        ],
    ];

    /**
     * 获取所有已集成模块
     */
    public static function get_all_modules(): array {
        return self::INTEGRATED_MODULES;
    }

    /**
     * 获取集成状态
     */
    public static function get_integration_status(): array {
        $status = [
            'total_modules' => count(self::INTEGRATED_MODULES),
            'loaded_modules' => 0,
            'missing_modules' => [],
            'module_details' => [],
        ];

        foreach (self::INTEGRATED_MODULES as $version => $info) {
            $class_exists = class_exists($info['class']);
            $status['module_details'][$version] = [
                'title' => $info['title'],
                'class' => $info['class'],
                'v18_source' => $info['v18_source'],
                'loaded' => $class_exists,
            ];
            if ($class_exists) {
                $status['loaded_modules']++;
            } else {
                $status['missing_modules'][] = $version;
            }
        }

        $status['all_loaded'] = $status['loaded_modules'] === $status['total_modules'];
        return $status;
    }

    /**
     * 全模块健康检查
     */
    public static function health_check(): array {
        $checks = [];

        // 检查每个模块的get_version_info方法
        foreach (self::INTEGRATED_MODULES as $version => $info) {
            $class = $info['class'];
            if (class_exists($class) && method_exists($class, 'get_version_info')) {
                try {
                    $vinfo = call_user_func([$class, 'get_version_info']);
                    $checks[$version] = [
                        'status' => 'healthy',
                        'version_info' => $vinfo,
                    ];
                } catch (\Throwable $e) {
                    $checks[$version] = [
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            } else {
                $checks[$version] = [
                    'status' => 'missing',
                    'error' => "类 {$class} 未加载或缺少 get_version_info 方法",
                ];
            }
        }

        $healthy_count = count(array_filter($checks, fn($c) => $c['status'] === 'healthy'));
        return [
            'total' => count(self::INTEGRATED_MODULES),
            'healthy' => $healthy_count,
            'all_healthy' => $healthy_count === count(self::INTEGRATED_MODULES),
            'checks' => $checks,
        ];
    }

    /**
     * 运行V18全量集成流水线
     *
     * REFACTORED v27.0.0 (P3): Previously a 117-line monolith. Now delegates
     * each stage to a dedicated private method for testability and readability.
     *
     * @param array $input 输入数据
     * @return array 流水线结果
     */
    public static function run_full_pipeline(array $input): array {
        $result = [
            'input'        => $input,
            'stages'       => [],
            'final_output' => null,
            'errors'       => [],
        ];

        // Stage 1: 逆向拆解 (v12.0.0)
        self::run_stage_reverse_parse($input, $result);

        // Stage 2: 能知约束注入 (v12.1.0)
        self::run_stage_capability_constraint($input, $result);

        // Stage 3: SVG统计预测 (v12.2.0)
        self::run_stage_visual_prediction($input, $result);

        // Stage 4: 频率标注 (v12.3.0)
        self::run_stage_frequency_badge($input, $result);

        // Stage 5: 飞轮量化 (v12.7.0)
        self::run_stage_flywheel_score($input, $result);

        // Stage 6: 质量门禁 (v12.9.0)
        self::run_stage_quality_gate($input, $result);

        $result['final_output'] = [
            'pipeline_version' => '13.0.0',
            'stages_completed' => count(array_filter($result['stages'], fn($s) => $s['status'] === 'ok')),
            'stages_total'     => count($result['stages']),
            'has_errors'       => !empty($result['errors']),
        ];

        return $result;
    }

    /**
     * Stage 1: 逆向拆解 — 使用逆向8维度引擎构建提示词.
     *
     * @param array $input  输入数据
     * @param array $result 流水线结果（引用传递，直接写入）
     */
    private static function run_stage_reverse_parse(array $input, array &$result): void
    {
        if (!class_exists('\Linked3\Classes\OS\Core\OSReverseEngine')) {
            return;
        }
        try {
            $reverse_prompt = \OSReverseDimensions::build_reverse_prompt(
                $input['engineer_type'] ?? '视觉系统',
                $input['target'] ?? ''
            );
            $result['stages']['reverse_parse'] = [
                'status' => 'ok',
                'prompt' => $reverse_prompt,
            ];
        } catch (\Throwable $e) {
            $result['errors'][] = "Stage reverse_parse: " . $e->getMessage();
            $result['stages']['reverse_parse'] = ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Stage 2: 能知约束注入 — 根据内容类型派生能力约束.
     *
     * @param array $input  输入数据
     * @param array $result 流水线结果（引用传递）
     */
    private static function run_stage_capability_constraint(array $input, array &$result): void
    {
        if (!class_exists('\Linked3\Classes\OS\Core\OSCapabilityLock')) {
            return;
        }
        try {
            $constraint = \OSCapabilityLock::derive_from_content_type(
                $input['content_type'] ?? 'T1'
            );
            $result['stages']['neng_constraint'] = [
                'status'     => 'ok',
                'constraint' => $constraint,
            ];
        } catch (\Throwable $e) {
            $result['errors'][] = "Stage neng_constraint: " . $e->getMessage();
            $result['stages']['neng_constraint'] = ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Stage 3: SVG统计预测 — 预测图表原子数量.
     *
     * @param array $input  输入数据
     * @param array $result 流水线结果（引用传递）
     */
    private static function run_stage_visual_prediction(array $input, array &$result): void
    {
        if (!class_exists('\Linked3\Classes\OS\Core\OSVisualAnalytics')) {
            return;
        }
        try {
            $prediction = \OSVisualAnalytics::predict_atom_count(
                $input['chart_type'] ?? 'Framework'
            );
            $result['stages']['svg_prediction'] = [
                'status'     => 'ok',
                'prediction' => $prediction,
            ];
        } catch (\Throwable $e) {
            $result['errors'][] = "Stage svg_prediction: " . $e->getMessage();
            $result['stages']['svg_prediction'] = ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Stage 4: 频率标注 — 三层意识频率徽章分配.
     *
     * @param array $input  输入数据
     * @param array $result 流水线结果（引用传递）
     */
    private static function run_stage_frequency_badge(array $input, array &$result): void
    {
        if (!class_exists('\Linked3\Classes\OS\Core\OSConsciousnessLayer')) {
            return;
        }
        try {
            $badge = \OSConsciousnessLayer::assign_frequency(
                $input['module_name'] ?? '核心模块',
                $input['cognitive_level'] ?? 'R'
            );
            $result['stages']['frequency_badge'] = [
                'status' => 'ok',
                'badge'  => $badge,
            ];
        } catch (\Throwable $e) {
            $result['errors'][] = "Stage frequency_badge: " . $e->getMessage();
            $result['stages']['frequency_badge'] = ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Stage 5: 飞轮量化 — 洪流公式飞轮得分计算.
     *
     * @param array $input  输入数据
     * @param array $result 流水线结果（引用传递）
     */
    private static function run_stage_flywheel_score(array $input, array &$result): void
    {
        if (!class_exists('\Linked3\Classes\OS\Core\OSMomentumFlywheel')) {
            return;
        }
        try {
            $flywheel_score = \OSMomentumFlywheel::calculate_flywheel_score([
                'template_potential' => $input['template_potential'] ?? 70,
                'human_design'       => $input['human_design'] ?? 70,
                'ai_execution'       => $input['ai_execution'] ?? 70,
            ]);
            $result['stages']['flywheel_score'] = [
                'status' => 'ok',
                'score'  => $flywheel_score,
            ];
        } catch (\Throwable $e) {
            $result['errors'][] = "Stage flywheel_score: " . $e->getMessage();
            $result['stages']['flywheel_score'] = ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Stage 6: 质量门禁 — 逆向质量报告生成.
     *
     * @param array $input  输入数据
     * @param array $result 流水线结果（引用传递）
     */
    private static function run_stage_quality_gate(array $input, array &$result): void
    {
        if (!class_exists('\Linked3\Classes\OS\Core\OSQualityGate')) {
            return;
        }
        try {
            $quality_report = \OSQualityGate::generate_quality_report(
                $input['reverse_result'] ?? []
            );
            $result['stages']['quality_gate'] = [
                'status' => 'ok',
                'report' => $quality_report,
            ];
        } catch (\Throwable $e) {
            $result['errors'][] = "Stage quality_gate: " . $e->getMessage();
            $result['stages']['quality_gate'] = ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取版本信息
     */
    public static function get_version_info(): array {
        return [
            'hub_version' => '13.0.0',
            'integrated_modules' => count(self::INTEGRATED_MODULES),
            'v18_source' => 'V18 逆向思维×李善友方法论×SVG统计 全维教科书',
            'baseline' => 'v11.9.1',
            'evolution_path' => 'v11.9.1 → v12.0.0 → ... → v12.9.0 → v13.0.0',
        ];
    }
}
