<?php

declare(strict_types=1);
namespace Linked3\Classes\Genesis;
if (!defined('ABSPATH')) exit;
/**
 * GenesisV7Loader — G8 extraction.
 * @since 27.13.0
 */
class GenesisV7Loader
{
    public function loadAll(): void {
        $this->load_seed_dir('characters');
        $this->load_seed_dir('scenes');
        $this->load_seed_dir('styles');
        $this->load_operator_dir();
    }

    /**
     * 从 seeds/{type} 目录加载 JSON 文件到对应属性
     */
    private function load_seed_dir(string $type): void {
        $dir = $this->libDir . '/seeds/' . $type;
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['id'])) {
                $this->{$type}[$data['id']] = $data;
            }
        }
    }

    /**
     * 从 operators 目录加载算子
     */
    private function load_operator_dir(): void {
        $dir = $this->libDir . '/operators';
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['id'])) {
                $this->operators[$data['id']] = $data;
            }
        }
    }

}
