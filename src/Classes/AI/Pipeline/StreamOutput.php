<?php

declare(strict_types=1);
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

class StreamOutput {
    private static ?StreamOutput $instance = null;
    private bool $active = false;
    private string $buffer = '';

    public static function instance(): StreamOutput {
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


