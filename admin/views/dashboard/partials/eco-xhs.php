<?php
/**
 * 小红书图文生成器 — 管理界面 v19.2.
 *
 * 吸收独立小红书生成器的 UI 精华，适配 Linked3 仪表盘风格。
 *
 * @package Linked3
 */
if (!defined('ABSPATH')) exit;

$nonce_xhs = wp_create_nonce('linked3_xhs');
$ajax_url = admin_url('admin-ajax.php');

// 获取可用风格
$styles = [
    'lifestyle'   => '生活分享',
    'tutorial'    => '教程干货',
    'food'        => '美食探店',
    'travel'      => '旅行攻略',
    'beauty'      => '美妆穿搭',
    'tech'        => '科技数码',
    'business'    => '商业创业',
    'emotion'     => '情感故事',
];
?>
<div class="linked3-eco-card">
    <h3>📕 小红书图文生成器</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:16px;">
        AI 驱动的小红书爆款图文笔记生成。自动生成标题、正文、分页内容、配图提示词，支持多风格切换和 V15 品牌上下文。
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label class="lk3-form-label">📝 主题</label>
            <input type="text" id="xhs-topic" class="linked3-eco-input" style="width:100%;font-size:14px;" placeholder="例如：如何在家做出完美的拿铁咖啡">
        </div>
        <div>
            <label class="lk3-form-label">🔑 关键词（可选）</label>
            <input type="text" id="xhs-keyword" class="linked3-eco-input" style="width:100%;font-size:14px;" placeholder="例如：咖啡、拿铁、居家">
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label class="lk3-form-label">🎨 风格</label>
            <select id="xhs-style" class="linked3-eco-input" style="width:100%;font-size:14px;">
                <?php foreach ($styles as $id => $label): ?>
                    <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="lk3-form-label">📄 页数</label>
            <select id="xhs-page-count" class="linked3-eco-input" style="width:100%;font-size:14px;">
                <option value="3">3 页</option>
                <option value="5" selected>5 页</option>
                <option value="6">6 页</option>
                <option value="8">8 页</option>
            </select>
        </div>
        <div>
            <label class="lk3-form-label">🤖 模型</label>
            <select id="xhs-model" class="linked3-eco-input" style="width:100%;font-size:14px;">
                <option value="">默认模型</option>
                <option value="deepseek-chat">DeepSeek Chat</option>
                <option value="gpt-4o-mini">GPT-4o Mini</option>
                <option value="gpt-4o">GPT-4o</option>
            </select>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <label class="lk3-form-label">✨ 自定义风格提示词（可选）</label>
        <textarea id="xhs-custom-style" class="linked3-eco-input" rows="2" style="width:100%;font-size:13px;" placeholder="补充风格要求，例如：使用日系清新风格，色调偏暖，文字简洁有力"></textarea>
    </div>

    <button id="xhs-generate-btn" class="button button-primary" style="background:#ff2e4d;border-color:#ff2e4d;margin-bottom:16px;">
        📕 生成小红书图文
    </button>

    <div id="xhs-result" style="display:none;">
        <div id="xhs-result-title" style="font-size:18px;font-weight:600;margin-bottom:8px;"></div>
        <div id="xhs-result-content" style="font-size:14px;color:#555;margin-bottom:16px;white-space:pre-wrap;"></div>
        <div id="xhs-result-tags" style="margin-bottom:16px;"></div>
        <div id="xhs-result-pages" style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;"></div>
    </div>

    <div id="xhs-loading" style="display:none;text-align:center;padding:40px;">
        <span class="spinner is-active"></span>
        <p style="margin-top:10px;color:#666;">AI 正在创作小红书图文...</p>
    </div>

    <div id="xhs-error" style="display:none;" class="notice notice-error inline">
        <p id="xhs-error-msg"></p>
    </div>
</div>

<script>
(function() {
    var btn = document.getElementById('xhs-generate-btn');
    var loading = document.getElementById('xhs-loading');
    var result = document.getElementById('xhs-result');
    var errorDiv = document.getElementById('xhs-error');
    var errorMsg = document.getElementById('xhs-error-msg');

    btn.addEventListener('click', function() {
        var topic = document.getElementById('xhs-topic').value.trim();
        if (!topic) { alert('请输入主题'); return; }

        btn.disabled = true;
        btn.textContent = '⏳ 生成中...';
        loading.style.display = 'block';
        result.style.display = 'none';
        errorDiv.style.display = 'none';

        fetch('<?php echo esc_js($ajax_url); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'linked3_xhs_generate',
                nonce: '<?php echo esc_js($nonce_xhs); ?>',
                topic: topic,
                keyword: document.getElementById('xhs-keyword').value,
                style: document.getElementById('xhs-style').value,
                page_count: document.getElementById('xhs-page-count').value,
                model: document.getElementById('xhs-model').value,
                custom_style: document.getElementById('xhs-custom-style').value
            })
        })
        .then(function(r) {
            // v19.2.1 前端加固：先读 text，再尝试 JSON 解析。
            // 这样当 WP fatal handler 输出 "<p>There has been a critical error...</p>"
            // 时，能给用户一条可读的错误，而不是 "Unexpected token '<'"。
            return r.text().then(function(text) {
                var trimmed = (text || '').trim();
                if (!trimmed) {
                    throw new Error('服务器返回空响应，请检查 PHP 错误日志。');
                }
                // 不是 JSON 开头（{ 或 [）→ 一定是 HTML 错误页
                if (trimmed[0] !== '{' && trimmed[0] !== '[') {
                    // 提取 <p>...</p> 或第一行作为错误信息
                    var m = trimmed.match(/<p>([^<]+)<\/p>/i);
                    var msg = m ? m[1] : trimmed.split('\n')[0].slice(0, 120);
                    throw new Error('服务器错误: ' + msg);
                }
                try {
                    return JSON.parse(trimmed);
                } catch (e) {
                    throw new Error('响应解析失败: ' + e.message);
                }
            });
        })
        .then(function(json) {
            if (!json || !json.success) {
                throw new Error((json && json.data && json.data.message) || '生成失败');
            }
            var data = json.data || {};
            document.getElementById('xhs-result-title').textContent = data.title || '';
            document.getElementById('xhs-result-content').textContent = data.main_content || '';

            // Tags
            var tagsHtml = '';
            (data.tags || []).forEach(function(tag) {
                tagsHtml += '<span style="display:inline-block;background:#fff0f0;color:#ff2e4d;padding:2px 10px;border-radius:12px;font-size:12px;margin-right:6px;">' + tag + '</span>';
            });
            document.getElementById('xhs-result-tags').innerHTML = tagsHtml;

            // Pages
            var pagesHtml = '';
            (data.pages || []).forEach(function(page, idx) {
                var isCover = page.is_cover;
                pagesHtml += '<div style="border:1px solid #ececec;border-radius:12px;overflow:hidden;background:#fff;">';
                pagesHtml += '<div style="padding:12px;">';
                pagesHtml += '<div style="font-size:14px;font-weight:600;margin-bottom:6px;">' + (isCover ? '🔥 ' : '📄 ') + (page.title || ('第' + (idx+1) + '页')) + '</div>';
                pagesHtml += '<div style="font-size:13px;color:#555;margin-bottom:8px;white-space:pre-wrap;">' + (page.content || '') + '</div>';
                pagesHtml += '<div style="background:#f5f5f7;padding:8px;border-radius:8px;font-size:12px;color:#888;">🎨 ' + (page.image_prompt || '(无配图提示词)') + '</div>';
                pagesHtml += '</div></div>';
            });
            document.getElementById('xhs-result-pages').innerHTML = pagesHtml;

            result.style.display = 'block';
        })
        .catch(function(err) {
            errorMsg.textContent = err.message || '生成失败';
            errorDiv.style.display = 'block';
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = '📕 生成小红书图文';
            loading.style.display = 'none';
        });
    });
})();
</script>
