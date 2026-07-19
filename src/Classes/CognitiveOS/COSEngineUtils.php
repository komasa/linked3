<?php

declare(strict_types=1);
/**
 * CognitiveOS Engine Utils — v27.17.9-fix1 修复 fatal error.
 *
 * 修复内容:
 *   1. COS_PATCH_VERSION 常量未定义 → 从 COSEngine 获取
 *   2. self::$instance 未声明 → 添加 private static $instance
 *   3. COSEvolution / COSEvolutionArchive 未导入 → 添加 use
 */

namespace Linked3\Classes\CognitiveOS;

use Linked3\Classes\CognitiveOS\Core\COSEvolution;
use Linked3\Classes\CognitiveOS\Storage\COSEvolutionArchive;

if (!defined('ABSPATH')) exit;

class COSEngineUtils
{
    /** @var self|null */
    private static $instance = null;

    public static function patch_version(): string
    {
        // 从 COSEngine 获取版本，避免常量未定义 fatal error
        if (defined('\Linked3\Classes\CognitiveOS\COSEngine::COS_PATCH_VERSION')) {
            return constant('\Linked3\Classes\CognitiveOS\COSEngine::COS_PATCH_VERSION');
        }
        if (class_exists('\Linked3\Classes\CognitiveOS\COSEngine')) {
            return (string) \Linked3\Classes\CognitiveOS\COSEngine::COS_PATCH_VERSION;
        }
        return 'v27.17.9';
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function evolve(string $problem, array $context = []): array
    {
        // 运行演化
        $result = COSEvolution::evolve($problem, $context);

        // 归档每代快照
        foreach ($result['generations'] ?? [] as $gen) {
            COSEvolutionArchive::save_generation([
                'generation'     => $gen['generation'],
                'timestamp'      => current_time('mysql'),
                'problem'         => $problem,
                'variants_count'  => $gen['variants_count'] ?? 0,
                'survivors_count' => $gen['survivors_count'] ?? 0,
                'killed_count'    => $gen['killed_count'] ?? 0,
                'mvp'             => $gen['mvp'] ?? null,
                'blind_spots'     => $gen['blind_spots'] ?? [],
                'hallucinations'  => $gen['hallucinations'] ?? [],
                'axiom_results'   => $gen['axiom_check'] ?? [],
                'sla_results'     => $gen['sla_check'] ?? [],
                'status'          => $gen['status'] ?? 'unknown',
            ]);
        }

        // 如果演化成功, 结晶为 Skill
        if ($result['final_status'] === 'success' && !empty($result['final_mvp'])) {
            $this->crystallize_skill($problem, $context, $result['final_mvp'], $result['generations_summary'] ?? []);
        }

        return $result;
    }

    public function evolve_single_gen(string $problem, array $context, string $gen, ?array $baseline): array
    {
        $gen_result = COSEvolution::run_generation($gen, $problem, $context, $baseline);

        // 归档本代快照
        COSEvolutionArchive::save_generation([
            'generation'     => $gen_result['generation'] ?? $gen,
            'timestamp'      => current_time('mysql'),
            'problem'         => $problem,
            'variants_count'  => $gen_result['variants_count'] ?? 0,
            'survivors_count' => $gen_result['survivors_count'] ?? 0,
            'killed_count'    => $gen_result['killed_count'] ?? 0,
            'mvp'             => $gen_result['mvp'] ?? null,
            'blind_spots'     => $gen_result['blind_spots'] ?? [],
            'hallucinations'  => $gen_result['hallucinations'] ?? [],
            'axiom_results'   => $gen_result['axiom_check'] ?? [],
            'sla_results'     => $gen_result['sla_check'] ?? [],
            'status'          => $gen_result['status'] ?? 'unknown',
        ]);

        return $gen_result;
    }

    public function finalize_evolution(string $problem, array $context, array $final_mvp, array $generations_summary): array
    {
        $this->crystallize_skill($problem, $context, $final_mvp, $generations_summary);

        return [
            'final_status' => 'success',
            'final_mvp'    => $final_mvp,
            'generations_summary' => $generations_summary,
        ];
    }

}
