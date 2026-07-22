<?php
/**
 * Dashboard tabs router (v11.7.1 — G2 风格库融合修复).
 *
 * v4.4.1: 从1494行god-view拆分为thin router.
 * v10.7.3: 1+4融合 + 视觉生态3合1 + 自动Agent含队列.
 * v11.3.1: G1演化结晶 — 14顶层Tab坍缩为5 Super-Tab (决策熵 3.81→2.32 bit).
 * v11.7.1: G2修复 — 风格库独立Tab移除, 融合至视觉生态·图示脚本生成配置
 *          (消除功能同质冗余, 决策熵 2.32→2.12 bit).
 *
 * 6 Super-Tab 结构 (按用户意图分组, 非按代码模块):
 *   🏠 总览       [overview]     — 保持不变
 *   ✍️ 创作中心   [creation]     — 写作生态 + 视觉生态(含风格库) + 云模版
 *   📤 分发中心   [distribution] — 发布采集 + 社交分发 + 电商表单
 *   🤖 自动化     [automation]   — 自动Agent + AI对话
 *   🔮 拆解OS  [v18]          — 前沿实验功能
 *   ⚙️ 系统设置   [system]       — API + SEO + 语音 + 授权 + 安全
 *
 * 公理α: 信息熵减 — 14→6顶层Tab, 风格库融合至图示脚本, 决策熵-43%
 * 公理β: 系统降维 — 按用户意图分组, 同质功能归一
 * 公理γ: 搭积木 — 同类归一Tab, 子面板像积木插入
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $overview */
/** @var array $chart */

// =========================================================================
// v12.0: 6 Super-Tab 注册表 — 国际顶级规范视觉区分 (参照Linear/Notion/Vercel)
// 每个 TAB 附带: icon(图标) + color(色标) + desc(描述) + short(短标签)
// =========================================================================
$tabs = [
    'overview' => [
        'label'  => __('总览', 'linked3'),
        'icon'   => '🏠',
        'color'  => '#6366F1',  // Indigo — 数据概览
        'desc'   => __('数据看板 · 快速概览', 'linked3'),
        'short'  => __('总览', 'linked3'),
    ],
    'cognitive-os' => [
        'label'  => __('认知OS', 'linked3'),
        'icon'   => '🧠',
        'color'  => '#667eea',  // Lapis Tech — 认知操作系统
        'desc'   => __('双公理 · 五部门 · 三代演化 · 十二杠杆', 'linked3'),
        'short'  => __('认知OS', 'linked3'),
    ],
    'creation' => [
        'label'  => __('创作中心', 'linked3'),
        'icon'   => '✍️',
        'color'  => '#0F172A',  // 墨黑 — 核心创作
        'desc'   => __('写作生态 · 视觉生态 · 云模版', 'linked3'),
        'short'  => __('创作', 'linked3'),
    ],
    'distribution' => [
        'label'  => __('分发中心', 'linked3'),
        'icon'   => '📤',
        'color'  => '#059669',  // Emerald — 分发增长
        'desc'   => __('发布采集 · 社交分发 · 电商表单', 'linked3'),
        'short'  => __('分发', 'linked3'),
    ],
    'automation' => [
        'label'  => __('自动化', 'linked3'),
        'icon'   => '🤖',
        'color'  => '#7C3AED',  // Violet — AI自动化
        'desc'   => __('自动Agent · AI对话 · 定时任务', 'linked3'),
        'short'  => __('自动化', 'linked3'),
    ],
    'v18' => [
        'label'  => __('拆解OS', 'linked3'),
        'icon'   => '🔮',
        'color'  => '#DB2777',  // Pink — 实验前沿
        'desc'   => __('前沿实验 · 创新功能', 'linked3'),
        'short'  => __('实验室', 'linked3'),
    ],
    'system' => [
        'label'  => __('系统设置', 'linked3'),
        'icon'   => '⚙️',
        'color'  => '#475569',  // Slate — 系统配置
        'desc'   => __('API · SEO · 语音 · 授权 · 安全', 'linked3'),
        'short'  => __('系统', 'linked3'),
    ],
];

$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';

// =========================================================================
// v11.3.1: 旧Tab → 新Super-Tab 重定向表 (100%向后兼容, 301语义)
// =========================================================================
// 格式: '旧tab' => ['新tab', '子参数名', '子参数值']
$legacy_redirect_map = [
    // → 创作中心
    'ecosystem' => ['creation', 'cr_sub', 'ecosystem'],
    'visual'    => ['creation', 'cr_sub', 'visual'],
    'cloud'     => ['creation', 'cr_sub', 'cloud'],
    // v11.7.1: 风格库独立Tab移除, 重定向至视觉生态·图示脚本(风格库已融合)
    'style-library' => ['creation', 'cr_sub', 'visual'],
    // → 分发中心
    'publish'    => ['distribution', 'di_sub', 'publish'],
    'distribute' => ['distribution', 'di_sub', 'distribute'],
    'commerce'   => ['distribution', 'di_sub', 'commerce'],
    // → 自动化
    'autogpt' => ['automation', 'au_sub', 'autogpt'],
    'chat'    => ['automation', 'au_sub', 'chat'],
    // → 系统设置
    'api'      => ['system', 'sy_sub', 'api'],
    'seo'      => ['system', 'sy_sub', 'seo'],
    'speech'   => ['system', 'sy_sub', 'speech'],
    'license'  => ['system', 'sy_sub', 'license'],
    'security' => ['system', 'sy_sub', 'security'],
];

// v10.7.3 遗留重定向 (1+4融合 + 视觉生态 + 队列) — 保留兼容
$eco_redirect_map = [
    'content'   => 'content',
    'keywords'  => 'keywords',
    'templates' => 'templates',
    'images'    => 'images',
];
$visual_redirect_map = [
    'charts'  => 'charts',
    'genesis' => 'genesis',
    'video'   => 'video',
    'xhs'     => 'xhs',
];

// 执行重定向 (旧Tab → 新Super-Tab)
if (isset($legacy_redirect_map[$current_tab])) {
    list($new_tab, $sub_key, $sub_val) = $legacy_redirect_map[$current_tab];
    $redirect_url = admin_url('admin.php?page=linked3-dashboard&tab=' . $new_tab . '&' . $sub_key . '=' . $sub_val);
    wp_safe_redirect($redirect_url);
    exit;
}

// v10.7.3 遗留: queue → 自动化 > Agent > 队列子面板
if ($current_tab === 'queue') {
    wp_safe_redirect(admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt&sub=queue'));
    exit;
}
// v10.7.3 遗留: content/keywords/templates/images → 创作中心 > 写作生态
if (isset($eco_redirect_map[$current_tab])) {
    wp_safe_redirect(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=' . $eco_redirect_map[$current_tab]));
    exit;
}
// v10.7.3 遗留: charts/genesis/video → 创作中心 > 视觉生态
if (isset($visual_redirect_map[$current_tab])) {
    wp_safe_redirect(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual&vs_sub=' . $visual_redirect_map[$current_tab]));
    exit;
}

// Whitelist the tab slug before resolving a partial path — defends against
// path-traversal attempts via a crafted `?tab=` value.
if (!array_key_exists($current_tab, $tabs)) {
    $current_tab = 'overview';
}
?>

<div class="wrap">
    <?php
    // v11.7.0: 确保全局CSS加载 (Invisible Precision设计系统)
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
            $tab_desc  = $tab_meta['desc'] ?? '';
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
    // v12.0: 当前TAB描述条 (参照Linear/Vercel的面包屑式描述)
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
    $partial_path = LINKED3_DIR . 'admin/views/dashboard/partials/tab-' . $current_tab . '.php';

    if (!file_exists($partial_path)) {
        echo '<div class="notice notice-error"><p>'
            . esc_html(sprintf(
                /* translators: %s: tab slug. */
                __('未知标签 "%s" — partial 文件不存在。', 'linked3'),
                $current_tab
            ))
            . '</p></div>';
    } else {
        try {
            // Each partial inherits $overview, $chart, $current_tab from this scope.
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
    // v11.5.1: ⌘K命令面板 (P0) — 全局快速跳转
    $cmdk_commands = [
        ['label' => '🏠 总览', 'desc' => 'Dashboard首页', 'url' => admin_url('admin.php?page=linked3-dashboard')],
        ['label' => '✍️ 创作中心 · 写作生态', 'desc' => '关键词/模版/内容写作/图片', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem')],
        ['label' => '✍️ 创作中心 · 视觉生态', 'desc' => '图示/漫画/视频/小红书脚本', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual')],
        ['label' => '✍️ 创作中心 · 云模版', 'desc' => '50场景母版库', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud')],
        ['label' => '📤 分发中心 · 发布与采集', 'desc' => '多目标发布+URL采集', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish')],
        ['label' => '📤 分发中心 · 社交分发', 'desc' => '15+平台同步', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=distribute')],
        ['label' => '📤 分发中心 · 电商与表单', 'desc' => 'WooCommerce+AI表单', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=commerce')],
        ['label' => '🤖 自动化 · 自动Agent', 'desc' => '定时任务+队列', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt')],
        ['label' => '🤖 自动化 · AI对话', 'desc' => '浮动客服+RAG', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=chat')],
        ['label' => '⚙️ 系统设置 · API密钥', 'desc' => 'AI Provider配置', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=api')],
        ['label' => '⚙️ 系统设置 · SEO优化', 'desc' => '关键词/内链/Schema/推送', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=seo')],
        ['label' => '⚙️ 系统设置 · 授权套餐', 'desc' => 'License+套餐对比', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=license')],
        ['label' => '⚙️ 系统设置 · 安全审计', 'desc' => 'AJAX端点扫描', 'url' => admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=security')],
    ];
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

        // v11.6.0: 最近访问记录 (localStorage, 最多5条)
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

            // v11.6.0: 空搜索时显示最近访问
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

        // 全局快捷键 Ctrl+K / Cmd+K
        document.addEventListener('keydown', function(e){
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); overlay.style.display === 'flex' ? close() : open(); }
        });
    })();
    </script>

</div>
