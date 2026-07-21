<?php
/**
 * Dashboard partial: 🤖 自动化 Hub v17.2.0
 *
 * G1 演化结晶 (5-Super-Tab 重组):
 *   公理β: 按用户意图分组 — "无人值守运行"统一归入自动化
 *
 * 自动化 Hub 结构:
 *   🤖 自动化 Tab
 *   ├── 🤖 自动Agent (au_sub=autogpt) — 定时任务 + 任务队列(子面板)
 *   └── 💬 AI对话    (au_sub=chat)    — 浮动客服窗 + RAG知识库
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-24
 */

if (!defined('ABSPATH')) {
    exit;
}

$au_sub = isset($_GET['au_sub']) ? sanitize_key($_GET['au_sub']) : 'autogpt';

$au_panels = [
    'autogpt' => ['自动 Agent', '🤖', 'tab-autogpt.php'],
    'chat'    => ['AI 对话',    '💬', 'tab-chat.php'],
];

if (!isset($au_panels[$au_sub])) {
    $au_sub = 'autogpt';
}

$current_label = $au_panels[$au_sub][0];
$current_icon  = $au_panels[$au_sub][1];

// v11.4.0: 跨Hub面包屑 + 跳转栏
require_once __DIR__ . '/helper-hub-nav.php';
linked3_render_breadcrumb('automation', $au_sub, $current_label);
linked3_render_hub_jumper('automation');
?>

<h2><?php echo esc_html($current_icon); ?> 自动化
    <span style="font-size:12px;color:#71717A;font-weight:normal;">v11.4.0 · Agent + 对话</span>
</h2>

<div class="notice notice-info inline"><p><strong>自动化:</strong> 无人值守运行的核心。自动Agent定时执行采集/生成/发布全流程，AI对话提供浮动客服窗与RAG知识库检索，让站点7×24小时自运转。</p></div>

<!-- Hub子面板切换 -->
<div class="linked3-eco-subtabs">
    <?php foreach ($au_panels as $slug => $meta) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=' . $slug)); ?>"
           class="linked3-eco-subtab <?php echo $au_sub === $slug ? 'active' : ''; ?>">
            <?php echo esc_html($meta[1] . ' ' . $meta[0]); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php
$au_partial = __DIR__ . '/' . $au_panels[$au_sub][2];
if (file_exists($au_partial)) {
    try {
        include $au_partial;
    } catch (\Throwable $e) {
        echo '<div class="notice notice-error inline"><p>' . esc_html($e->getMessage()) . '</p></div>';
    }
} else {
    echo '<div class="notice notice-error inline"><p>子面板文件缺失: ' . esc_html($au_panels[$au_sub][2]) . '</p></div>';
}
