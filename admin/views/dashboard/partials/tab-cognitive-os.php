<?php
/**
 * Dashboard partial: 🧠 认知操作系统 (v20.3)
 *
 * v20.3 重大重构 — 从"技术展示"改为"引导式工作流"
 *
 * 完整 SOP:
 *   ① 提出问题 → ② 启动演化 → ③ 查看结晶 Skill → ④ 应用 Skill → ⑤ 杠杆链审查 (可选)
 *
 * 每个区块都有:
 *   - "这是什么"说明
 *   - "怎么用"操作指引
 *   - "下一步"引导
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取 COS 数据
$cos_overview = [];
$top_skills   = [];
$recent_evolutions = [];
if (class_exists('\\Linked3\\Classes\\CognitiveOS\\COSReporter')) {
    $reporter = new \Linked3\Classes\CognitiveOS\COSReporter();
    $cos_overview      = $reporter->dashboard_overview();
    $top_skills       = $reporter->top_skills(10);
    $recent_evolutions = $reporter->recent_evolutions(10);
}

$cos_nonce = wp_create_nonce('linked3_cos');
$ajax_url  = esc_url(admin_url('admin-ajax.php'));
?>

<?php // v29.1.0 Step 5: Template split into 7 partials
include __DIR__ . '/cos-hero.php';
include __DIR__ . '/cos-sop-guide.php';
include __DIR__ . '/cos-evolution.php';
include __DIR__ . '/cos-skills.php';
include __DIR__ . '/cos-archive.php';
include __DIR__ . '/cos-lever-chain.php';
include __DIR__ . '/cos-architecture.php';
?>

</div>

<?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-cognitive-os.js ?>

