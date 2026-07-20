<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Footer_Registry — extracted from DiagramEndpointRegistry.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramFooterRegistry {
    private static ?Linked3_Diagram_Footer_Registry $instance = null;
    private array $footers = [];

    public static function instance(): Linked3_Diagram_Footer_Registry {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->register('values', ['name_zh' => '价值观型', 'template' => '{品牌}·{价值观}', 'suitable_for' => '品牌传达']);
        $this->register('method', ['name_zh' => '方法论型', 'template' => '{品牌}·{方法论}', 'suitable_for' => '方法论输出']);
        $this->register('principle', ['name_zh' => '原则型', 'template' => '{品牌}·{原则}', 'suitable_for' => '原则宣导']);
        $this->register('formula', ['name_zh' => '公式型', 'template' => '{品牌}·{公式}', 'suitable_for' => '公式总结']);
    }

    public function register(string $id, array $config): void {
        $this->footers[$id] = array_merge(['id' => $id], $config);
    }
    public function get(string $id): ?array { return $this->footers[$id] ?? null; }
    public function all(): array { return $this->footers; }

    public function render(string $id, array $vars): string {
        $footer = $this->get($id);
        if (!$footer) return '';
        $text = $footer['template'];
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', $v, $text);
        }
        return $text;
    }
}

// =================================================================
// v6.3.0.5: Footer×追问兼容性矩阵
// =================================================================
