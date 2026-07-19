<?php
/**
 * Dashboard partial: ⚙️ 系统设置 Hub v17.2.0
 *
 * G1 演化结晶 (5-Super-Tab 重组):
 *   公理β: 按用户意图分组 — 所有"配置类/优化类/审计类"功能归入系统设置
 *
 * 系统设置 Hub 结构:
 *   ⚙️ 系统设置 Tab
 *   ├── 🔑 API设置     (sy_sub=api)      — AI Provider密钥
 *   ├── 📈 SEO优化     (sy_sub=seo)      — 关键词/内链/Schema/推送/GEO
 *   ├── 🎤 语音TTS/STT (sy_sub=speech)   — 文字转语音/语音转文字
 *   ├── 📜 授权与套餐  (sy_sub=license)  — License Key + 套餐对比
 *   └── 🛡️ 安全审计   (sy_sub=security) — AJAX端点扫描
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 * @version 17.2.0
 * @date 2026-06-24
 */

if (!defined('ABSPATH')) {
    exit;
}

$sy_sub = isset($_GET['sy_sub']) ? sanitize_key($_GET['sy_sub']) : 'api';

$sy_panels = [
    'api'        => ['API 设置',     '🔑', 'tab-api.php'],
    'seo'        => ['SEO 优化',     '📈', 'tab-seo.php'],
    'speech'     => ['语音 TTS/STT', '🎤', 'tab-speech.php'],
    'license'    => ['授权与套餐',   '📜', 'tab-license.php'],
    'security'   => ['安全审计',     '🛡️', 'tab-security.php'],
    'meta-lever' => ['元杠杆配置',   '🧠', 'tab-meta-lever.php'],
];

if (!isset($sy_panels[$sy_sub])) {
    $sy_sub = 'api';
}

$current_label = $sy_panels[$sy_sub][0];
$current_icon  = $sy_panels[$sy_sub][1];

// v11.4.0: 跨Hub面包屑 + 跳转栏
require_once __DIR__ . '/helper-hub-nav.php';
linked3_render_breadcrumb('system', $sy_sub, $current_label);
linked3_render_hub_jumper('system');
?>

<h2><?php echo esc_html($current_icon); ?> 系统设置
    <span style="font-size:12px;color:#71717A;font-weight:normal;">v11.4.5 · API + SEO + 语音 + 授权 + 安全</span>
</h2>

<div class="notice notice-info inline"><p><strong>系统设置:</strong> 全站配置与优化总控。API密钥管理、SEO全链路优化(关键词/内链/Schema/推送/GEO)、语音TTS/STT、授权套餐升级、AJAX安全审计——所有"设置类"功能统一入口。</p></div>

<?php
// v11.4.5: 配置健康度仪表盘 (方案⑦)
$health_checks = [];

// 1. API Key 配置检测
$provider_keys = (array) get_option(LINKED3_OPTION_PREFIX . 'provider_keys', []);
$has_api_key = false;
foreach ($provider_keys as $pk) {
    if (!empty($pk)) { $has_api_key = true; break; }
}
$health_checks['api'] = [
    'label' => 'API 密钥', 'ok' => $has_api_key,
    'ok_text' => '已配置', 'fail_text' => '未配置 — AI功能不可用',
    'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=api'),
];

// 2. SEO 推送引擎检测
// v17.2.0: 修复 — 各引擎字段名不同(baidu=site+token, bing=api_key, google=service_account等)
$seo_engines = ['baidu','bing','google','toutiao','indexnow'];
$seo_configured = 0;
foreach ($seo_engines as $eng) {
    $cfg = get_option(LINKED3_OPTION_PREFIX . 'push_' . $eng, []);
    $cfg = is_array($cfg) ? $cfg : [];
    // v17.2.0: 综合检测 — 任一关键字段非空即视为已配置
    $has_config = !empty($cfg['enabled']) || !empty($cfg['api_key']) || !empty($cfg['token']) || !empty($cfg['site']) || !empty($cfg['key']);
    if ($has_config) $seo_configured++;
}
$health_checks['seo'] = [
    'label' => 'SEO 推送', 'ok' => $seo_configured > 0,
    'ok_text' => $seo_configured . '/5 引擎已配置', 'fail_text' => '0/5 引擎 — 文章不会被搜索引擎收录',
    'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=seo'),
];

// 3. 授权套餐检测
$license_ok = false;
$license_plan = 'free';
if (class_exists('\\Linked3\\Classes\\License\\LicenseService')) {
    try {
        $lic = \Linked3\Classes\License\LicenseService::instance();
        $license_plan = $lic->plan();
        // v17.2.0: 修复 — plan非free即视为已激活(覆盖pro/premium/enterprise)
        $license_ok = ($license_plan !== 'free');
    } catch (\Throwable $e) {}
}
$health_checks['license'] = [
    'label' => '授权套餐', 'ok' => $license_ok,
    'ok_text' => '已激活 (' . ucfirst($license_plan) . ')', 'fail_text' => '未激活 — 功能受限',
    'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=license'),
];

// 4. 语音套餐检测
// v17.2.0: 修复 — premium也支持语音(原代码漏了premium)
$health_checks['speech'] = [
    'label' => '语音 TTS/STT', 'ok' => in_array($license_plan, ['pro', 'premium', 'enterprise']),
    'ok_text' => '套餐支持 (' . ucfirst($license_plan) . ')', 'fail_text' => '需 Pro+ 套餐',
    'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=speech'),
];

// 5. 安全审计检测
$security_ok = class_exists('\\Linked3\\Classes\\Security\\Linked3_Ajax_Auditor');
$health_checks['security'] = [
    'label' => '安全审计', 'ok' => $security_ok,
    'ok_text' => '模块可用', 'fail_text' => '模块未加载',
    'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=security'),
];

$ok_count = count(array_filter($health_checks, function($c) { return $c['ok']; }));
$total_count = count($health_checks);
$health_pct = round($ok_count / $total_count * 100);
$health_color = $health_pct >= 80 ? '#16a34a' : ($health_pct >= 50 ? '#F59E0B' : '#DC2626');
?>
<div class="linked3-eco-card" style="margin:15px 0;">
    <h3>🩺 配置健康度 <span style="font-size:13px;color:<?php echo esc_attr($health_color); ?>;font-weight:bold;"><?php echo $ok_count; ?>/<?php echo $total_count; ?> (<?php echo $health_pct; ?>%)</span></h3>
    <div style="background:#e5e7eb;border-radius:8px;height:8px;margin:8px 0 12px 0;overflow:hidden;">
        <div style="background:<?php echo esc_attr($health_color); ?>;height:8px;width:<?php echo esc_attr($health_pct); ?>%;border-radius:8px;transition:width 0.3s;"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;">
        <?php foreach ($health_checks as $key => $check): ?>
        <a href="<?php echo esc_url($check['url']); ?>" style="text-decoration:none;display:block;padding:10px;background:<?php echo $check['ok'] ? '#F4F4F5' : '#fef2f2'; ?>;border:1px solid <?php echo $check['ok'] ? '#bbf7d0' : '#FECACA'; ?>;border-radius:6px;text-align:center;">
            <div style="font-size:18px;margin-bottom:4px;"><?php echo $check['ok'] ? '✅' : '⚠️'; ?></div>
            <div style="font-size:12px;font-weight:600;color:#3F3F46;"><?php echo esc_html($check['label']); ?></div>
            <div style="font-size:10px;color:<?php echo $check['ok'] ? '#16a34a' : '#DC2626'; ?>;margin-top:2px;"><?php echo esc_html($check['ok'] ? $check['ok_text'] : $check['fail_text']); ?></div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($health_pct < 100): ?>
    <p style="font-size:11px;color:#71717A;margin:8px 0 0 0;">💡 点击对应卡片修复配置项。健康度≥80%时系统处于最佳运行状态。</p>
    <?php endif; ?>
</div>

<!-- Hub子面板切换 -->
<div class="linked3-eco-subtabs">
    <?php foreach ($sy_panels as $slug => $meta) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=' . $slug)); ?>"
           class="linked3-eco-subtab <?php echo $sy_sub === $slug ? 'active' : ''; ?>">
            <?php echo esc_html($meta[1] . ' ' . $meta[0]); ?>
        </a>
    <?php endforeach; ?>
</div>

<?php
$sy_partial = __DIR__ . '/' . $sy_panels[$sy_sub][2];
if (file_exists($sy_partial)) {
    try {
        include $sy_partial;
    } catch (\Throwable $e) {
        echo '<div class="notice notice-error inline"><p>' . esc_html($e->getMessage()) . '</p></div>';
    }
} else {
    echo '<div class="notice notice-error inline"><p>子面板文件缺失: ' . esc_html($sy_panels[$sy_sub][2]) . '</p></div>';
}
