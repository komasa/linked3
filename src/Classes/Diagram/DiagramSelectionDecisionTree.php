<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Selection_DecisionTree — extracted from Diagram3LayerDepth.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Linked3_Diagram_Selection_DecisionTree {
    private DiagramTypeRegistry $registry;

    public function __construct() {
        $this->registry = DiagramTypeRegistry::instance();
    }

    /**
     * 根据信息结构选择图示类型。
     */
    public function select(string $infoStructure): array {
        $typeId = $this->registry->selectByInfoStructure($infoStructure);
        $type = $this->registry->get($typeId);
        return [
            'selected_type' => $typeId,
            'name_zh' => $type['name_zh'] ?? '',
            'name_en' => $type['name_en'] ?? '',
            'category' => $type['category'] ?? '',
            'info_structure' => $infoStructure,
            'prompt_fragment' => $type['prompt_fragment'] ?? '',
        ];
    }

    /**
     * 批量选择 (为多个模块匹配图示)。
     */
    public function selectBatch(array $modules): array {
        $result = [];
        foreach ($modules as $module) {
            $info = $module['info_structure'] ?? '层级递进';
            $result[] = array_merge($module, $this->select($info));
        }
        return $result;
    }
}

/**
 * Linked3 Diagram Complexity Reduction — v6.1.0.6
 * 复杂结构降维三法: 象限法/漏斗法/聚类法
 */
