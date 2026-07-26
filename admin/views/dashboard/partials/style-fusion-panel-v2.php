<?php
/**
 * 风格库融合面板 v2.0 — 统一画风选择器
 *
 * ============================================================================
 * v2.0 重构要点 (修复 v1.x 三大 Bug + 五处架构冲突):
 *
 * [Bug-1 修复] 画风下拉"看不见"
 *   v1.x: 画风下拉在 form-grid 第1格, 面板 grid-column:1/-1 独占下一行 → 视觉断裂
 *   v2.0: 画风下拉内嵌进面板顶部, 作为面板"输出区", 视觉强绑定
 *
 * [Bug-2 修复] AI自动适配 ≈ AI推荐 语义重叠
 *   v1.x: 两个按钮都调 linked3_genesis_recommend(mode=auto), 仅 Top1自动 vs Top3手动 之差
 *   v2.0: 合并为单一"🤖 AI推荐"按钮 + "☑ 自动选用Top1"开关; 开关开=自动选中, 关=手动选卡
 *
 * [冲突-3 修复] 视图过滤清空"自动适配"选项
 *   v1.x: styleSelect.innerHTML='' 直接清空, "auto"选项永久消失
 *   v2.0: 重建时始终保留首位"🤖 自动适配(后端推断)"选项
 *
 * [冲突-4 修复] 硬编码 DOM 耦合
 *   v1.x: JS 硬编码 getElementById('lk3-charts-visual-style') 仅 charts 实例可用
 *   v2.0: visual_style_select_id 改为可选参数, 通过 data 属性传递, 三实例通用
 *
 * [冲突-5 修复] 双轴语义混淆
 *   v1.x: 视图过滤(F/Y/S/G) 与 推荐策略(F-01~F-09) 混在同一面板无分隔
 *   v2.0: 明确分区 "① 按用途筛选" + "② 按策略AI推荐", 标题标注轴别
 *
 * [冲突-6 修复] 四处"auto"语义不一
 *   v1.x: 画风auto / 面板AI自动适配 / 布局auto-adapt / 技法auto-adapt 各指不同
 *   v2.0: 画风auto=后端推断(保留); 面板改为"AI推荐+开关"; 布局/技法auto-adapt加tooltip说明
 *
 * [冲突-7 修复] 标签冗余
 *   v1.x: 面板头"AI智能推荐" + 按钮"AI自动适配" + 按钮"AI推荐" = 3个AI标签
 *   v2.0: 面板头"🎨 画风风格库" + 单按钮"🤖 AI推荐" = 语义清晰
 * ============================================================================
 *
 * 用法:
 *   <?php
 *   $fusion_params = [
 *       'style_select_id'        => 'lk3-charts-style',        // 必填: 画风下拉DOM ID (v2.0由面板内部渲染)
 *       'topic_input_id'         => 'lk3-charts-topic',         // 必填: 内容输入框DOM ID
 *       'visual_style_select_id' => 'lk3-charts-visual-style',  // 可选: 信息图技法下拉DOM ID (联动禁用)
 *       'nonce'                  => wp_create_nonce('linked3_content_writer'),
 *       'ajax_url'               => admin_url('admin-ajax.php'),
 *       'instance'               => 'charts',                   // 实例标识
 *   ];
 *   include __DIR__ . '/style-fusion-panel-v2.php';
 *   ?>
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @since 16.0.35
 * @replaces style-fusion-panel.php (v1.2)
 */

if (!defined('ABSPATH')) exit;

// ---- 参数兜底 (v2.0 新增 visual_style_select_id) ----
$style_select_id        = $style_select_id        ?? 'linked3-genesis-style';
$topic_input_id         = $topic_input_id         ?? 'linked3-genesis-script';
$visual_style_select_id = $visual_style_select_id ?? '';  // v2.0: 可选, 留空则不联动
$nonce                  = $nonce                  ?? wp_create_nonce('linked3_content_writer');
$ajax_url               = $ajax_url               ?? admin_url('admin-ajax.php');
$instance               = $instance               ?? 'default';

// ---- 加载画风列表 (v2.0: 面板自己负责渲染下拉, 不依赖外部预渲染) ----
// v11.0: 按 instance 过滤风格 — 漫画脚本(genesis)只显示S/Y/G漫画基因, 图示脚本(charts)只显示F图示基因
// 公理2: 漫画视觉基因(S/Y/G)与图示基因(F)正交, 不可混入
$panel_styles = [];
if (class_exists('GenesisAtomIndex')) {
    $idx = GenesisAtomIndex::instance();
    $raw = $idx->getStyles();
    if (isset($raw['styles']) && is_array($raw['styles'])) {
        foreach ($raw['styles'] as $sid => $sinfo) {
            $uc = $sinfo['usage_code'] ?? '';
            // v11.0: 按 instance 过滤
            // genesis(漫画脚本) → 只保留 S/Y/G 开头的漫画视觉风格
            // charts(图示脚本)  → 只保留 F 开头的图示风格
            // default/其他       → 全部显示(兼容旧行为)
            if ($instance === 'genesis') {
                if (!preg_match('/^[SYG]\d+/', $uc)) continue;
            } elseif ($instance === 'charts' || $instance === 'diagram') {
                if (!preg_match('/^F\d+/', $uc)) continue;
            }
            $label = $sinfo['name_cn'] ?? ($sinfo['name_en'] ?? $sid);
            if ($uc) $label = '[' . $uc . '] ' . $label;
            if (!empty($sinfo['category'])) $label .= ' [' . $sinfo['category'] . ']';
            $panel_styles[$sid] = $label;
        }
    }
}
// v11.0: 动态计算风格数量
$_panel_style_count = count($panel_styles);
?>

<!-- ===== 画风风格库融合面板 v2.0 [实例: <?php echo esc_attr($instance); ?>] ===== -->
<div class="lk3-sfp-v2" data-instance="<?php echo esc_attr($instance); ?>"
     data-style-select-id="<?php echo esc_attr($style_select_id); ?>"
     data-visual-style-select-id="<?php echo esc_attr($visual_style_select_id); ?>"
     data-topic-input-id="<?php echo esc_attr($topic_input_id); ?>"
     data-nonce="<?php echo esc_attr($nonce); ?>"
     data-ajax-url="<?php echo esc_attr($ajax_url); ?>">

<?php // v29.1.0 Step 5: Template split into 3 partials
include __DIR__ . '/sfp-output.php';
include __DIR__ . '/sfp-axis-usage.php';
include __DIR__ . '/sfp-axis-ai.php';
