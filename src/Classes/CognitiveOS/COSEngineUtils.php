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
use Linked3\Classes\CognitiveOS\Core\COSDepartments;
use Linked3\Classes\CognitiveOS\Storage\COSEvolutionArchive;
use Linked3\Classes\CognitiveOS\Storage\COSSkillLibrary;

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
        return 'v27.6.19';
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

    /**
     * 结晶 Skill — 将演化 MVP 固化为可复用的 Skill。
     *
     * v27.17.9-fix2: 从 COSEngine 提取到 COSEngineUtils, 修复
     * "Call to undefined method crystallize_skill" 错误。
     *
     * @param string $problem            原始问题。
     * @param array  $context            上下文。
     * @param array  $mvp                最优变体。
     * @param array  $generations_summary 各代摘要。
     * @return void
     */
    private function crystallize_skill(string $problem, array $context, array $mvp, array $generations_summary): void
    {
        $domain_slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($context['domain'] ?? 'general')) ?: 'general';
        $short_hash = substr(md5($problem . microtime(true)), 0, 6);
        $skill_name = $domain_slug . '_skill_' . $short_hash;

        $rules = COSDepartments::extract_rules($mvp);

        COSSkillLibrary::save($skill_name, [
            'domain'       => !empty($context['domain']) ? $context['domain'] : 'general',
            'rules'        => $rules,
            'fitness'      => (float) ($mvp['fitness'] ?? 5.0),
            'problem'      => $problem,
            'mvp_id'       => $mvp['id'] ?? '',
            'mvp_approach' => $mvp['approach'] ?? '',
            'mvp_steps'    => $mvp['steps'] ?? '',
            'mvp_scores'   => [
                'risk'        => $mvp['score']['risk'] ?? 0,
                'feasibility' => $mvp['score']['feasibility'] ?? 0,
                'novelty'     => $mvp['score']['novelty'] ?? 0,
            ],
            'generations_summary' => $generations_summary,
            'created_at'   => current_time('mysql'),
        ]);

        // G4.4: Trigger fitness recalculation after new SKILL generation
        if (class_exists("\\Linked3\\Classes\\MetaLever\\MetaLeverFitnessTracker")) {
            \Linked3\Classes\MetaLever\MetaLeverFitnessTracker::recalculate();
        }
    }

}
