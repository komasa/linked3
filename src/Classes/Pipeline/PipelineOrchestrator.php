<?php

declare(strict_types=1);
/**
 * Pipeline Orchestrator — the production factory conductor.
 *
 * v5.1.0: coordinates the 5-stage production pipeline:
 *   collect → generate → enhance → publish → distribute
 *
 * Design principles:
 *   - The Orchestrator is a PURE coordination layer — it contains NO
 *     business logic. Each stage delegates to its respective module
 *     (Keyword_Manager, Content_Writer, SEO, Publish_Manager, etc.).
 *   - Stages are registered dynamically so modules can be disabled
 *     without breaking the pipeline.
 *   - A stage failure can be configured to either abort the pipeline
 *     or skip to the next stage (on_failure = 'abort' | 'skip').
 *   - Every stage completion fires do_action('linked3/pipeline/stage_complete')
 *     so modules can observe progress.
 *
 * @package Linked3
 * @subpackage Classes\Pipeline
 */

namespace Linked3\Classes\Pipeline;

if (!defined('ABSPATH')) {
    exit;
}

final class PipelineOrchestrator
{
    /** @var array<string, PipelineStageInterface> Registered stages. */
    private $stages = [];

    /** @var array The default 5-stage order. */
    const DEFAULT_ORDER = ['collect', 'generate', 'enhance', 'publish', 'distribute'];

    /**
     * Register a pipeline stage.
     *
     * @param PipelineStageInterface $stage
     * @return self
     */
    public function register_stage(PipelineStageInterface $stage): self
    {
        $this->stages[$stage->slug()] = $stage;
        return $this;
    }

    /**
     * Get all registered stage slugs.
     *
     * @return string[]
     */
    public function stage_slugs(): array
    {
        return array_keys($this->stages);
    }

    /**
     * Run the pipeline.
     *
     * @param array $pipeline_config  Configuration for the pipeline run:
     *   - stages: string[]  Which stages to run (default: all 5)
     *   - on_failure: string  'abort' (default) or 'skip'
     *   - content_template_id: int  The content template to use
     *   - pipeline_templates: array  Map of stage → template_id
     *   - brand_profile_id: int  V15 brand profile (v5.2+)
     * @param array $input  Initial input data (keyword, topic, etc.)
     * @return array{ok:bool, stages:array, data:array, errors:array}
     */
    public function run(array $pipeline_config, array $input = []): array
    {
        $stage_order = $pipeline_config['stages'] ?? self::DEFAULT_ORDER;
        $on_failure = $pipeline_config['on_failure'] ?? 'abort';

        $data = $input;
        $stage_results = [];
        $errors = [];
        $ok = true;

        foreach ($stage_order as $slug) {
            if (!isset($this->stages[$slug])) {
                $errors[] = sprintf('Stage "%s" not registered — skipping.', $slug);
                if ($on_failure === 'abort') {
                    $ok = false;
                    break;
                }
                continue;
            }

            $stage = $this->stages[$slug];
            $stage_config = $pipeline_config['stage_config'][$slug] ?? [];

            try {
                $result = $stage->execute($data, $stage_config);
                $stage_results[$slug] = $result;

                if (!$result['ok']) {
                    $errors[] = sprintf('Stage "%s" failed: %s', $slug, $result['message']);
                    if ($on_failure === 'abort') {
                        $ok = false;
                        break;
                    }
                    // 'skip' — merge partial data and continue.
                }

                // Merge stage data into the pipeline accumulator.
                if (is_array($result['data'])) {
                    $data = array_merge($data, $result['data']);
                }

                // Fire completion hook for observers.
                do_action('linked3/pipeline/stage_complete', $slug, $result, $data);

            } catch (\Throwable $e) {
                $errors[] = sprintf('Stage "%s" threw: %s', $slug, $e->getMessage());
                $stage_results[$slug] = [
                    'ok' => false,
                    'data' => [],
                    'message' => $e->getMessage(),
                ];
                if ($on_failure === 'abort') {
                    $ok = false;
                    break;
                }
            }
        }

        return [
            'ok'     => $ok,
            'stages' => $stage_results,
            'data'   => $data,
            'errors' => $errors,
        ];
    }

    /**
     * Schedule a pipeline run via AutoGPT cron.
     *
     * v5.3.4: registers the pipeline as a recurring AutoGPT task so it
     * runs on a schedule (e.g. "generate 3 articles per day from hot
     * keywords and publish them").
     *
     * @param array  $pipeline_config
     * @param string $schedule  Cron expression or WP schedule name.
     * @return int The AutoGPT task ID (0 on failure).
     */
    public function schedule_via_autogpt(array $pipeline_config, string $schedule = 'hourly'): int
    {
        if (!class_exists('\\Linked3\\Classes\\AutoGPT\\AutoGPTTaskRepository')) {
            return 0;
        }

        $repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
        $task_id = $repo->create([
            'user_id'   => get_current_user_id(),
            'task_type' => 'content-writing',
            'name'      => 'Pipeline: ' . ($pipeline_config['name'] ?? 'unnamed'),
            'config'    => [
                'pipeline'         => true,
                'pipeline_config'  => $pipeline_config,
                'schedule'         => $schedule,
            ],
            'schedule'  => $schedule,
        ]);

        return is_wp_error($task_id) ? 0 : (int) $task_id;
    }
}
