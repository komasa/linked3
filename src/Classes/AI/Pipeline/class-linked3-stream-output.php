<?php
/**
 * Linked3 Stream Output — v5.6.0.6
 *
 * 功能:
 *   - SSE (Server-Sent Events) 流式输出 AI 响应
 *   - 前端实时显示打字机效果
 *   - 支持取消 (AbortController)
 *   - 错误恢复: 中断时返回已生成部分
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.6
 */
namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class Linked3_Stream_Output {
    private static ?Linked3_Stream_Output $instance = null;
    private bool $active = false;
    private string $buffer = '';

    public static function instance(): Linked3_Stream_Output {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 启动 SSE 流。
     */
    public function start(): void {
        $this->active = true;
        $this->buffer = '';
        // 设置 SSE 头
        if (!headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Nginx
        }
        // 关闭输出缓冲
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
    }

    /**
     * 发送一个 SSE 事件。
     */
    public function send(string $event, string $data): void {
        if (!$this->active) return;
        echo "event: {$event}\n";
        echo "data: " . $this->escapeSSE($data) . "\n\n";
        flush();
        $this->buffer .= $data;
    }

    /**
     * 发送 data 块 (默认 event)。
     */
    public function sendChunk(string $chunk): void {
        $this->send('message', $chunk);
    }

    /**
     * 发送完成事件。
     */
    public function sendDone(array $meta = []): void {
        $this->send('done', wp_json_encode(array_merge([
            'status' => 'completed',
            'total_length' => mb_strlen($this->buffer),
        ], $meta)));
        $this->active = false;
    }

    /**
     * 发送错误事件。
     */
    public function sendError(string $message, array $extra = []): void {
        $this->send('error', wp_json_encode(array_merge([
            'message' => $message,
        ], $extra)));
        $this->active = false;
    }

    /**
     * 检查客户端是否断开。
     */
    public function clientDisconnected(): bool {
        return connection_aborted() !== 0;
    }

    /**
     * 获取已生成的完整内容 (用于中断恢复)。
     */
    public function getBuffer(): string {
        return $this->buffer;
    }

    /**
     * SSE data 转义 (换行处理)。
     */
    private function escapeSSE(string $data): string {
        // SSE 每行需要独立 data: 前缀
        $lines = explode("\n", $data);
        return implode("\ndata: ", $lines);
    }
}

/**
 * Linked3 Cost Reporter — v5.6.0.7
 * 成本报表: 按 provider/user/module/time 维度统计
 */
class Linked3_Cost_Reporter {
    public static function getReport(string $period = 'monthly', ?int $userId = null): array {
        $meter = Linked3_Token_Meter::instance();
        $date = $period === 'monthly' ? current_time('Y-m') : current_time('Y-m-d');

        $report = [
            'period' => $period,
            'date' => $date,
            'total_tokens' => 0,
            'total_cost' => 0,
            'by_provider' => [],
            'by_user' => [],
            'by_module' => [],
        ];

        $usage = get_option(LINKED3_OPTION_PREFIX . 'token_usage', []);
        $entries = $usage[$period][$date] ?? [];

        foreach ($entries as $e) {
            if ($userId !== null && $e['user_id'] !== $userId) continue;
            $report['total_tokens'] += $e['total_tokens'];

            $p = $e['provider'];
            if (!isset($report['by_provider'][$p])) {
                $report['by_provider'][$p] = ['tokens' => 0, 'calls' => 0];
            }
            $report['by_provider'][$p]['tokens'] += $e['total_tokens'];
            $report['by_provider'][$p]['calls']++;

            $u = $e['user_id'];
            if (!isset($report['by_user'][$u])) {
                $report['by_user'][$u] = ['tokens' => 0, 'calls' => 0];
            }
            $report['by_user'][$u]['tokens'] += $e['total_tokens'];
            $report['by_user'][$u]['calls']++;

            $m = $e['module'];
            if (!isset($report['by_module'][$m])) {
                $report['by_module'][$m] = ['tokens' => 0, 'calls' => 0];
            }
            $report['by_module'][$m]['tokens'] += $e['total_tokens'];
            $report['by_module'][$m]['calls']++;
        }

        return $report;
    }

    public static function formatCost(float $cost): string {
        if ($cost < 0.01) return '$' . number_format($cost * 1000, 2) . 'm'; // milli
        if ($cost < 1) return '$' . number_format($cost, 4);
        return '$' . number_format($cost, 2);
    }
}
