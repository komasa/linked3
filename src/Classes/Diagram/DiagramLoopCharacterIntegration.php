<?php

declare(strict_types=1);
/**
 * DiagramLoopCharacterIntegration — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramLoopCharacterIntegration {
    private array $steps = [
        1 => '生成初稿 (含角色Seed)',
        2 => '校验角色Critical项 (100%)',
        3 => '校验角色Important项 (>95%)',
        4 => '诊断角色断裂',
        5 => '修复角色不一致',
        6 => '再校验系列DNA 4层',
        7 => '定稿归档',
    ];

    private function autoFixCharacter(array $diagram, string $seedId): array {
        $charMgr = DiagramCharacterSeedManager::instance();
        $seed = $charMgr->get($seedId);
        if ($seed) {
            $diagram['character_seed_applied'] = $seedId;
            $diagram['character_lock'] = true;
        }
        return $diagram;
    }

}

// =================================================================
// v6.4.0.9: MD主格式编译器 (MD→JSON)
// =================================================================
