<?php

declare(strict_types=1);
/**
 * Linked3 Diagram Visual DNA & Seed System — v6.4.0
 *
 * 9个原子版本:
 *   v6.4.0.1: CharacterSeed管理器 (VisualDNA+PersonalityDNA+Priority+Lock)
 *   v6.4.0.2: ProductSeed管理器 (Material/Shape/Glaze/Base/Accent+Signature_Light)
 *   v6.4.0.3: Seed引用3种模式 (锚定/链式/双参照)
 *   v6.4.0.4: Seed锁定3级 (Critical 100%/Important >95%/Flexible >80%)
 *   v6.4.0.5: Seed校验5维度
 *   v6.4.0.6: 系列DNA 4层锁定 (META签名/角标/徽章色/排版骨架)
 *   v6.4.0.7: 四层断裂诊断
 *   v6.4.0.8: Loop×角色校验7步
 *   v6.4.0.9: MD主格式编译器 (MD→JSON)
 *
 * @package Linked3\Diagram
 * @since 6.4.0
 */
namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

// =================================================================
// v6.4.0.1: CharacterSeed管理器
// =================================================================

class DiagramCharacterSeedManager {
    private static ?DiagramCharacterSeedManager $instance = null;
    private array $seeds = [];

    public static function instance(): DiagramCharacterSeedManager {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * 创建角色种子。
     */
    public function create(string $id, array $visualDNA, array $personalityDNA, array $priority): array {
        $seed = [
            'id' => $id,
            'visual_dna' => [
                'face' => $visualDNA['face'] ?? '',
                'body' => $visualDNA['body'] ?? '',
                'hair' => $visualDNA['hair'] ?? '',
                'costume' => $visualDNA['costume'] ?? '',
                'accessory' => $visualDNA['accessory'] ?? '',
            ],
            'personality_dna' => [
                'personality' => $personalityDNA['personality'] ?? '',
                'speech_pattern' => $personalityDNA['speech_pattern'] ?? '',
                'emotion_range' => $personalityDNA['emotion_range'] ?? [],
            ],
            'priority' => [
                'critical' => $priority['critical'] ?? [],   // 100%锁定
                'important' => $priority['important'] ?? [], // >95%
                'flexible' => $priority['flexible'] ?? [],   // >80%
            ],
            'lock' => [
                'character_lock' => true,
                'personality_lock' => true,
            ],
        ];
        $this->seeds[$id] = $seed;
        return $seed;
    }

    public function get(string $id): ?array { return $this->seeds[$id] ?? null; }
    public function all(): array { return $this->seeds; }

    /**
     * 校验角色一致性。
     */
    public function verifyConsistency(string $seedId, array $generated): array {
        $seed = $this->seeds[$seedId] ?? null;
        if (!$seed) return ['passed' => false, 'issues' => ['Seed不存在']];

        $issues = [];
        // 校验Critical项 100%保留
        foreach ($seed['priority']['critical'] as $item) {
            if (!str_contains(json_encode($generated, JSON_UNESCAPED_UNICODE), $item)) {
                $issues[] = "Critical项「{$item}」缺失";
            }
        }
        // 校验Important项 >95%
        $importantCount = count($seed['priority']['important']);
        $importantFound = 0;
        foreach ($seed['priority']['important'] as $item) {
            if (str_contains(json_encode($generated, JSON_UNESCAPED_UNICODE), $item)) $importantFound++;
        }
        $importantRate = $importantCount > 0 ? $importantFound / $importantCount : 1;
        if ($importantRate < 0.95) {
            $issues[] = "Important项保留率{$importantRate}<95%";
        }

        return ['passed' => empty($issues), 'issues' => $issues, 'important_rate' => $importantRate];
    }
}

// =================================================================
// v6.4.0.2: ProductSeed管理器


// =================================================================
// v6.4.0.3: Seed引用3种模式


// =================================================================
// v6.4.0.4: Seed锁定3级


// =================================================================
// v6.4.0.5: Seed校验5维度


// =================================================================
// v6.4.0.6: 系列DNA 4层锁定


// =================================================================
// v6.4.0.7: 四层断裂诊断


// =================================================================
// v6.4.0.8: Loop×角色校验7步


// =================================================================
// v6.4.0.9: MD主格式编译器 (MD→JSON)

