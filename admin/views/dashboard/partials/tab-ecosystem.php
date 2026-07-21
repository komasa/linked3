<?php
/**
 * Dashboard partial: 写作生态 v17.2.0 — 1+4融合重铸 + 全功能链
 *
 * v17.2.0 重铸 (破而后立):
 *   公理O: 1+4融合 — 4模块坍缩为写作生态子面板
 *   公理P: UI组件统一设计系统
 *   公理Q: 生态数据流可视化
 *   公理R: 云模版跨生态共享
 *   公理S: 云模版是总控母版库 (顶层Tab), 写作生态用本地模版
 *   公理T: 本地模版从云模版拉取母版, 隔离修改, 不污染母版
 *   公理U: 旧模块全功能整合 — 热词采集/长文写作/图片站/图片插入等全部进入
 *
 * 生态结构 (全功能链):
 *   🚀 写作生态 Tab
 *   ├── 生态协同 (默认, 一键全流程)
 *   ├── 关键词 (热词采集 + 三维度生成 + 历史) [eco_sub=keywords]
 *   ├── 本地模版 (从云模版拉取母版, 隔离修改) [eco_sub=templates]
 *   ├── 内容写作 (快速/长文/CSV批量, 5阶段) [eco_sub=content]
 *   └── 图片设置 (AI生成 + 图库API + 图片站采集 + 插入位置) [eco_sub=images]
 *
 * 跨生态通道:
 *   ☁ 云模版总控Tab → 写作生态本地模版 (Fork母版)
 *   ☁ 云模版总控Tab → 图示/漫画/视频脚本 (直接消费)
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-24
 */
if (!defined('ABSPATH')) exit;

$nonce_eco = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// v16.0.13 [公理α: H↓ 路由确定性] [公理β: dim↓ 1维白名单替代N维补丁]
// v12.0: 写作生态全量补全 — 新增SEO优化/标题生成/摘要生成/改写润色 (参照Jasper/Copy.ai/Notion AI)
// 白名单闸门: 消除路径穿越风险 + 消除"点哪个子面板"的不确定性
$eco_sub_whitelist = ['synergy', 'keywords', 'templates', 'content', 'images', 'seo', 'title', 'summary', 'rewrite', 'book'];
$eco_sub_raw = isset($_GET['eco_sub']) ? sanitize_key($_GET['eco_sub']) : 'synergy';
$eco_sub = in_array($eco_sub_raw, $eco_sub_whitelist, true) ? $eco_sub_raw : 'synergy';

// v17.2.0: 云模版总控状态检测
$cloud_master_active = class_exists('CloudTemplateFactory');
$script_ecosystem_tabs = ['charts' => '图示脚本', 'genesis' => '漫画脚本', 'video' => '视频脚本'];
$cloud_master_url = admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud');
?>

<!-- v12.0: 写作生态 — 参照Jasper/Copy.ai/Notion AI国际顶级写作工具规范 -->

<h2>🚀 写作生态 <span style="font-size:12px;color:#71717A;font-weight:normal;">v17.0 · 8模块全链路 + AI写作增强</span></h2>

<div class="linked3-tab-breadcrumb" style="border-left-color:#0F172A;">
    <span style="font-size:18px;line-height:1;">🚀</span>
    <div style="display:flex;flex-direction:column;gap:1px;">
        <strong style="font-size:14px;color:#0F172A;font-weight:600;letter-spacing:-0.01em;">写作生态</strong>
        <span style="font-size:12px;color:#71717A;line-height:1.4;">关键词 → 模版 → 内容 → 图片 → SEO → 标题 → 摘要 → 改写 → 写书 · 9模块协同</span>
    </div>
</div>

<?php if ($cloud_master_active): ?>
<div class="notice notice-success inline" style="border-left-color:#10B981;"><p><strong>☁ 云模版总控已激活:</strong>
    <a href="<?php echo esc_url($cloud_master_url); ?>" style="margin-right:12px;text-decoration:none;">母版库管理 →</a>
    <a href="<?php echo esc_url($cloud_master_url); ?>" style="margin-right:12px;text-decoration:none;">Fork到本地 →</a>
<?php foreach ($script_ecosystem_tabs as $stab => $slabel): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=' . $stab)); ?>" style="margin-right:12px;text-decoration:none;"><?php echo esc_html($slabel); ?> →</a>
<?php endforeach; ?>
跨生态共享同一母版池。
</p></div>
<?php endif; ?>

<!-- 公理Q: 生态数据流可视化 (v12.0: 8模块全链路) -->
<div class="linked3-eco-card">
    <h3>生态数据流</h3>
    <div class="linked3-eco-flow">
        <span class="linked3-eco-flow-step active">① 关键词</span>
        <span class="linked3-eco-flow-arrow">→</span>
        <span class="linked3-eco-flow-step">② 模版</span>
        <span class="linked3-eco-flow-arrow">→</span>
        <span class="linked3-eco-flow-step">③ 内容</span>
        <span class="linked3-eco-flow-arrow">→</span>
        <span class="linked3-eco-flow-step">④ 图片</span>
        <span class="linked3-eco-flow-arrow">→</span>
        <span class="linked3-eco-flow-step" style="background:#F4F4F5;border-color:#D4D4D8;color:#3F3F46;">⑤ SEO</span>
        <span class="linked3-eco-flow-arrow">→</span>
        <span class="linked3-eco-flow-step" style="background:#F4F4F5;border-color:#D4D4D8;color:#3F3F46;">⑥ 标题</span>
        <span class="linked3-eco-flow-arrow">→</span>
        <span class="linked3-eco-flow-step" style="background:#F4F4F5;border-color:#D4D4D8;color:#3F3F46;">⑦ 摘要</span>
        <span class="linked3-eco-flow-arrow">→</span>
        <span class="linked3-eco-flow-step" style="background:#F4F4F5;border-color:#D4D4D8;color:#3F3F46;">⑧ 改写</span>
        <span class="linked3-eco-flow-arrow">↻</span>
        <span class="linked3-eco-flow-step" style="background:#FEF3C7;border-color:#F59E0B;color:#92400E;">反馈优化</span>
    </div>
    <div style="margin-top:8px;font-size:11px;color:#A1A1AA;">
        ☁ <a href="<?php echo esc_url($cloud_master_url); ?>" style="color:#0F172A;text-decoration:none;">云模版总控</a> 同时向脚本生态输出:
        <?php foreach ($script_ecosystem_tabs as $stab => $slabel): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=' . $stab)); ?>" style="color:#0F172A;text-decoration:none;margin-right:8px;"><?php echo esc_html($slabel); ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- 子面板切换 (v12.0: 8模块全量 — 参照Jasper/Copy.ai/Notion AI) -->
<div class="linked3-eco-subtabs">
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=synergy')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'synergy' ? 'active' : ''; ?>">⚡ 生态协同</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=keywords')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'keywords' ? 'active' : ''; ?>">🔑 关键词</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=templates')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'templates' ? 'active' : ''; ?>">📋 本地模版</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'content' ? 'active' : ''; ?>">📝 内容写作</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=images')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'images' ? 'active' : ''; ?>">🖼️ 图片设置</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=seo')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'seo' ? 'active' : ''; ?>">🔍 SEO优化</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=title')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'title' ? 'active' : ''; ?>">💡 标题生成</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=summary')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'summary' ? 'active' : ''; ?>">📄 摘要生成</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=rewrite')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'rewrite' ? 'active' : ''; ?>">✏️ 改写润色</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=book')); ?>"
       class="linked3-eco-subtab <?php echo $eco_sub === 'book' ? 'active' : ''; ?>">📖 写书式写作</a>
</div>

<?php
// 子面板路由
// v16.0.8: Enhanced routing with debug logging and fallback
$eco_partial = __DIR__ . '/eco-' . $eco_sub . '.php';
if (file_exists($eco_partial)) {
    try {
        include $eco_partial;
    } catch (\Throwable $e) {
        // v16.0.8: Show the actual error instead of silently falling back
        echo '<div class="notice notice-error inline"><p>';
        echo '<strong>关键词面板加载失败:</strong> ' . esc_html($e->getMessage());
        echo '<br><small>File: ' . esc_html($e->getFile()) . ':' . (int) $e->getLine() . '</small>';
        echo '</p></div>';
        if (function_exists('error_log')) {
            error_log('[Linked3] eco-keywords load failed: ' . $e->getMessage());
        }
    }
} else {
    // v16.0.8: Show which file was expected
    echo '<div class="notice notice-warning inline"><p>';
    echo '<strong>子面板文件缺失:</strong> ' . esc_html($eco_partial);
    echo '<br><small>eco_sub = ' . esc_html($eco_sub) . '</small>';
    echo '</p></div>';
    // 默认显示生态协同面板
    $fallback = __DIR__ . '/eco-synergy.php';
    if (file_exists($fallback)) {
        include $fallback;
    }
}
