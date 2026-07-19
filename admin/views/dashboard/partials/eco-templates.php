<?php
/**
 * 本地模版子面板 v17.0 — 从云模版拉取母版, 隔离修改, 场景化使用
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的模版管理界面规范
 *   - 保留全部功能: Fork母版/本地模版列表/feicai4.0结构化10字段编辑器
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_tpl = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// 加载本地Fork模版 (从云模版拉取的母版副本, 可修改)
$local_templates = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_templates', []);

// 加载云模版母版库 (用于Fork)
$cloud_masters = [];
if (class_exists('Linked3_Cloud_Template_Factory')) {
    $cloud_factory = new \Linked3_Cloud_Template_Factory();
    $cloud_categories = $cloud_factory->get_categories();
    foreach ($cloud_categories as $cat) {
        try {
            $tpl = $cloud_factory->load_template_by_category($cat);
            $cloud_masters[$cat] = $tpl;
        } catch (\Throwable $e) {}
    }
}
// 也加载自定义母版
$custom_masters = (array) get_option(LINKED3_OPTION_PREFIX . 'cloud_master_templates', []);

$cloud_master_url = admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud');
?>

<div class="linked3-eco-card">
    <h3>本地模版 — 从云模版拉取母版, 隔离修改</h3>
    <p style="color:#71717A;font-size:13px;margin-bottom:12px;">
        本地模版是云模版母版的<strong>场景化副本</strong>。从<a href="<?php echo esc_url($cloud_master_url); ?>">☁ 云模版总控</a>Fork母版后, 可自由修改, 不影响母版。模版含: Profile/Role/Scene/Background/Goals/Skills/Style/Limit/Step/Output
    </p>

    <!-- v17.2.0: 从云模版Fork母版 -->
    <div style="background:#FAFAFA;border:1px solid #0F172A;border-radius:4px;padding:10px;margin-bottom:12px;">
        <strong style="font-size:13px;color:#0F172A;">☁ 从云模版拉取母版</strong>
        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;align-items:center;">
            <select class="linked3-eco-select" id="tpl-fork-source" style="flex:1;min-width:200px;">
                <option value="">— 选择云模版母版 —</option>
                <?php foreach ($cloud_masters as $cat => $tpl): ?>
                    <option value="builtin:<?php echo esc_attr($cat); ?>">☁ <?php echo esc_html($tpl['name'] ?? $cat); ?> (内置母版)</option>
                <?php endforeach; ?>
                <?php foreach ($custom_masters as $mid => $tpl): ?>
                    <option value="custom:<?php echo esc_attr($mid); ?>">☁ <?php echo esc_html($tpl['name'] ?? $mid); ?> (自定义母版)</option>
                <?php endforeach; ?>
            </select>
            <button class="linked3-eco-btn" id="tpl-fork-btn">📥 Fork到本地</button>
            <a href="<?php echo esc_url($cloud_master_url); ?>" class="linked3-eco-btn linked3-eco-btn-secondary" style="text-decoration:none;display:inline-block;line-height:28px;">管理母版 →</a>
        </div>
        <div style="font-size:11px;color:#71717A;margin-top:6px;">💡 Fork后, 本地副本可自由修改, 母版保持不变 (场景隔离)</div>
    </div>

    <!-- 本地模版列表 -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
        <select class="linked3-eco-select" id="tpl-list" style="flex:1;min-width:200px;">
            <option value="">— 选择本地模版 —</option>
            <?php foreach ($local_templates as $tid => $tpl): ?>
                <option value="<?php echo esc_attr($tid); ?>"><?php echo esc_html($tpl['name'] ?? '未命名'); ?> (<?php echo esc_html($tpl['type'] ?? 'content'); ?>)<?php echo !empty($tpl['forked_from']) ? ' [Fork]' : ''; ?></option>
            <?php endforeach; ?>
        </select>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="tpl-load">加载</button>
        <button class="linked3-eco-btn" id="tpl-save">保存</button>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="tpl-delete" style="color:#DC2626;">删除</button>
    </div>

    <!-- feicai4.0 10字段编辑器 -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
            <label style="font-size:12px;color:#71717A;">Profile (作者/版本)</label>
            <input type="text" class="linked3-eco-input" id="tpl-profile" placeholder="如: Linked3 v10.7">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">Role (角色定义)</label>
            <input type="text" class="linked3-eco-input" id="tpl-role" placeholder="如: 资深内容写手">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">Scene (适用场景)</label>
            <input type="text" class="linked3-eco-input" id="tpl-scene" placeholder="如: 博客文章/公众号">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">Background (背景)</label>
            <input type="text" class="linked3-eco-input" id="tpl-background" placeholder="如: 面向中文读者">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">Goals (目标, 逗号分隔)</label>
            <input type="text" class="linked3-eco-input" id="tpl-goals" placeholder="如: 信息传递,SEO友好">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">Skills (技能, 逗号分隔)</label>
            <input type="text" class="linked3-eco-input" id="tpl-skills" placeholder="如: 结构化写作,关键词布局">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">Style (风格)</label>
            <input type="text" class="linked3-eco-input" id="tpl-style" placeholder="如: 专业但易懂">
        </div>
        <div>
            <label style="font-size:12px;color:#71717A;">Limit (限制)</label>
            <input type="text" class="linked3-eco-input" id="tpl-limit" placeholder="如: 字数800-2000">
        </div>
        <div style="grid-column:span 2;">
            <label style="font-size:12px;color:#71717A;">Step (步骤, 逗号分隔)</label>
            <input type="text" class="linked3-eco-input" id="tpl-step" placeholder="如: 选题,大纲,撰写,质检">
        </div>
        <div style="grid-column:span 2;">
            <label style="font-size:12px;color:#71717A;">Output (输出格式)</label>
            <input type="text" class="linked3-eco-input" id="tpl-output" placeholder="如: Markdown, 含H1/H2/H3">
        </div>
    </div>

    <div id="tpl-status" style="margin-top:12px;"></div>
</div>

<script>
(function(){
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce = '<?php echo esc_js($nonce_tpl); ?>';
    var localTemplates = <?php echo json_encode(array_values($local_templates)); ?>;
    var localTemplateIds = <?php echo json_encode(array_keys($local_templates)); ?>;

    function escHtml(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function fillForm(tpl) {
        var cfg = tpl.config || {};
        document.getElementById('tpl-profile').value = cfg.profile || '';
        document.getElementById('tpl-role').value = cfg.role || '';
        document.getElementById('tpl-scene').value = cfg.scene || '';
        document.getElementById('tpl-background').value = cfg.background || '';
        document.getElementById('tpl-goals').value = (cfg.goals || []).join(',');
        document.getElementById('tpl-skills').value = (cfg.skills || []).join(',');
        document.getElementById('tpl-style').value = cfg.style || '';
        document.getElementById('tpl-limit').value = (cfg.limit || []).join(',');
        document.getElementById('tpl-step').value = (cfg.step || []).join(',');
        document.getElementById('tpl-output').value = cfg.output || '';
    }

    document.addEventListener('DOMContentLoaded', function(){
        // Fork母版到本地
        var forkBtn = document.getElementById('tpl-fork-btn');
        if (forkBtn) {
            forkBtn.addEventListener('click', function(){
                var sourceVal = document.getElementById('tpl-fork-source').value;
                if (!sourceVal) { alert('请选择云模版母版'); return; }

                // 解析 source: "builtin:category" 或 "custom:master_id"
                var parts = sourceVal.split(':');
                var sourceType = parts[0]; // builtin | custom
                var refId = parts[1] || ''; // category 或 master_id

                forkBtn.disabled = true;
                forkBtn.textContent = 'Fork中...';

                var fd = new FormData();
                fd.append('action', 'linked3_cloud_fork');
                fd.append('nonce', nonce);
                fd.append('source', sourceType);
                fd.append('category', refId);
                fd.append('master_id', refId);
                fd.append('fork_name', '本地_' + refId + '_' + Date.now());

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        forkBtn.disabled = false;
                        forkBtn.textContent = '📥 Fork到本地';
                        if (data.success) {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : 'Fork失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        forkBtn.disabled = false;
                        forkBtn.textContent = '📥 Fork到本地';
                        document.getElementById('tpl-status').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
            });
        }

        // 加载本地模版
        var loadBtn = document.getElementById('tpl-load');
        if (loadBtn) {
            loadBtn.addEventListener('click', function(){
                var idx = document.getElementById('tpl-list').value;
                if (idx === '') { alert('请选择本地模版'); return; }
                var tpl = localTemplates[localTemplateIds.indexOf(idx)] || {};
                fillForm(tpl);
                // v11.0.3 #3: 加载后显示完整提示词内容 (不只是模版名)
                var cfg = tpl.config || tpl;
                var promptPreview = '<div class="notice notice-info inline"><p>已加载本地模版: ' + escHtml(tpl.name || '未命名') + (tpl.type ? ' (' + escHtml(tpl.type) + ')' : '') + (tpl.forked_from ? ' [Fork自: ' + escHtml(tpl.forked_from) + ']' : '') + '</p></div>';
                promptPreview += '<div style="margin-top:12px;padding:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">';
                promptPreview += '<h4 style="margin:0 0 8px 0;font-size:13px;color:#3F3F46;">📋 完整提示词内容 (可直接复制使用)</h4>';
                promptPreview += '<pre style="white-space:pre-wrap;font-size:12px;line-height:1.7;color:#1f2937;background:#fff;padding:10px;border-radius:4px;border:1px solid #e5e7eb;max-height:400px;overflow:auto;">';
                var promptText = '';
                promptText += '【Profile】' + (cfg.profile || '-') + '\n';
                promptText += '【Role】' + (cfg.role || '-') + '\n';
                promptText += '【Scene】' + (cfg.scene || '-') + '\n';
                promptText += '【Background】' + (cfg.background || '-') + '\n';
                promptText += '【Goals】' + (Array.isArray(cfg.goals) ? cfg.goals.join('、') : (cfg.goals || '-')) + '\n';
                promptText += '【Skills】' + (Array.isArray(cfg.skills) ? cfg.skills.join('、') : (cfg.skills || '-')) + '\n';
                promptText += '【Style】' + (cfg.style || '-') + '\n';
                promptText += '【Limit】' + (Array.isArray(cfg.limit) ? cfg.limit.join('、') : (cfg.limit || '-')) + '\n';
                promptText += '【Step】' + (Array.isArray(cfg.step) ? cfg.step.join(' → ') : (cfg.step || '-')) + '\n';
                promptText += '【Output】' + (cfg.output || '-');
                promptPreview += escHtml(promptText);
                promptPreview += '</pre>';
                promptPreview += '<button class="button button-small" onclick="var t=this.previousElementSibling;var r=document.createRange();r.selectNode(t);window.getSelection().removeAllRanges();window.getSelection().addRange(r);document.execCommand(\'copy\');this.textContent=\'✅ 已复制\';setTimeout(function(){},2000);" style="margin-top:6px;">📋 复制提示词</button>';
                promptPreview += '</div>';
                document.getElementById('tpl-status').innerHTML = promptPreview;
            });
        }

        // 保存本地模版
        var saveBtn = document.getElementById('tpl-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function(){
                var tplName = prompt('请输入模版名称:', '本地模版_' + new Date().getTime());
                if (!tplName) return;

                var tplData = {
                    name: tplName,
                    type: 'content',
                    config: {
                        profile: document.getElementById('tpl-profile').value,
                        role: document.getElementById('tpl-role').value,
                        scene: document.getElementById('tpl-scene').value,
                        background: document.getElementById('tpl-background').value,
                        goals: document.getElementById('tpl-goals').value.split(',').filter(function(s){return s.trim();}),
                        skills: document.getElementById('tpl-skills').value.split(',').filter(function(s){return s.trim();}),
                        style: document.getElementById('tpl-style').value,
                        limit: document.getElementById('tpl-limit').value.split(',').filter(function(s){return s.trim();}),
                        step: document.getElementById('tpl-step').value.split(',').filter(function(s){return s.trim();}),
                        output: document.getElementById('tpl-output').value
                    }
                };

                saveBtn.disabled = true;
                saveBtn.textContent = '保存中...';

                var fd = new FormData();
                fd.append('action', 'linked3_eco_template_save');
                fd.append('nonce', nonce);
                fd.append('template', JSON.stringify(tplData));

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(data){
                        saveBtn.disabled = false;
                        saveBtn.textContent = '保存';
                        if (data.success) {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-success inline"><p>✅ 本地模版已保存: ' + escHtml(tplName) + '</p></div>';
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '保存失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        saveBtn.disabled = false;
                        saveBtn.textContent = '保存';
                        document.getElementById('tpl-status').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
            });
        }

        // 删除本地模版
        var delBtn = document.getElementById('tpl-delete');
        if (delBtn) {
            delBtn.addEventListener('click', function(){
                var idx = document.getElementById('tpl-list').value;
                if (idx === '') { alert('请选择要删除的本地模版'); return; }
                if (!confirm('确认删除此本地模版? (不影响云模版母版)')) return;

                delBtn.disabled = true;
                delBtn.textContent = '删除中...';

                var fd = new FormData();
                fd.append('action', 'linked3_cloud_fork_delete');
                fd.append('nonce', nonce);
                fd.append('fork_id', idx);

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        delBtn.disabled = false;
                        delBtn.textContent = '删除';
                        if (data.success) {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-success inline"><p>✅ ' + escHtml(data.data.message) + '</p></div>';
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            document.getElementById('tpl-status').innerHTML =
                                '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '删除失败') + '</p></div>';
                        }
                    })
                    .catch(function(e){
                        delBtn.disabled = false;
                        delBtn.textContent = '删除';
                        document.getElementById('tpl-status').innerHTML =
                            '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                    });
            });
        }
    });
})();
</script>
