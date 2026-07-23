<?php
/**
 * Content Writer admin page — template editor + generate UI.
 *
 * @package Linked3
 * @subpackage Admin\Views\ContentWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $templates */
$templates = $templates ?? [];
$nonce = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
?>
<div class="wrap" id="linked3-content-writer">
    <h1><?php echo esc_html__('Linked3 内容写作', 'linked3'); ?></h1>

    <div class="linked3-cw-grid">
        <div class="linked3-cw-form">
            <h2><?php echo esc_html__('生成文章', 'linked3'); ?></h2>
            <p>
                <label><?php echo esc_html__('关键词', 'linked3'); ?>
                    <input type="text" id="linked3-cw-keyword" class="regular-text" />
                </label>
            </p>
            <p>
                <label><?php echo esc_html__('标题(可选)', 'linked3'); ?>
                    <input type="text" id="linked3-cw-title" class="regular-text" />
                </label>
            </p>
            <p>
                <label><?php echo esc_html__('模板', 'linked3'); ?>
                    <select id="linked3-cw-template">
                        <?php foreach ($templates as $tpl) : ?>
                            <option value="<?php echo esc_attr($tpl['id']); ?>" <?php echo $tpl['is_starter'] ? 'data-starter="1"' : ''; ?>>
                                <?php echo esc_html($tpl['template_name'] . ' (' . $tpl['template_type'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" id="linked3-cw-images" checked />
                    <?php echo esc_html__('插入图片', 'linked3'); ?>
                </label>
            </p>
            <p>
                <button type="button" class="button button-primary" id="linked3-cw-generate">
                    <?php echo esc_html__('生成', 'linked3'); ?>
                </button>
                <span class="spinner" id="linked3-cw-spinner"></span>
            </p>

            <h3><?php echo esc_html__('Quick Actions', 'linked3'); ?></h3>
            <p>
                <button type="button" class="button" id="linked3-cw-gen-title"><?php echo esc_html__('生成标题', 'linked3'); ?></button>
                <button type="button" class="button" id="linked3-cw-gen-meta"><?php echo esc_html__('Meta 描述', 'linked3'); ?></button>
                <button type="button" class="button" id="linked3-cw-gen-tags"><?php echo esc_html__('标签', 'linked3'); ?></button>
                <button type="button" class="button" id="linked3-cw-gen-excerpt"><?php echo esc_html__('摘要', 'linked3'); ?></button>
            </p>
        </div>

        <div class="linked3-cw-output">
            <h2><?php echo esc_html__('输出', 'linked3'); ?></h2>
            <div id="linked3-cw-result" class="linked3-cw-result">
                <p class="linked3-cw-placeholder"><?php echo esc_html__('生成的内容将显示在此。', 'linked3'); ?></p>
            </div>
            <p>
                <button type="button" class="button" id="linked3-cw-copy"><?php echo esc_html__('复制', 'linked3'); ?></button>
                <button type="button" class="button" id="linked3-cw-new-post"><?php echo esc_html__('创建文章', 'linked3'); ?></button>
            </p>
        </div>
    </div>

    <script>
    (function () {
        var nonce = <?php echo wp_json_encode($nonce); ?>;
        var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        function post(action, data, cb) {
            var body = new FormData();
            body.append('action', action);
            body.append('nonce', nonce);
            Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
            fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(cb)
                .catch(function (e) { console.error(e); });
        }
        document.getElementById('linked3-cw-generate').addEventListener('click', function () {
            var data = {
                keyword: document.getElementById('linked3-cw-keyword').value,
                title: document.getElementById('linked3-cw-title').value,
                template_id: document.getElementById('linked3-cw-template').value,
                inject_images: document.getElementById('linked3-cw-images').checked ? 1 : 0
            };
            document.getElementById('linked3-cw-spinner').classList.add('is-active');
            post('linked3_generate_content', data, function (res) {
                document.getElementById('linked3-cw-spinner').classList.remove('is-active');
                if (res.success) {
                    // v0.4.0: response is { articles: [...], count, total_tokens }.
                    // Render the first article; if multiple, show count.
                    var arts = res.data.articles || [];
                    var first = arts.length ? arts[0] : null;
                    var body = first ? (first.content || '') : '';
                    // Escape via textContent to neutralise any XSS payload the
                    // model might have emitted (defence-in-depth; <pre> already
                    // neutralises tags via .replace below, but textContent is
                    // the canonical WP-safe pattern).
                    var pre = document.createElement('pre');
                    pre.textContent = body;
                    var out = document.getElementById('linked3-cw-result');
                    out.innerHTML = '';
                    out.appendChild(pre);
                    if (arts.length > 1) {
                        var note = document.createElement('p');
                        note.className = 'linked3-cw-batch-note';
                        note.textContent = arts.length + ' articles generated (' + (res.data.total_tokens || 0) + ' tokens).';
                        out.appendChild(note);
                    }
                    window.__linked3_last_content = body;
                } else {
                    alert((res.data && res.data.message) || 'Error');
                }
            });
        });
        ['title','meta','tags','excerpt'].forEach(function (kind) {
            document.getElementById('linked3-cw-gen-' + kind).addEventListener('click', function () {
                post('linked3_generate_' + kind, {
                    keyword: document.getElementById('linked3-cw-keyword').value,
                    title: document.getElementById('linked3-cw-title').value
                }, function (res) {
                    if (res.success) {
                        var out = res.data[kind === 'meta' ? 'meta_description' : (kind + 's')];
                        if (Array.isArray(out)) out = out.join('\n');
                        alert(out || '(empty)');
                    } else {
                        alert((res.data && res.data.message) || 'Error');
                    }
                });
            });
        });
        document.getElementById('linked3-cw-copy').addEventListener('click', function () {
            if (window.__linked3_last_content) {
                navigator.clipboard.writeText(window.__linked3_last_content);
            }
        });
        document.getElementById('linked3-cw-new-post').addEventListener('click', function () {
            if (!window.__linked3_last_content) return;
            // v0.4.0: removed the bogus `keyword: '__save_post__'` AJAX call
            // that wasted tokens by triggering a real generation just to
            // "save" the post. Instead, open post-new.php with a fragment
            // the editor can pick up; a future REST endpoint will write the
            // draft properly (v0.5.x).
            window.open('<?php echo esc_url_raw(admin_url("post-new.php")); ?>#linked3-prefill=' + encodeURIComponent(window.__linked3_last_content).slice(0, 2000));
        });
    })();
    </script>
    <style>
    .linked3-cw-grid { display:grid; grid-template-columns: 1fr 2fr; gap:20px; }
    @media (max-width: 960px) { .linked3-cw-grid { grid-template-columns: 1fr; } }
    .linked3-cw-result { background:#fff; border:1px solid #ddd; padding:12px; min-height:300px; max-height:70vh; overflow:auto; }
    .linked3-cw-result pre { white-space: pre-wrap; font-family: inherit; }
    .linked3-cw-placeholder { color:#999; }
    </style>
</div>
