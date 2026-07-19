<?php
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

class Linked3_Composite_Lever_Registry
{
    /** @var array<string, Linked3_Composite_Lever_Interface> */
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
            'Linked3\\Classes\\MetaLever\\Composite\\Linked3_Composite_Deai5d',
            'Linked3\\Classes\\MetaLever\\Composite\\Linked3_Composite_Genesis',
            'Linked3\\Classes\\MetaLever\\Composite\\Linked3_Composite_Deep_Strategy',
            'Linked3\\Classes\\MetaLever\\Composite\\Linked3_Composite_Cross_Innovation',
            'Linked3\\Classes\\MetaLever\\Composite\\Linked3_Composite_Socratic_Review',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Super_Prompt',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Cognitive_Audit',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Knowledge_Synthesis',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Content_Engine',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Risk_Defense',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Universal_Trio',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Creative_Engine',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Quality_Gauntlet',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Writing_Depth',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Seed_Recombinator',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Intent_Decoder',
            'Linked3\Classes\MetaLever\Composite\Linked3_Composite_Code_Optimizer',
        ];

        foreach ($builtin as $class) {
            if (class_exists($class)) {
                $instance = new $class();
                if ($instance instanceof Linked3_Composite_Lever_Interface) {
                    self::register($instance);
                }
            }
        }

        do_action('linked3_composite_levers_registered', self::$levers);
    }

    public static function register(Linked3_Composite_Lever_Interface $lever): void
    {
        self::$levers[$lever->id()] = $lever;
    }

    public static function get(string $id): ?Linked3_Composite_Lever_Interface
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

    public static function by_level(string $level): array
    {
        if (!self::$initialized) {
            self::init();
        }
        $result = [];
        foreach (self::$levers as $id => $lever) {
            if ($lever->level() === $level) {
                $result[$id] = $lever;
            }
        }
        return $result;
    }
}
