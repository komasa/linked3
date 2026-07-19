<?php
/**
 * Linked3 Agent Scheduler — Cron+队列调度
 *
 * @package Linked3\Agent\Scheduler
 * @since 5.5.0.8
 */
namespace Linked3\Classes\Agent\Scheduler;

if (!defined('ABSPATH')) exit;

class Linked3_Agent_Scheduler {
    private static ?Linked3_Agent_Scheduler $instance = null;
    private array $queue = [];

    public static function instance(): Linked3_Agent_Scheduler {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('linked3_agent_cron', [$this, 'processQueue']);
        if (!wp_next_scheduled('linked3_agent_cron')) {
            wp_schedule_event(time(), 'every_minute', 'linked3_agent_cron');
        }
    }

    public function enqueue(string $workflow, array $input = [], int $priority = 10): string {
        $id = uniqid('task_');
        $this->queue[] = [
            'id' => $id,
            'workflow' => $workflow,
            'input' => $input,
            'priority' => $priority,
            'added' => time(),
        ];
        usort($this->queue, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return $id;
    }

    public function processQueue(): void {
        if (empty($this->queue)) return;
        $task = array_shift($this->queue);
        $orchestrator = Linked3_Agent_Orchestrator::instance();
        $orchestrator->execute($task['workflow'], $task['input']);
    }

    public function getQueue(): array { return $this->queue; }
    public function clearQueue(): void { $this->queue = []; }
}

add_filter('cron_schedules', function($schedules) {
    $schedules['every_minute'] = ['interval' => 60, 'display' => 'Every Minute'];
    return $schedules;
});
