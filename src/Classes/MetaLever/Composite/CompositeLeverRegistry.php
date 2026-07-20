<?php

declare(strict_types=1);
/**
 * Composite Lever Registry — v20.4-fix17 复合杠杆注册表.
 *
 * 统一管理所有复合杠杆（高级能力+复合能力）。
 * 支持三级分类：基础(24) → 高级(8) → 复合(5)
 *
 * @package Linked3
 * @subpackage Classes\MetaLever\Composite
 */

namespace Linked3\Classes\MetaLever\Composite;

if (!defined('ABSPATH')) {
    exit;
}

class CompositeLeverRegistry
{
    /** @var array<string, CompositeLeverInterface> */
    private static $levers = [];

    /** @var bool */
    private static $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        $dir = __DIR__;
        $files = glob($dir . '/class-composite-*.php');
        if (is_array($files)) {
            foreach ($files as $file) {
                require_once $file;
            }
        }

        $builtin = [
            'Linked3\\Classes\\MetaLever\\Composite\\CompositeDeai5d',
            'Linked3\\Classes\\MetaLever\\Composite\\CompositeGenesis',
            'Linked3\\Classes\\MetaLever\\Composite\\CompositeDeepStrategy',
            'Linked3\\Classes\\MetaLever\\Composite\\CompositeCrossInnovation',
            'Linked3\\Classes\\MetaLever\\Composite\\CompositeSocraticReview',
            'Linked3\Classes\MetaLever\Composite\CompositeSuperPrompt',
            'Linked3\Classes\MetaLever\Composite\CompositeCognitiveAudit',
            'Linked3\Classes\MetaLever\Composite\CompositeKnowledgeSynthesis',
            'Linked3\Classes\MetaLever\Composite\CompositeContentEngine',
            'Linked3\Classes\MetaLever\Composite\CompositeRiskDefense',
            'Linked3\Classes\MetaLever\Composite\CompositeUniversalTrio',
            'Linked3\Classes\MetaLever\Composite\CompositeCreativeEngine',
            'Linked3\Classes\MetaLever\Composite\CompositeQualityGauntlet',
            'Linked3\Classes\MetaLever\Composite\CompositeWritingDepth',
            'Linked3\Classes\MetaLever\Composite\CompositeSeedRecombinator',
            'Linked3\Classes\MetaLever\Composite\CompositeIntentDecoder',
            'Linked3\Classes\MetaLever\Composite\CompositeCodeOptimizer',
        ];

        foreach ($builtin as $class) {
            if (class_exists($class)) {
                $instance = new $class();
                if ($instance instanceof CompositeLeverInterface) {
                    self::register($instance);
                }
            }
        }

        do_action('linked3_composite_levers_registered', self::$levers);
    }

    public static function register(CompositeLeverInterface $lever): void
    {
        self::$levers[$lever->id()] = $lever;
    }

    public static function get(string $id): ?CompositeLeverInterface
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$levers[$id] ?? null;
    }

    public static function all(): array
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$levers;
    }

    public static function info(): array
    {
        if (!self::$initialized) {
            self::init();
        }
        $info = [];
        foreach (self::$levers as $id => $lever) {
            $info[] = [
                'id'          => $lever->id(),
                'label'       => $lever->label(),
                'description' => $lever->description(),
                'level'       => $lever->level(),
                'levers'      => $lever->orchestrated_levers(),
                'scene_tags'  => $lever->scene_tags(),
            ];
        }
        return $info;
    }

}
