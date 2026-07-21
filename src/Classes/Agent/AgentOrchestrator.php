<?php

declare(strict_types=1);
/**
 * Linked3 Agent Orchestrator — Agent编排基座
 *
 * @package Linked3\Agent
 * @since 5.5.0.1
 */
namespace Linked3\Classes\Agent;

use Linked3\Includes\EventBus;

if (!defined('ABSPATH')) exit;

class AgentOrchestrator {
    private static ?AgentOrchestrator $instance = null;
    private array $workflows = [];
    private array $running = [];

    public static function instance(): AgentOrchestrator {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register(string $name, AgentWorkflowInterface $workflow): void {
        $this->workflows[$name] = $workflow;
    }

    public function execute(string $name, array $input = []): array {
        if (!isset($this->workflows[$name])) {
            throw new RuntimeException("Workflow not found: {$name}");
        }
        $workflow = $this->workflows[$name];
        $runId = uniqid('agent_', true);
        $this->running[$runId] = ['name' => $name, 'status' => 'running', 'started' => time()];

        EventBus::dispatch('linked3.agent.run.start', ['run_id' => $runId, 'name' => $name, 'input' => $input]);

        try {
            $result = $workflow->execute($input);
            $this->running[$runId]['status'] = 'completed';
            $this->running[$runId]['result'] = $result;
            EventBus::dispatch('linked3.agent.run.complete', ['run_id' => $runId, 'result' => $result]);
            return ['run_id' => $runId, 'status' => 'completed', 'result' => $result];
        } catch (Throwable $e) {
            $this->running[$runId]['status'] = 'failed';
            $this->running[$runId]['error'] = $e->getMessage();
            EventBus::dispatch('linked3.agent.run.failed', ['run_id' => $runId, 'error' => $e->getMessage()]);
            return ['run_id' => $runId, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

}

