<?php
/**
 * Linked3 Agent Task Definition — 任务DSL
 *
 * @package Linked3\Agent
 * @since 5.5.0.2
 */
namespace Linked3\Classes\Agent;

if (!defined('ABSPATH')) exit;

class Linked3_Agent_Task_Definition {
    private string $name;
    private string $description;
    private array $steps = [];
    private array $inputs = [];
    private array $outputs = [];
    private array $conditions = [];
    private array $retries = [];

    public function __construct(string $name, string $description = '') {
        $this->name = $name;
        $this->description = $description;
    }

    public function addStep(string $name, callable $handler, array $config = []): self {
        $this->steps[$name] = [
            'handler' => $handler,
            'config' => $config,
            'name' => $name,
        ];
        return $this;
    }

    public function input(string $name, string $type = 'string', $default = null): self {
        $this->inputs[$name] = ['type' => $type, 'default' => $default];
        return $this;
    }

    public function output(string $name, string $type = 'string'): self {
        $this->outputs[$name] = ['type' => $type];
        return $this;
    }

    public function condition(string $stepName, callable $condition): self {
        $this->conditions[$stepName] = $condition;
        return $this;
    }

    public function retry(string $stepName, int $maxRetries = 3, int $backoffSeconds = 5): self {
        $this->retries[$stepName] = ['max' => $maxRetries, 'backoff' => $backoffSeconds];
        return $this;
    }

    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getSteps(): array { return $this->steps; }
    public function getInputs(): array { return $this->inputs; }
    public function getOutputs(): array { return $this->outputs; }
    public function getConditions(): array { return $this->conditions; }
    public function getRetries(): array { return $this->retries; }

    public function validateInput(array $input): array {
        $validated = [];
        foreach ($this->inputs as $name => $spec) {
            $validated[$name] = $input[$name] ?? $spec['default'];
        }
        return $validated;
    }

    public function toArray(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'steps' => array_keys($this->steps),
            'inputs' => $this->inputs,
            'outputs' => $this->outputs,
        ];
    }
}
