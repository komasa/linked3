<?php

declare(strict_types=1);
/**
 * Diagram8SystemCrossRef — extracted from Diagram30Spectrum.php during PSR-4 migration.
 *
 * @package Linked3\Classes\Diagram

namespace Linked3\Classes\Diagram;

if (!defined('ABSPATH')) exit;

class Diagram8SystemCrossRef {
    private array $systems = [
        'diagram'    => '图示系统',
        'animation'  => '动画系统',
        'brand'      => '品牌系统',
        'character'  => '角色系统',
        'product'    => '产品系统',
        'manga'      => '漫画系统',
        'picture_book' => '绘本系统',
        'sticker'    => '表情包系统',
    ];

    private array $crossRef = [];

    public function __construct() {
        // 每对系统的交叉引用关系
        $this->crossRef = [
            'diagram×animation' => '图示是动画的分镜蓝图',
            'diagram×brand' => '品牌镜头签名在每帧贯穿',
            'animation×brand' => '品牌镜头签名在动画每帧',
            'animation×character' => '角色Seed在动画全程锁定',
            'manga×animation' => '漫画分格=动画分镜, 气泡映射声效',
            'picture_book×animation' => '绘本页=动画帧',
            'sticker×character' => '表情包共享角色Seed',
        ];
    }

    public function getRelation(string $systemA, string $systemB): ?string {
        $key1 = "{$systemA}×{$systemB}";
        $key2 = "{$systemB}×{$systemA}";
        return $this->crossRef[$key1] ?? $this->crossRef[$key2] ?? null;
    }

    public function getAllRelations(): array { return $this->crossRef; }
    public function getSystems(): array { return $this->systems; }
}

// =================================================================
// v6.5.0.7: 商业加固
// =================================================================
