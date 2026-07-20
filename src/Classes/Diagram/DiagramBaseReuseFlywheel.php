<?php

declare(strict_types=1);
/**
 * DiagramBaseReuseFlywheel — extracted from Diagram30Spectrum.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramBaseReuseFlywheel {
    /**
     * 计算复用率。
     */
    public function calculateReuseRate(array $baseTemplate, array $derivedDiagram): array {
        $baseKeys = array_keys($baseTemplate);
        $derivedKeys = array_keys($derivedDiagram);
        $shared = array_intersect($baseKeys, $derivedKeys);
        $reuseRate = count($derivedKeys) > 0 ? count($shared) / count($derivedKeys) : 0;
        return [
            'base_fields' => count($baseKeys),
            'derived_fields' => count($derivedKeys),
            'shared_fields' => count($shared),
            'reuse_rate' => round($reuseRate * 100, 1) . '%',
            'target' => '70-80%',
            'passed' => $reuseRate >= 0.70,
        ];
    }

    /**
     * 从基座派生子类。
     */
    public function derive(string $baseType, array $overrides): array {
        // v19.55-fix: match() is PHP 8.0+, plugin requires PHP 7.4 — convert to switch.
        switch ($baseType) {
            case 'knowledge':
                $base = ['layout' => '4band', 'badge_system' => true, 'color_system' => '9badge', 'density' => 'deep'];
                break;
            case 'infographic':
                $base = ['layout' => '3column', 'badge_system' => false, 'color_system' => 'theme', 'density' => 'standard'];
                break;
            case 'flowchart':
                $base = ['layout' => 'linear', 'badge_system' => true, 'color_system' => 'mono', 'density' => 'minimal'];
                break;
            default:
                $base = ['layout' => '4band', 'badge_system' => true, 'color_system' => '9badge', 'density' => 'deep'];
                break;
        }
        return array_merge($base, $overrides);
    }
}

// =================================================================
// v6.5.0.3: 3D/AR/动态海报子系统
// =================================================================
