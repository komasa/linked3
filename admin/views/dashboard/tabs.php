<?php
/**
 * Dashboard tabs router — v28 thin template (PR-05).
 *
 * v4.4.1: 从1494行god-view拆分为thin router.
 * v28.0:  Tab 元数据/redirect/Command Palette 提取至 DashboardTabRegistry.
 *         本文件降级为纯模板, 只读取 Registry 数据并渲染.
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $overview */
/** @var array $chart */

use Linked3\Classes\Dashboard\Registry\DashboardTabRegistry;

// =========================================================================
// v28 PR-05: 所有 Tab 元数据、redirect、partial 路径由 Registry 统一管理
// =========================================================================
$tabs       = DashboardTabRegistry::tabs();
$current_tab = DashboardTabRegistry::resolveTab($_GET['tab'] ?? 'overview');

?>

<div class="wrap">
    <?php
    $css_path = LINKED3_DIR . 'assets/css/linked3-admin.css';
    if (file_exists($css_path)) {
        echo '<style>' . file_get_contents($css_path) . '</style>';
    }
    ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Linked3 AI <span style="font-size:12px;color:#6b7280;font-weight:normal;">v11.7.0 · Invisible Precision</span></h1>
        <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" id="lk3-cmdk-trigger" class="button" style="font-size:12px;" title="快速跳转 (Ctrl+K / Cmd+K)">
                🔍 快速跳转 <kbd style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:3px;padding:1px 4px;font-size:10px;">⌘K</kbd>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard')); ?>" class="button"><?php echo esc_html('← 返回总览'); ?></a>
        </div>
    </div>

    <h2 class="nav-tab-wrapper linked3-nav" style="margin-bottom:20px;">
        <?php foreach ($tabs as $slug => $tab_meta) :
            $is_active = ($current_tab === $slug);
            $tab_color = $tab_meta['color'] ?? '#0F172A';
            $tab_icon  = $tab_meta['icon'] ?? '';
            $tab_label = $tab_meta['label'] ?? $slug;
        ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=' . $slug)); ?>"
           class="nav-tab linked3-super-tab <?php echo $is_active ? 'nav-tab-active linked3-super-tab-active' : ''; ?>"
           data-tab-color="<?php echo esc_attr($tab_color); ?>"
           style="<?php echo $is_active ? 'border-bottom-color:' . esc_attr($tab_color) . ';' : ''; ?>">
            <span class="linked3-tab-icon" style="font-size:16px;line-height:1;margin-right:6px;<?php echo $is_active ? 'opacity:1;' : 'opacity:0.6;'; ?>"><?php echo esc_html($tab_icon); ?></span>
            <span class="linked3-tab-label" style="font-size:13px;font-weight:<?php echo $is_active ? '600' : '500'; ?>;color:<?php echo $is_active ? esc_attr($tab_color) : '#71717A'; ?>;"><?php echo esc_html($tab_label); ?></span>
        </a>
        <?php endforeach; ?>
    </h2>

    <?php
    // 当前TAB描述条
    $current_tab_meta = $tabs[$current_tab] ?? null;
    if ($current_tab_meta && !empty($current_tab_meta['desc'])) :
        $ct_color = $current_tab_meta['color'];
        $ct_icon  = $current_tab_meta['icon'];
        $ct_label = $current_tab_meta['label'];
        $ct_desc  = $current_tab_meta['desc'];
    ?>
    <div class="linked3-tab-breadcrumb" style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:#FAFAFA;border:1px solid #E4E4E7;border-left:3px solid <?php echo esc_attr($ct_color); ?>;border-radius:6px;margin:0 0 16px 0;">
        <span style="font-size:18px;line-height:1;"><?php echo esc_html($ct_icon); ?></span>
        <div style="display:flex;flex-direction:column;gap:1px;">
            <strong style="font-size:14px;color:<?php echo esc_attr($ct_color); ?>;font-weight:600;letter-spacing:-0.01em;"><?php echo esc_html($ct_label); ?></strong>
            <span style="font-size:12px;color:#71717A;line-height:1.4;"><?php echo esc_html($ct_desc); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="linked3-tab-content">
    <?php
    $partial_path = DashboardTabRegistry::partialPath($current_tab);

    if (!file_exists($partial_path)) {
        echo '<div class="notice notice-error"><p>'
            . esc_html(sprintf(
                __('未知标签 "%s" — partial 文件不存在。', 'linked3'),
                $current_tab
            ))
            . '</p></div>';
    } else {
        try {
            include $partial_path;
        } catch (\Throwable $e) {
            echo '<div class="notice notice-error"><p>'
                . esc_html($e->getMessage())
                . '</p></div>';
        }
    }
    ?>
    </div>

    <?php
    // ⌘K命令面板 — v28 PR-05: 数据来自 Registry
    $cmdk_commands = DashboardTabRegistry::commandPaletteCommands();
    ?>
    <div id="lk3-cmdk-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:99998;align-items:flex-start;justify-content:center;padding-top:80px;">
        <div id="lk3-cmdk-dialog" style="background:#fff;border-radius:8px;width:90%;max-width:560px;box-shadow:0 20px 60px rgba(0,0,0,0.3);z-index:99999;overflow:hidden;">
            <div style="padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                <input type="text" id="lk3-cmdk-input" placeholder="输入关键词跳转... (如: 写作、发布、SEO、Agent)" style="width:100%;border:none;outline:none;font-size:15px;" autocomplete="off">
            </div>
            <div id="lk3-cmdk-list" style="max-height:360px;overflow-y:auto;"></div>
            <div style="padding:8px 16px;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;display:flex;justify-content:space-between;">
                <span>↑↓ 选择 · Enter 跳转 · Esc 关闭</span>
                <span><?php echo count($cmdk_commands); ?> 个快捷入口</span>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var commands = <?php echo json_encode($cmdk_commands); ?>;
        var overlay = document.getElementById('lk3-cmdk-overlay');
        var input = document.getElementById('lk3-cmdk-input');
        var list = document.getElementById('lk3-cmdk-list');
        var trigger = document.getElementById('lk3-cmdk-trigger');
        var selectedIdx = 0;

        var RECENT_KEY = 'lk3_cmdk_recent';
        var recent = [];
        try { recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch(e) { recent = []; }

        function saveRecent(url, label){
            recent = recent.filter(function(r){ return r.url !== url; });
            recent.unshift({url: url, label: label, ts: Date.now()});
            recent = recent.slice(0, 5);
            try { localStorage.setItem(RECENT_KEY, JSON.stringify(recent)); } catch(e) {}
        }

        function render(filter){
            filter = (filter || '').toLowerCase();
            var html = '';

            if (!filter && recent.length > 0) {
                html += '<div style="padding:6px 16px;background:#f9fafb;font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.5px;">🕐 最近访问</div>';
                recent.forEach(function(r, i){
                    html += '<div class="lk3-cmdk-item" data-url="'+r.url+'" data-idx="'+i+'" style="padding:10px 16px;cursor:pointer;border-bottom:1px solid #f3f4f6;'+(i===0?'background:#eff6ff;':'')+'">'
                        + '<div style="font-size:13px;font-weight:500;">'+r.label+'</div>'
                        + '<div style="font-size:10px;color:#9ca3af;">'+new Date(r.ts).toLocaleString()+'</div>'
                        + '</div>';
                });
                html += '<div style="padding:6px 16px;background:#f9fafb;font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.5px;">📋 全部命令</div>';
            }

            var matched = commands.filter(function(c){
                return c.label.toLowerCase().indexOf(filter) > -1 || c.desc.toLowerCase().indexOf(filter) > -1;
            });
            if (matched.length === 0 && !recent.length) {
                list.innerHTML = '<div style="padding:24px;text-align:center;color:#9ca3af;">无匹配项</div>';
                return;
            }
            var offsetBase = (!filter && recent.length > 0) ? recent.length : 0;
            html += matched.map(function(c, i){
                var idx = i + offsetBase;
                var is_first = idx === 0;
                return '<div class="lk3-cmdk-item" data-url="'+c.url+'" data-idx="'+idx+'" style="padding:10px 16px;cursor:pointer;border-bottom:1px solid #f3f4f6;'+(is_first?'background:#eff6ff;':'')+'">'
                    + '<div style="font-size:13px;font-weight:500;">'+c.label+'</div>'
                    + '<div style="font-size:11px;color:#6b7280;">'+c.desc+'</div>'
                    + '</div>';
            }).join('');
            list.innerHTML = html;
            selectedIdx = 0;
            list.querySelectorAll('.lk3-cmdk-item').forEach(function(el){
                el.addEventListener('mouseenter', function(){
                    list.querySelectorAll('.lk3-cmdk-item').forEach(function(x){x.style.background='';});
                    this.style.background = '#eff6ff';
                    selectedIdx = parseInt(this.getAttribute('data-idx'));
                });
                el.addEventListener('click', function(){
                    var url = this.getAttribute('data-url');
                    var labelEl = this.querySelector('div');
                    saveRecent(url, labelEl ? labelEl.textContent : url);
                    window.location.href = url;
                });
            });
        }
        function open() { overlay.style.display='flex'; input.value=''; render(''); setTimeout(function(){input.focus();},50); }
        function close() { overlay.style.display='none'; }

        if (trigger) trigger.addEventListener('click', open);
        input.addEventListener('input', function(){ render(this.value); });
        input.addEventListener('keydown', function(e){
            var items = list.querySelectorAll('.lk3-cmdk-item');
            if (e.key === 'ArrowDown') { e.preventDefault(); if (selectedIdx < items.length-1) { selectedIdx++; items[selectedIdx].scrollIntoView({block:'nearest'}); } }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (selectedIdx > 0) { selectedIdx--; items[selectedIdx].scrollIntoView({block:'nearest'}); } }
            else if (e.key === 'Enter') {
                e.preventDefault();
                var it = items[selectedIdx];
                if (it) {
                    var url = it.getAttribute('data-url');
                    var labelEl = it.querySelector('div');
                    saveRecent(url, labelEl ? labelEl.textContent : url);
                    window.location.href = url;
                }
            }
            else if (e.key === 'Escape') { close(); }
            if (items.length) { items.forEach(function(x){x.style.background='';}); items[selectedIdx] && (items[selectedIdx].style.background='#eff6ff'); }
        });
        overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });

        document.addEventListener('keydown', function(e){
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); overlay.style.display === 'flex' ? close() : open(); }
        });
    })();
    </script>

</div>
