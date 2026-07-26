<?php
/**
 * Dashboard partial: 漫画脚本 Genesis v10.0 — SEED-First 线性流水线重构
 *
 * v10.0 重构要点 (基于 /genesis 5部门3代演化):
 *   公理1: SEED是信息基(低熵), 剧本是熵增调度 → UI顺序必须 SEED→剧本→分镜
 *   公理2: SEED的"不可变/可变"二分是降维核心 → fixed/variable 显式UI化
 *   公理3: 线性5阶段流水线 → Stage0(SEED)→Stage1(剧本)→Stage2(配置)→Stage3(生成)→Stage4(质检)
 *
 * 兼容性: 保留所有现有 element ID 和 AJAX action, 仅重构 UI 呈现层
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-23
 */
if (!defined('ABSPATH')) exit;

// ============================================================
// PHP 数据准备 (保留原逻辑)
// ============================================================
$nonce_g  = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

$styles = [];
$stats  = [];
if (class_exists('GenesisAtomIndex')) {
    $idx    = GenesisAtomIndex::instance();
    $raw    = $idx->getStyles();
    // _index.json 返回 {version, total_styles, architecture, styles:{...}}
    // 漫画脚本只显示漫画视觉风格 (usage_code 以 S/Y/G 开头), 排除图示风格 (F开头)
    // 公理2: 漫画视觉基因 = S(摄影) + Y(插画/东方) + G(概念/西方), 不可混入图示基因(F)
    if (isset($raw['styles']) && is_array($raw['styles'])) {
        foreach ($raw['styles'] as $sid => $sinfo) {
            $uc = $sinfo['usage_code'] ?? '';
            // 只保留漫画视觉风格: S01-S99, Y01-Y99, G01-G99
            if (!preg_match('/^[SYG]\d+/', $uc)) continue;
            $label = $sinfo['name_cn'] ?? ($sinfo['name_en'] ?? $sid);
            if (!empty($sinfo['category'])) $label .= ' [' . $sinfo['category'] . ']';
            $styles[$sid] = $label;
        }
    }
    $stats = $idx->getStats();
}

// v10.0: SEED 分类定义 (6类, 对应公理2) — v11.0: 统一墨黑色头, 极简
$seed_categories = [
    'character' => ['icon' => '👤', 'label' => __('角色', 'linked3'), 'desc' => __('人物外貌/性格/服装', 'linked3'), 'color' => '#18181B'],
    'scene'     => ['icon' => '🏞️', 'label' => __('场景', 'linked3'), 'desc' => __('地点/环境/氛围', 'linked3'), 'color' => '#18181B'],
    'prop'      => ['icon' => '⚔️', 'label' => __('道具', 'linked3'), 'desc' => __('关键物品/武器/信物', 'linked3'), 'color' => '#18181B'],
    'style'     => ['icon' => '🎨', 'label' => __('风格', 'linked3'), 'desc' => __('画风/色调/笔触', 'linked3'), 'color' => '#18181B'],
    'brand'     => ['icon' => '🏷️', 'label' => __('品牌', 'linked3'), 'desc' => __('IP标识/水印/字体', 'linked3'), 'color' => '#18181B'],
    'palette'   => ['icon' => '🌈', 'label' => __('色板', 'linked3'), 'desc' => __('主色/辅色/情绪色', 'linked3'), 'color' => '#18181B'],
];
?>

<!-- ============================================================ -->
<!-- HTML: 线性5阶段流水线 -->
<!-- ============================================================ -->
<div class="lk3-genesis-wrap">

<?php // v29.1.0 Step 5: Template split into 6 partials
include __DIR__ . '/genesis-progress.php';
include __DIR__ . '/genesis-stage-seed.php';
include __DIR__ . '/genesis-stage-input.php';
include __DIR__ . '/genesis-stage-config.php';
include __DIR__ . '/genesis-stage-execute.php';
include __DIR__ . '/genesis-stage-export.php';
?>

<!-- JS: 保留所有现有AJAX逻辑 + 新增SEED中心交互 + 阶段导航 -->
<!-- ============================================================ -->
<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-genesis.js ?>

