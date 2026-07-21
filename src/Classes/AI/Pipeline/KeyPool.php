<?php

declare(strict_types=1);
/**
 * Linked3 Key Pool — v5.6.0.4
 *
 * 功能:
 *   - 每个 Provider 支持多 Key 轮转 (突破单 Key 速率限制)
 *   - Key 限流自动切换下一个 Key
 *   - Key 健康追踪 (可用/限流/失效)
 *   - 事件驱动: key.exhausted / key.rotated
 *
 * @package Linked3\AI\Pipeline
 * @since 5.6.0.4
 */
namespace Linked3\Classes\AI\Pipeline;

if (!defined('ABSPATH')) exit;

class KeyPool {
    private static ?KeyPool $instance = null;
    private array $keys = [];       // ['siliconflow' => ['sk-xxx', 'sk-yyy', ...]]
    private array $keyStatus = [];  // ['sk-xxx' => ['status'=>'active', 'rate_limited_until'=>0, 'calls'=>0]]
    private int $rotationIndex = 0;

    public static function instance(): KeyPool {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->loadKeys();
    }

    /**
     * 从 option 加载所有 Provider 的 Key 池。
     */
    private function loadKeys(): void {
        $providerKeys = get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
        foreach ($providerKeys as $provider => $raw) {
            $lines = array_filter(array_map('trim', explode("\n", $raw)));
            $this->keys[$provider] = $lines;
            foreach ($lines as $key) {
                if (!isset($this->keyStatus[$key])) {
                    $this->keyStatus[$key] = [
                        'status' => 'active',
                        'rate_limited_until' => 0,
                        'calls' => 0,
                        'errors' => 0,
                    ];
                }
            }
        }
    }

    /**
     * 标记 Key 失效 (永久)。
     */
    public function markInvalid(string $key): void {
        if (!isset($this->keyStatus[$key])) return;
        $this->keyStatus[$key]['status'] = 'invalid';
    }
}
