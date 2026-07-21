<?php
/**
 * 图片设置子面板 v17.0 — 全功能链整合 (AI生成 + 图库API + 图片站采集 + 插入位置)
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的图片配置界面规范
 *   - 保留全部功能: AI生成/图库API/图片站采集/信息图20布局/插入位置
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_img = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

$img_settings = wp_parse_args((array) get_option(LINKED3_OPTION_PREFIX . 'image_settings', []), [
    'provider' => 'openai', 'model' => 'dall-e-3',
    'img_width' => 800, 'img_height' => 600,
    'insert_position' => 'after_first_h2',
    'resolution' => '1280*1280',
    'layouts' => [],
    'stock_provider' => 'unsplash',
    'stock_api_key' => '',
    'image_site_url' => '',
    'image_site_count' => 3,
]);
$saved_layouts = (array) ($img_settings['layouts'] ?? []);
$saved_resolution = $img_settings['resolution'] ?? '1280*1280';
$img_mode = isset($_GET['img_mode']) ? sanitize_key($_GET['img_mode']) : 'ai';
?>

<div class="linked3-eco-card">
    <h3>图片设置 — 全功能链 (AI生成 + 图库API + 图片站采集 + 插入位置)</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:12px;">feicai4.0万相5分辨率 + 宝玉20布局 + 多源图片采集</p>

    <!-- 图片模式切换 -->
    <div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid #e5e7eb;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=images&img_mode=ai')); ?>"
           class="linked3-eco-subtab <?php echo $img_mode === 'ai' ? 'active' : ''; ?>">🎨 AI生成</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=images&img_mode=stock')); ?>"
           class="linked3-eco-subtab <?php echo $img_mode === 'stock' ? 'active' : ''; ?>">📷 图库API</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=images&img_mode=site')); ?>"
           class="linked3-eco-subtab <?php echo $img_mode === 'site' ? 'active' : ''; ?>">🌐 图片站采集</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=images&img_mode=insert')); ?>"
           class="linked3-eco-subtab <?php echo $img_mode === 'insert' ? 'active' : ''; ?>">📍 插入位置</a>
    </div>

    <?php if ($img_mode === 'ai'): ?>
    <!-- AI生成模式 -->
    <h4 style="font-size:13px;margin-bottom:8px;">🎨 AI图片生成 (万相5分辨率)</h4>

    <!-- v11.2.0 #1: 去同质化提示 — 图片API在API设置页统一配置 -->
    <div style="background:#FAFAFA;border:1px solid #0F172A;border-radius:4px;padding:8px 12px;margin-bottom:12px;font-size:12px;color:#0F172A;">
        💡 图片生成API (供应商/模型/Key) 已在 <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=api')); ?>" style="color:#0F172A;font-weight:600;text-decoration:underline;">API设置页</a> 统一配置, 此处仅选择供应商和分辨率。修改API Key请去API设置页。
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
            <label style="font-size:12px;color:#71717A;">图片供应商</label>
            <select class="linked3-eco-select" id="img-provider" style="width:100%;">
                <!-- v11.2.0 #1: 补充硅基流动(默认) + 阿里万相(5分辨率) -->
                <option value="siliconflow" <?php selected($img_settings['provider'] ?? 'siliconflow', 'siliconflow'); ?>>硅基流动 (Kwai-Kolors, 推荐)</option>
                <option value="wanx" <?php selected($img_settings['provider'], 'wanx'); ?>>阿里万相 (5分辨率)</option>
                <option value="openai" <?php selected($img_settings['provider'], 'openai'); ?>>OpenAI DALL-E</option>
                <option value="stability" <?php selected($img_settings['provider'], 'stability'); ?>>Stability AI</option>
                <option value="midjourney" <?php selected($img_settings['provider'], 'midjourney'); ?>>Midjourney (需手动)</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">分辨率 (万相5分辨率)</label>
            <select class="linked3-eco-select" id="img-resolution" style="width:100%;">
                <option value="1280*1280" <?php selected($saved_resolution, '1280*1280'); ?>>1280×1280 (方形)</option>
                <option value="720*1280" <?php selected($saved_resolution, '720*1280'); ?>>720×1280 (竖版)</option>
                <option value="1280*720" <?php selected($saved_resolution, '1280*720'); ?>>1280×720 (横版)</option>
                <option value="960*1280" <?php selected($saved_resolution, '960*1280'); ?>>960×1280 (长竖)</option>
                <option value="1664*928" <?php selected($saved_resolution, '1664*928'); ?>>1664×928 (宽横)</option>
            </select>
        </div>
    </div>

    <!-- v11.2.0 #1: 当前API配置状态显示 -->
    <?php
    $cur_provider = get_option(LINKED3_OPTION_PREFIX . 'image_provider', 'siliconflow');
    $cur_model = get_option(LINKED3_OPTION_PREFIX . 'image_model', 'Kwai-Kolors/Kolors');
    $cur_key = get_option(LINKED3_OPTION_PREFIX . 'image_api_key', '');
    $provider_labels = [
        'siliconflow' => '硅基流动',
        'wanx' => '阿里万相',
        'openai' => 'OpenAI',
        'stability' => 'Stability AI',
        'midjourney' => 'Midjourney',
    ];
    $cur_label = $provider_labels[$cur_provider] ?? $cur_provider;
    ?>
    <div style="margin-top:8px;padding:8px 12px;background:#f9fafb;border-radius:4px;font-size:11px;color:#71717A;">
        <strong>当前API配置:</strong> <?php echo esc_html($cur_label); ?> · <?php echo esc_html($cur_model); ?> · API Key: <?php echo !empty($cur_key) ? '✅ 已配置' : '❌ 未配置 (去API设置页配置)'; ?>
    </div>

    <?php elseif ($img_mode === 'stock'): ?>
    <!-- 图库API模式 -->
    <h4 style="font-size:13px;margin-bottom:8px;">📷 图库API (免费图库)</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
            <label style="font-size:12px;color:#71717A;">图库供应商</label>
            <select class="linked3-eco-select" id="img-stock-provider" style="width:100%;">
                <option value="unsplash" <?php selected($img_settings['stock_provider'] ?? '', 'unsplash'); ?>>Unsplash (免费)</option>
                <option value="pexels" <?php selected($img_settings['stock_provider'] ?? '', 'pexels'); ?>>Pexels (免费)</option>
                <option value="pixabay" <?php selected($img_settings['stock_provider'] ?? '', 'pixabay'); ?>>Pixabay (免费)</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">API Key (可选, 免费申请)</label>
            <input type="text" class="linked3-eco-input" id="img-stock-key" value="<?php echo esc_attr($img_settings['stock_api_key'] ?? ''); ?>" placeholder="留空用公共配额" style="width:100%;">
        </div>
    </div>
    <div style="margin-top:8px;font-size:11px;color:#9ca3af;">💡 免费图库API, 无需付费。建议申请自己的API Key以获得更高配额。</div>

    <?php elseif ($img_mode === 'site'): ?>
    <!-- 图片站采集模式 -->
    <h4 style="font-size:13px;margin-bottom:8px;">🌐 图片站采集 (自定义URL)</h4>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">
        <div>
            <label style="font-size:12px;color:#71717A;">图片站URL (采集源)</label>
            <input type="text" class="linked3-eco-input" id="img-site-url" value="<?php echo esc_attr($img_settings['image_site_url'] ?? ''); ?>" placeholder="https://example.com/images" style="width:100%;">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">每篇采集数量</label>
            <input type="number" class="linked3-eco-input" id="img-site-count" value="<?php echo esc_attr($img_settings['image_site_count'] ?? 3); ?>" min="1" max="20" style="width:100%;">
        </div>
    </div>
    <div style="margin-top:8px;font-size:11px;color:#9ca3af;">💡 从指定图片站URL采集图片, 自动插入文章。支持RSS/HTML页面解析。</div>

    <?php else: ?>
    <!-- 插入位置模式 -->
    <h4 style="font-size:13px;margin-bottom:8px;">📍 图片插入位置设置</h4>
    <div>
        <label style="font-size:12px;color:#71717A;">默认插入位置</label>
        <select class="linked3-eco-select" id="img-insert-position" style="width:100%;">
            <option value="after_first_h2" <?php selected($img_settings['insert_position'], 'after_first_h2'); ?>>第一个H2后 (推荐)</option>
            <option value="before_first_h2" <?php selected($img_settings['insert_position'], 'before_first_h2'); ?>>第一个H2前</option>
            <option value="after_intro" <?php selected($img_settings['insert_position'], 'after_intro'); ?>>引言后</option>
            <option value="between_paragraphs" <?php selected($img_settings['insert_position'], 'between_paragraphs'); ?>>段落间均匀分布</option>
            <option value="featured_only" <?php selected($img_settings['insert_position'], 'featured_only'); ?>>仅作特色图片</option>
        </select>
    </div>

    <h4 style="font-size:13px;margin:16px 0 8px;">📐 信息图20布局 (宝玉布局)</h4>
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;font-size:11px;">
        <?php
        $all_layouts = [
            'title_top' => '标题顶部', 'title_bottom' => '标题底部', 'title_left' => '标题左侧', 'title_right' => '标题右侧',
            'hero_full' => '全屏Hero', 'hero_half' => '半屏Hero', 'split_left' => '左分栏', 'split_right' => '右分栏',
            'grid_2x2' => '2×2网格', 'grid_3x1' => '3×1网格', 'list_vertical' => '纵向列表', 'list_horizontal' => '横向列表',
            'carousel' => '轮播', 'stack' => '堆叠', 'mosaic' => '马赛克', 'timeline' => '时间线',
            'comparison' => '对比', 'flow' => '流程', 'pyramid' => '金字塔', 'radial' => '放射',
        ];
        foreach ($all_layouts as $key => $label):
            $checked = in_array($key, $saved_layouts) ? 'checked' : '';
        ?>
        <label style="display:flex;align-items:center;gap:4px;padding:4px;background:#f9fafb;border-radius:3px;cursor:pointer;">
            <input type="checkbox" name="img-layouts[]" value="<?php echo esc_attr($key); ?>" <?php echo $checked; ?>>
            <?php echo esc_html($label); ?>
        </label>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:6px;font-size:11px;color:#9ca3af;">💡 勾选的布局将用于信息图生成 (宝玉20布局系统)</div>
    <?php endif; ?>

    <!-- 保存按钮 (所有模式共用) -->
    <div style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e7eb;">
        <button class="linked3-eco-btn" id="img-save">💾 保存设置</button>
        <span id="img-status" style="margin-left:12px;"></span>
    </div>
</div>

<!-- v16.1.0: 引入生态共享JS库 (收敛 escHtml 重复定义) -->
<?php include __DIR__ . '/eco-shared-js.php'; ?>

<script>
(function(){
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce = '<?php echo esc_js($nonce_img); ?>';

    // v16.1.0: escHtml 优先复用 Linked3EcoShared.escapeHtml (消除三处重复定义)
    var escHtml = (window.Linked3EcoShared && window.Linked3EcoShared.escapeHtml) ? window.Linked3EcoShared.escapeHtml : function(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    };

    document.addEventListener('DOMContentLoaded', function(){
        var saveBtn = document.getElementById('img-save');
        if (!saveBtn) return;

        saveBtn.addEventListener('click', function(){
            var settings = {};

            // AI模式字段
            var provider = document.getElementById('img-provider');
            if (provider) settings.provider = provider.value;
            var resolution = document.getElementById('img-resolution');
            if (resolution) settings.resolution = resolution.value;

            // 图库模式字段
            var stockProvider = document.getElementById('img-stock-provider');
            if (stockProvider) settings.stock_provider = stockProvider.value;
            var stockKey = document.getElementById('img-stock-key');
            if (stockKey) settings.stock_api_key = stockKey.value;

            // 图片站模式字段
            var siteUrl = document.getElementById('img-site-url');
            if (siteUrl) settings.image_site_url = siteUrl.value;
            var siteCount = document.getElementById('img-site-count');
            if (siteCount) settings.image_site_count = parseInt(siteCount.value) || 3;

            // 插入位置字段
            var insertPos = document.getElementById('img-insert-position');
            if (insertPos) settings.insert_position = insertPos.value;

            // 布局checkbox
            var layouts = [];
            document.querySelectorAll('input[name="img-layouts[]"]:checked').forEach(function(cb){
                layouts.push(cb.value);
            });
            settings.layouts = layouts;

            saveBtn.disabled = true;
            saveBtn.textContent = '保存中...';

            var fd = new FormData();
            fd.append('action', 'linked3_eco_image_save');
            fd.append('nonce', nonce);
            fd.append('settings', JSON.stringify(settings));

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(data){
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 保存设置';
                    var status = document.getElementById('img-status');
                    if (data.success) {
                        status.innerHTML = '<span style="color:#10B981;font-size:12px;">✅ 已保存</span>';
                    } else {
                        status.innerHTML = '<span style="color:#EF4444;font-size:12px;">❌ ' + escHtml(data.data && data.data.message ? data.data.message : '保存失败') + '</span>';
                    }
                    setTimeout(function(){ status.innerHTML = ''; }, 4000);
                })
                .catch(function(e){
                    saveBtn.disabled = false;
                    saveBtn.textContent = '💾 保存设置';
                    document.getElementById('img-status').innerHTML =
                        '<span style="color:#EF4444;font-size:12px;">❌ 错误: ' + escHtml(e.message) + '</span>';
                });
        });
    });
})();
</script>
