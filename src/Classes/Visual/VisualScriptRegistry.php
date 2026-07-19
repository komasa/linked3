<?php

declare(strict_types=1);
/**
 * Visual Script Registry — v19.2 视觉脚本注册表.
 *
 * 统一管理所有视觉脚本生成器（小红书/漫画/图示/视频）。
 * 每个生成器实现 VisualScriptGeneratorInterface 接口。
 * 注册表提供查询、获取、列举功能。
 *
 * @package Linked3
 * @subpackage Classes\Visual
 */

namespace Linked3\Classes\Visual;

if (!defined('ABSPATH')) {
    exit;
}

final class VisualScriptRegistry
{
    /** @var array<string, VisualScriptGeneratorInterface> */
    private static $generators = [];

    /**
     * 注册一个视觉脚本生成器。
     *
     * @param VisualScriptGeneratorInterface $generator
     * @return void
     */
    public static function register_generator(VisualScriptGeneratorInterface $generator)
    : void {
        $platform = $generator->platform();
        self::$generators[$platform] = $generator;
    }

    /**
     * 获取指定平台的生成器。
     *
     * @param string $platform
     * @return VisualScriptGeneratorInterface|null
     */
    public static function get($platform) : mixed {
        return self::$generators[$platform] ?? null;
    }

    /**
     * 获取所有已注册的生成器。
     *
     * @return array<string, VisualScriptGeneratorInterface>
     */
    public static function all() : mixed     {
        return self::$generators;
    }

    /**
     * 获取所有平台选项（用于 UI 下拉菜单）。
     *
     * @return array [{ id, label }]
     */
    public static function platform_options() : mixed {
        $options = [];
        foreach (self::$generators as $id => $gen) {
            $options[] = [
                'id'    => $id,
                'label' => $gen->platform_label(),
            ];
        }
        return $options;
    }

    /**
     * 获取指定平台的可用风格列表。
     *
     * @param string $platform
     * @return array
     */
    public static function styles_for($platform) : mixed     {
        $gen = self::get($platform);
        if ($gen) {
            return $gen->available_styles();
        }
        return [];
    }
}
