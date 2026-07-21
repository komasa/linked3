<?php

declare(strict_types=1);
/**
 * Linked3 Agent Task Definition — 任务DSL
 *
 * @package Linked3\Agent
 * @since 5.5.0.2
 */
namespace Linked3\Classes\Agent;

if (!defined('ABSPATH')) exit;

class AgentTaskDefinition {
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
}
