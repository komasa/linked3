<?php

declare(strict_types=1);
/**
 * Linked3 Agent Workflow — 采集→改写→SEO→发布 管线
 *
 * @package Linked3\Agent\Workflow
 * @since 5.5.0.4
 */
namespace Linked3\Classes\Agent\Workflow;

use Linked3\Classes\Agent\AgentWorkflowInterface;



if (!defined('ABSPATH')) exit;
// 确保接口在实现类之前加载 (glob_scan 按字母排序, interface- 排在 class- 之后)
if (!interface_exists('Linked3\Classes\Agent\AgentWorkflowInterface')) {
    require_once dirname(__DIR__) . '/AgentWorkflow.php';
}

class AgentContentPipeline implements AgentWorkflowInterface {
    private array $steps = [
        'collect' => '采集源内容',
        'rewrite' => 'AI改写',
        'seo' => 'SEO优化',
        'publish' => '发布',
    ];

    public function getName(): string { return 'content_pipeline'; }
    public function getSteps(): array { return $this->steps; }

    public function execute(array $input): array {
        $state = new \Linked3\Classes\Agent\AgentStateMachine('idle');
        $state->addState('collecting')->addState('rewriting')->addState('seo')->addState('publishing')->addState('done')->addState('failed');
        $state->addTransition('idle', 'collecting');
        $state->addTransition('collecting', 'rewriting');
        $state->addTransition('rewriting', 'seo');
        $state->addTransition('seo', 'publishing');
        $state->addTransition('publishing', 'done');
        $state->addTransition('collecting', 'failed');
        $state->addTransition('rewriting', 'failed');
        $state->addTransition('seo', 'failed');

        $result = ['steps' => []];

        // Step 1: 采集
        $state->transition('collecting');
        try {
            $collected = $this->collect($input);
            $result['steps']['collect'] = $collected;
            $state->transition('rewriting');
        } catch (Throwable $e) {
            $state->transition('failed');
            return ['status' => 'failed', 'step' => 'collect', 'error' => $e->getMessage()];
        }

        // Step 2: 改写
        try {
            $rewritten = $this->rewrite($collected);
            $result['steps']['rewrite'] = $rewritten;
            $state->transition('seo');
        } catch (Throwable $e) {
            $state->transition('failed');
            return ['status' => 'failed', 'step' => 'rewrite', 'error' => $e->getMessage()];
        }

        // Step 3: SEO
        try {
            $seoData = $this->seoOptimize($rewritten);
            $result['steps']['seo'] = $seoData;
            $state->transition('publishing');
        } catch (Throwable $e) {
            $state->transition('failed');
            return ['status' => 'failed', 'step' => 'seo', 'error' => $e->getMessage()];
        }

        // Step 4: 发布
        try {
            $published = $this->publish($rewritten, $seoData);
            $result['steps']['publish'] = $published;
            $state->transition('done');
        } catch (Throwable $e) {
            $state->transition('failed');
            return ['status' => 'failed', 'step' => 'publish', 'error' => $e->getMessage()];
        }

        $result['status'] = 'completed';
        $result['state_history'] = $state->getHistory();
        return $result;
    }

    private function collect(array $input): array {
        $url = $input['url'] ?? '';
        $topic = $input['topic'] ?? '';
        return ['url' => $url, 'topic' => $topic, 'raw_content' => $input['content'] ?? ''];
    }

    private function rewrite(array $collected): array {
        // 委托给现有 AI Dispatcher
        if (class_exists('\Linked3\Classes\Agent\Workflow\AIDispatcher')) {
            $dispatcher = AIDispatcher::instance();
            $prompt = "请改写以下内容:\n\n" . ($collected['raw_content'] ?: $collected['topic']);
            try { // v19.3.0: AI 调用容错
                $result = $dispatcher->chat(
                    [['role' => 'user', 'content' => $prompt]],
                    ['temperature' => 0.7, 'max_tokens' => 2000, 'module' => 'content'],
                    ['fallback_providers' => []]
                );
            } catch (\Throwable $e) {
                return ['content' => $collected['raw_content'], 'usage' => [], 'error' => $e->getMessage()];
            }
            return ['content' => $result['content'] ?? '', 'usage' => $result['usage'] ?? []];
        }
        return ['content' => $collected['raw_content'], 'usage' => []];
    }

    private function seoOptimize(array $rewritten): array {
        return [
            'title' => mb_substr($rewritten['content'], 0, 50),
            'meta_description' => mb_substr($rewritten['content'], 0, 160),
            'keywords' => [],
        ];
    }

    private function publish(array $rewritten, array $seo): array {
        return ['post_id' => 0, 'status' => 'draft', 'title' => $seo['title']];
    }
}
