<?php

declare(strict_types=1);
/**
 * Linked3 Genesis 9 Engine Quick Access v1.0.0
 *
 * G7 Track F 9种推荐引擎的快捷调用入口
 * 每个引擎提供独立静态方法，便于程序化调用
 *
 * 引擎清单:
 *   F-01 auto          一键智能推荐
 *   F-02 beginner       新手友好推荐
 *   F-03 designer       设计师精选
 *   F-04 market         万兴市场优选
 *   F-05 industry       行业专家推荐
 *   F-06 accessible     无障碍优先
 *   F-07 conversion     高转化推荐
 *   F-08 complex        复杂内容推荐
 *   F-09 cross-platform 跨平台适配
 *
 * @package Linked3\Genesis
 * @since 16.0.27
 */

namespace Linked3\Classes\Genesis;

if (!defined('ABSPATH')) exit;

class Genesis9Engines
{
    /**
     * F-01 一键智能推荐
     */
    public static function auto(string $content): array
    {
        return self::engine()->recommend($content, 'auto');
    }

    /**
     * F-02 新手友好推荐
     */
    public static function beginner(string $content): array
    {
        return self::engine()->recommend($content, 'beginner');
    }

    /**
     * F-03 设计师精选
     */
    public static function designer(string $content): array
    {
        return self::engine()->recommend($content, 'designer');
    }

    /**
     * F-04 万兴市场优选
     */
    public static function market(string $content, string $category = ''): array
    {
        return self::engine()->recommend($content, 'market', $category);
    }

    /**
     * F-05 行业专家推荐
     */
    public static function industry(string $content, string $industry): array
    {
        return self::engine()->recommend($content, 'industry', $industry);
    }

    /**
     * F-06 无障碍优先
     */
    public static function accessible(string $content): array
    {
        return self::engine()->recommend($content, 'accessible');
    }

    /**
     * F-07 高转化推荐
     */
    public static function conversion(string $content): array
    {
        return self::engine()->recommend($content, 'conversion');
    }

    /**
     * F-08 复杂内容推荐
     */
    public static function complex(string $content): array
    {
        return self::engine()->recommend($content, 'complex');
    }

    /**
     * F-09 跨平台适配推荐
     */
    public static function crossPlatform(string $content, string $platforms = ''): array
    {
        return self::engine()->recommend($content, 'cross-platform');
    }

    /**
     * 获取所有9引擎信息
     */
    public static function getAllEngines(): array
    {
        return self::engine()->getModes();
    }

    /**
     * 获取引擎实例
     */
    private static function engine(): GenesisRecommendationEngine
    {
        return new GenesisRecommendationEngine();
    }
}
