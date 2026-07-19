<?php

declare(strict_types=1);
/**
 * Linked3 Agent State Machine — 状态机引擎
 *
 * @package Linked3\Agent
 * @since 5.5.0.3
 */
namespace Linked3\Classes\Agent;

if (!defined('ABSPATH')) exit;

class AgentStateMachine {
    private array $states = [];
    private array $transitions = [];
    private string $current;
    private string $initial;
    private array $history = [];
    private $data = [];

    public function __construct(string $initialState) {
        $this->initial = $initialState;
        $this->current = $initialState;
        $this->addState($initialState);
    }

    public function addState(string $name): self {
        $this->states[$name] = $name;
        return $this;
    }

    public function addTransition(string $from, string $to, ?callable $guard = null): self {
        $this->transitions[$from][] = ['to' => $to, 'guard' => $guard];
        $this->addState($from);
        $this->addState($to);
        return $this;
    }

    public function transition(string $to): bool {
        $from = $this->current;
        if (!isset($this->transitions[$from])) return false;

        foreach ($this->transitions[$from] as $t) {
            if ($t['to'] !== $to) continue;
            if ($t['guard'] && !($t['guard'])($this->data)) return false;
            $this->history[] = ['from' => $from, 'to' => $to, 'time' => time()];
            $this->current = $to;
            linked3_dispatch('linked3.agent.state.transition', [
                'from' => $from, 'to' => $to, 'data' => $this->data
            ]);
            return true;
        }
        return false;
    }

    public function can(string $to): bool {
        if (!isset($this->transitions[$this->current])) return false;
        foreach ($this->transitions[$this->current] as $t) {
            if ($t['to'] === $to) {
                if ($t['guard'] && !($t['guard'])($this->data)) return false;
                return true;
            }
        }
        return false;
    }

    public function current(): string { return $this->current; }
    public function getData(): array { return $this->data; }
    public function setData(array $data): void { $this->data = $data; }
    public function reset(): void {
        $this->current = $this->initial;
        $this->history = [];
        $this->data = [];
    }
    public function getHistory(): array { return $this->history; }
}
