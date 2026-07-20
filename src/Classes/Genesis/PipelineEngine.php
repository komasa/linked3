<?php

declare(strict_types=1);
/**
 * Linked3 Pipeline Engine — YAML驱动的生产管线引擎 (独立类)
 *
 * v10.4.5 (方案A) 新增: Pipeline引擎, 独立类无继承
 *
 * 设计原理 (公理K: 独立类零继承):
 *   - 本类是独立类, 无extends, 加载顺序安全
 *   - 移植自 feicai4.0 的 Pipeline 引擎 (PHP化)
 *
 * 核心能力 (公理G/H):
 *   • YAML配置驱动 — 步骤定义在YAML, 代码只执行 (公理H)
 *   • 中断恢复 — 长流程可断点续作, 状态持久化 (公理G)
 *   • 步骤编排 — load_seed → compile → project → check → adapt
 *   • 可选步骤 — optional=true的步骤失败可跳过
 *
 * @package Linked3\Genesis
 * @since 10.4.5
 * @version 10.4.5
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class PipelineEngine {

    /** @var string 管线名称 */
    private $name;

    /** @var array 步骤定义 (来自YAML) */
    private $steps = [];

    /** @var string 状态持久化key前缀 */
    private $state_key_prefix = 'linked3_pipeline_';

    /**
     * 构造: 加载YAML管线配置
     */
    public function __construct(string $name, ?string $yaml_file = null) {
        $this->name = $name;
        if ($yaml_file === null) {
            $yaml_file = __DIR__ . '/pipelines/' . $name . '.yaml';
        }
        $this->steps = $this->load_yaml($yaml_file);
    }

    /**
     * 执行管线
     */
    public function execute(array $context = [], ?callable $on_step_start = null, ?callable $on_step_done = null, ?callable $on_step_error = null): array {
        $results = [];
        $resume_from = $context['_resume_from'] ?? 0;
        unset($context['_resume_from']);

        $total = count($this->steps);
        for ($i = $resume_from; $i < $total; $i++) {
            $step = $this->steps[$i];
            $step_name = $step['name'] ?? "step_{$i}";

            if ($on_step_start) {
                $on_step_start($step, $context);
            }

            try {
                $result = $this->execute_step($step, $context, $results);
                $results[$step_name] = $result;

                if ($on_step_done) {
                    $on_step_done($step, $result, $context);
                }

                if ($result['status'] !== 'completed') {
                    $this->save_state($context, $results, $i);
                    return [
                        'status' => 'partial',
                        'context' => $context,
                        'results' => $results,
                        'failed_at' => $i,
                        'failed_step' => $step_name,
                        'error' => $result['error'] ?? 'Step did not complete',
                    ];
                }

            } catch (\Throwable $e) {
                $error_msg = $e->getMessage();

                $continue = false;
                if ($on_step_error) {
                    $continue = (bool) $on_step_error($step, $error_msg, $context);
                }

                if ($continue || !empty($step['optional'])) {
                    $results[$step_name] = [
                        'status' => 'failed',
                        'skipped' => true,
                        'error' => $error_msg,
                    ];
                    continue;
                }

                $this->save_state($context, $results, $i);
                return [
                    'status' => 'partial',
                    'context' => $context,
                    'results' => $results,
                    'failed_at' => $i,
                    'failed_step' => $step_name,
                    'error' => $error_msg,
                ];
            }
        }

        $this->clear_state();
        return [
            'status' => 'completed',
            'context' => $context,
            'results' => $results,
            'failed_at' => null,
        ];
    }

    /**
     * 恢复中断的管线
     */
    public function resume(array $override_context = []): array {
        $state = $this->load_state();
        if ($state === null) {
            return ['status' => 'failed', 'error' => 'No saved state to resume'];
        }
        $context = array_merge($state['context'], $override_context);
        $context['_resume_from'] = $state['failed_at'];
        return $this->execute($context);
    }

    /**
     * 执行单个步骤
     */
    private function execute_step(array $step, array $context, array $previous_results): array {
        $handler = $step['handler'] ?? null;
        if ($handler === null) {
            return ['status' => 'failed', 'error' => "Step '{$step['name']}' has no handler"];
        }

        $handler_parts = explode('|', $handler, 2);
        $callable = $handler_parts[0];
        $extra_params = [];
        if (isset($handler_parts[1])) {
            parse_str($handler_parts[1], $extra_params);
        }

        $params = array_merge($step['params'] ?? [], $extra_params);

        if (strpos($callable, '::') !== false) {
            list($class, $method) = explode('::', $callable, 2);
            if (!class_exists($class) || !method_exists($class, $method)) {
                return ['status' => 'failed', 'error' => "Handler {$callable} not found"];
            }
            $result = call_user_func([$class, $method], $context, $params, $previous_results);
        } elseif (function_exists($callable)) {
            $result = $callable($context, $params, $previous_results);
        } else {
            return ['status' => 'failed', 'error' => "Handler {$callable} not callable"];
        }

        if (!is_array($result)) {
            $result = ['status' => 'completed', 'output' => ['result' => $result]];
        }
        if (!isset($result['status'])) {
            $result['status'] = 'completed';
        }

        return $result;
    }

    /**
     * 保存管线状态 (中断点)
     */
    private function save_state(array $context, array $results, int $failed_at): void {
        $state = [
            'pipeline' => $this->name,
            'context' => $context,
            'results' => $results,
            'failed_at' => $failed_at,
            'saved_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'timestamp' => time(),
        ];

        $key = $this->state_key_prefix . $this->name;

        if (function_exists('set_transient')) {
            $expiry = defined('HOUR_IN_SECONDS') ? 24 * HOUR_IN_SECONDS : 86400;
            set_transient($key, $state, $expiry);
        }

        $state_file = $this->get_state_file();
        $json = function_exists('wp_json_encode') ? wp_json_encode($state) : json_encode($state);
        @file_put_contents($state_file, $json, LOCK_EX);
    }

    /**
     * 加载管线状态
     */
    private function load_state(): ?array {
        $key = $this->state_key_prefix . $this->name;

        $state_file = $this->get_state_file();
        if (file_exists($state_file)) {
            $data = json_decode(file_get_contents($state_file), true);
            if (is_array($data) && isset($data['pipeline'])) {
                return $data;
            }
        }

        if (function_exists('get_transient')) {
            $data = get_transient($key);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * 清理管线状态 (完成后调用)
     */
    private function clear_state(): void {
        $key = $this->state_key_prefix . $this->name;

        if (function_exists('delete_transient')) {
            delete_transient($key);
        }

        $state_file = $this->get_state_file();
        if (file_exists($state_file)) {
            @unlink($state_file);
        }
    }

    /**
     * 获取管线进度
     */
    public function get_progress(): array {
        $state = $this->load_state();
        if ($state === null) {
            return ['has_state' => false, 'progress' => 0];
        }
        $total = count($this->steps);
        $completed = $state['failed_at'];
        return [
            'has_state' => true,
            'progress' => round(($completed / $total) * 100),
            'failed_at' => $completed,
            'total' => $total,
            'saved_at' => $state['saved_at'] ?? null,
        ];
    }

    private function get_state_file(): string {
        $dir = sys_get_temp_dir() . '/linked3_pipeline_states/';
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $dir = $upload_dir['basedir'] . '/linked3_pipeline_states/';
        }
        if (!is_dir($dir)) {
            if (function_exists('wp_mkdir_p')) {
                @wp_mkdir_p($dir);
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        return $dir . $this->name . '.json';
    }

    /**
     * 加载YAML配置文件
     */
    private function load_yaml(string $file): array {
        if (!file_exists($file)) {
            return [];
        }
        $content = file_get_contents($file);

        if (function_exists('yaml_parse')) {
            $parsed = yaml_parse($content);
            return $parsed['steps'] ?? $parsed ?? [];
        }

        return $this->parse_yaml_simple($content);
    }

    /**
     * 简易YAML解析器
     */
    private function parse_yaml_simple(string $content): array {
        $lines = explode("\n", $content);
        $steps = [];
        $current_step = null;
        $in_params = false;
        $in_steps = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));

            if ($indent === 0 && strpos($trimmed, 'steps:') === 0) {
                $in_steps = true;
                $in_params = false;
                continue;
            }

            if (!$in_steps) continue;

            if (preg_match('/^(\s*)-\s+(.+)$/', $line, $m)) {
                if ($current_step !== null) {
                    $steps[] = $current_step;
                }
                $current_step = [];
                $in_params = false;
                $rest = trim($m[2]);
                $this->parse_kv($rest, $current_step, $in_params);
            } elseif ($current_step !== null && $indent > 0) {
                $this->parse_kv($trimmed, $current_step, $in_params);
            }
        }

        if ($current_step !== null) {
            $steps[] = $current_step;
        }

        return $steps;
    }

    private function parse_kv(string $rest, array &$step, bool &$in_params): void {
        if (preg_match('/^(\w+):\s*(.*)$/', $rest, $m)) {
            $key = $m[1];
            $val = trim($m[2]);
            if ($val === '') {
                $step[$key] = [];
                $in_params = ($key === 'params');
            } else {
                $step[$key] = $this->parse_value($val);
                $in_params = false;
            }
        }
    }

    private function parse_value(string $val) : mixed {
        $val = trim($val);
        if ((substr($val, 0, 1) === '"' && substr($val, -1) === '"') ||
            (substr($val, 0, 1) === "'" && substr($val, -1) === "'")) {
            return substr($val, 1, -1);
        }
        if ($val === 'true') return true;
        if ($val === 'false') return false;
        if ($val === 'null') return null;
        if (is_numeric($val)) return strpos($val, '.') !== false ? (float)$val : (int)$val;
        return $val;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_steps(): array {
        return $this->steps;
    }

}
