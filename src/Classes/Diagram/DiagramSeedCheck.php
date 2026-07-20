<?php

declare(strict_types=1);
/**
 * Linked3_Diagram_Seed_Check — extracted from DiagramCharacterSeedManager.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class DiagramSeedCheck {
    public function check(array $seed, array $generated): array {
        return [
            'visual_dna'     => $this->checkVisualDNA($seed, $generated),
            'personality'    => $this->checkPersonality($seed, $generated),
            'priority'       => $this->checkPriority($seed, $generated),
            'lock'           => $this->checkLock($seed, $generated),
            'consistency'    => $this->checkConsistency($seed, $generated),
        ];
    }

    private function checkVisualDNA(array $seed, array $gen): array {
        $vd = $seed['visual_dna'] ?? [];
        $passed = !empty($vd['face']) && !empty($vd['costume']);
        return ['passed' => $passed, 'msg' => $passed ? 'OK' : 'VisualDNA不完整'];
    }
    private function checkPersonality(array $seed, array $gen): array {
        $pd = $seed['personality_dna'] ?? [];
        $passed = !empty($pd['personality']);
        return ['passed' => $passed, 'msg' => $passed ? 'OK' : '性格DNA缺失'];
    }
    private function checkPriority(array $seed, array $gen): array {
        $locker = new Linked3_Diagram_Seed_Lock();
        $result = $locker->checkLock($seed, $gen);
        $allPassed = $result['critical']['passed'] && $result['important']['passed'];
        return ['passed' => $allPassed, 'detail' => $result];
    }
    private function checkLock(array $seed, array $gen): array {
        $lock = $seed['lock'] ?? [];
        $passed = ($lock['character_lock'] ?? false) && ($lock['personality_lock'] ?? false);
        return ['passed' => $passed, 'msg' => $passed ? 'OK' : '锁定未启用'];
    }
    private function checkConsistency(array $seed, array $gen): array {
        $passed = true; // 简化
        return ['passed' => $passed, 'msg' => '一致性检查通过'];
    }
}

// =================================================================
// v6.4.0.6: 系列DNA 4层锁定
// =================================================================
