<?php
/**
 * Dashboard partial: 🧠 元杠杆配置 (v19.51)
 *
 * 系统设置 → 元杠杆配置 子面板
 * 展示 12 个元提示词杠杆 + 8 种图示结构，支持启用/禁用
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取杠杆列表
$levers = [];
if (class_exists('\\Linked3\\Classes\\MetaLever\\Linked3_Meta_Lever_Registry')) {
    $levers = \Linked3\Classes\MetaLever\Linked3_Meta_Lever_Registry::info();
}

// 获取结构列表
$structures = [];
if (class_exists('\\Linked3\\Classes\\Diagram\\Linked3_Diagram_Structure_Registry')) {
    $structures = \Linked3\Classes\Diagram\Linked3_Diagram_Structure_Registry::all();
}

// v27.17.9-fix1: 获取复合杠杆列表 (17种高级+复合能力)
$composite_levers = [];
if (class_exists('\\Linked3\\Classes\\MetaLever\\Composite\\Linked3_Composite_Lever_Registry')) {
    $composite_levers = \Linked3\Classes\MetaLever\Composite\Linked3_Composite_Lever_Registry::info();
}
$composite_count = count($composite_levers);
$basic_count = count($levers);
$structure_count = count($structures);

// 处理保存
if (isset($_POST['linked3_save_meta_levers']) && check_admin_referer('linked3_meta_levers')) {
    $enabled = isset($_POST['lever_enabled']) ? array_map('sanitize_key', (array) $_POST['lever_enabled']) : [];
    if (class_exists('\\Linked3\\Classes\\MetaLever\\Linked3_Meta_Lever_Registry')) {
        foreach ($levers as $lever) {
            \Linked3\Classes\MetaLever\Linked3_Meta_Lever_Registry::set_enabled($lever['id'], in_array($lever['id'], $enabled, true));
        }
    }
    echo '<div class="notice notice-success is-dismissible"><p>✅ 元杠杆配置已保存。</p></div>';
    // 刷新杠杆列表
    if (class_exists('\\Linked3\\Classes\\MetaLever\\Linked3_Meta_Lever_Registry')) {
        $levers = \Linked3\Classes\MetaLever\Linked3_Meta_Lever_Registry::info();
    }
}
?>

<div class="linked3-eco-card">
    <h3>🧠 元提示词杠杆配置</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        <strong><?php echo $basic_count; ?></strong> 个元能力杠杆教会 AI "怎么思考"而非"思考什么"。启用后，AI 输出将包含对应的 trace 字段（如 learning_trace、logic_trace），可观测 AI 的认知过程。
    </p>

    <form method="post" action="">
        <?php wp_nonce_field('linked3_meta_levers'); ?>

        <table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
            <thead>
                <tr>
                    <th style="width:40px;">启用</th>
                    <th style="width:120px;">杠杆</th>
                    <th>描述</th>
                    <th style="width:200px;">适用任务</th>
                    <th style="width:150px;">Trace 字段</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($levers as $lever): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="lever_enabled[]" value="<?php echo esc_attr($lever['id']); ?>" <?php checked($lever['enabled']); ?>>
                    </td>
                    <td><strong><?php echo esc_html($lever['label']); ?></strong><br><code style="font-size:11px;color:#888;"><?php echo esc_html($lever['id']); ?></code></td>
                    <td><?php echo esc_html($lever['description']); ?></td>
                    <td><code style="font-size:11px;"><?php echo esc_html(implode(', ', $lever['tasks'])); ?></code></td>
                    <td><code style="font-size:11px;color:#0d7377;"><?php echo esc_html($lever['trace_field']); ?></code></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-bottom:24px;">
            <button type="submit" name="linked3_save_meta_levers" class="button button-primary">💾 保存杠杆配置</button>
        </p>
    </form>
</div>

<div class="linked3-eco-card" style="margin-top:20px;">
    <h3>📐 图示结构注册表</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        <strong><?php echo $structure_count; ?></strong> 种图示结构替代 4Band 一刀切。每镜根据内容自动选择最适合的结构（时间轴/流程图/对比图/数据图/清单/思维导图/引用卡片/4Band）。
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:40px;">图标</th>
                <th style="width:120px;">结构</th>
                <th>描述</th>
                <th style="width:200px;">适用场景</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($structures as $sid => $s): ?>
            <tr>
                <td style="font-size:20px;"><?php echo esc_html($s['icon']); ?></td>
                <td><strong><?php echo esc_html($s['label']); ?></strong><br><code style="font-size:11px;color:#888;"><?php echo esc_html($sid); ?></code></td>
                <td><?php echo esc_html($s['description']); ?></td>
                <td><code style="font-size:11px;"><?php echo esc_html(implode(', ', $s['suitable_for'])); ?></code></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- v27.17.9-fix1: 复合杠杆注册表 (17种高级+复合能力) -->
<div class="linked3-eco-card" style="margin-top:20px;">
    <h3>🧬 复合杠杆注册表</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        <strong><?php echo $composite_count; ?></strong> 种复合杠杆 = 基础杠杆(<?php echo $basic_count; ?>) → 高级编排 → 复合能力。三级分类：
        基础 → 高级 → 复合。每个复合杠杆编排多个基础杠杆协同工作。
    </p>

    <?php if (empty($composite_levers)): ?>
        <div class="notice notice-warning inline"><p>⚠️ 复合杠杆注册表为空。请检查 <code>src/Classes/MetaLever/Composite/</code> 目录是否已加载。</p></div>
    <?php else: ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:40px;">图标</th>
                <th style="width:160px;">复合杠杆</th>
                <th style="width:80px;">级别</th>
                <th>描述</th>
                <th style="width:250px;">编排杠杆</th>
                <th style="width:180px;">场景标签</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($composite_levers as $cl): ?>
            <tr>
                <td style="font-size:20px;">🧬</td>
                <td>
                    <strong><?php echo esc_html($cl['label'] ?? $cl['id']); ?></strong>
                    <br><code style="font-size:11px;color:#888;"><?php echo esc_html($cl['id']); ?></code>
                </td>
                <td>
                    <?php
                    $level = $cl['level'] ?? 'composite';
                    $level_colors = ['basic' => '#3b82f6', 'advanced' => '#8b5cf6', 'composite' => '#ec4899'];
                    $color = $level_colors[$level] ?? '#64748b';
                    ?>
                    <span style="background:<?php echo $color; ?>;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">
                        <?php echo esc_html(ucfirst($level)); ?>
                    </span>
                </td>
                <td><?php echo esc_html($cl['description'] ?? ''); ?></td>
                <td><code style="font-size:11px;"><?php echo esc_html(implode(', ', $cl['levers'] ?? [])); ?></code></td>
                <td><code style="font-size:11px;color:#0d7377;"><?php echo esc_html(implode(', ', $cl['scene_tags'] ?? [])); ?></code></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- 总览统计 -->
<div class="linked3-eco-card" style="margin-top:20px;">
    <h3>📊 杠杆体系总览</h3>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
        <div style="text-align:center;padding:20px;background:#f0f9ff;border-radius:8px;">
            <div style="font-size:32px;font-weight:700;color:#3b82f6;"><?php echo $basic_count; ?></div>
            <div style="font-size:13px;color:#666;margin-top:4px;">基础元杠杆</div>
        </div>
        <div style="text-align:center;padding:20px;background:#faf5ff;border-radius:8px;">
            <div style="font-size:32px;font-weight:700;color:#8b5cf6;"><?php echo $composite_count; ?></div>
            <div style="font-size:13px;color:#666;margin-top:4px;">复合杠杆</div>
        </div>
        <div style="text-align:center;padding:20px;background:#f0fdf4;border-radius:8px;">
            <div style="font-size:32px;font-weight:700;color:#22c55e;"><?php echo $structure_count; ?></div>
            <div style="font-size:13px;color:#666;margin-top:4px;">图示结构</div>
        </div>
    </div>
</div>
