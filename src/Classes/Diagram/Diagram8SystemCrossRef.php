<?php

declare(strict_types=1);
/**
 * Diagram8SystemCrossRef — extracted from Diagram30Spectrum.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram
 */

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Diagram8SystemCrossRef {
    private array $systems = [
        'diagram'    => __('图示系统', 'linked3'),
        'animation'  => __('动画系统', 'linked3'),
        'brand'      => __('品牌系统', 'linked3'),
        'character'  => __('角色系统', 'linked3'),
        'product'    => __('产品系统', 'linked3'),
        'manga'      => __('漫画系统', 'linked3'),
        'picture_book' => __('绘本系统', 'linked3'),
        'sticker'    => __('表情包系统', 'linked3'),
    ];

    private array $crossRef = [];

    public function __construct() {
        // 每对系统的交叉引用关系
        $this->crossRef = [
            'diagram×animation' => __('图示是动画的分镜蓝图', 'linked3'),
            'diagram×brand' => __('品牌镜头签名在每帧贯穿', 'linked3'),
            'animation×brand' => __('品牌镜头签名在动画每帧', 'linked3'),
            'animation×character' => __('角色Seed在动画全程锁定', 'linked3'),
            'manga×animation' => __('漫画分格=动画分镜, 气泡映射声效', 'linked3'),
            'picture_book×animation' => __('绘本页=动画帧', 'linked3'),
            'sticker×character' => __('表情包共享角色Seed', 'linked3'),
        ];
    }

}

// =================================================================
// v6.5.0.7: 商业加固
// =================================================================
