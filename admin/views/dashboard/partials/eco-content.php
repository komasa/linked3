<?php
/**
 * 内容写作子面板 v17.0 — 全功能链整合 (快速/长文/CSV批量, feicai4.0 5阶段)
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的写作界面规范
 *   - 修复BUG: 写作配置桥接器布局错乱/模版来源/配图画风显示问题
 *   - 新增: HTML输出格式选择 (MD/HTML/纯文本)
 *   - 保留 feicai4.0 文案5阶段法进度可视化
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
$nonce_cw = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');
$cw_mode = isset($_GET['cw_mode']) ? sanitize_key($_GET['cw_mode']) : 'quick';
?>

<div class="linked3-eco-card">
    <h3>📝 内容写作 — feicai4.0文案5阶段法</h3>
    <p style="color:#71717A;font-size:12px;margin-bottom:16px;">5阶段: 上下文收集 → 简报锁定 → 草稿生成 → 自检 → 交付 · 支持快速/长文/CSV批量三种模式</p>

    <!-- 写作模式切换 (v17.0: 极简下划线式) -->
    <div class="linked3-eco-subtabs" style="margin-bottom:16px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=quick')); ?>"
           class="linked3-eco-subtab <?php echo $cw_mode === 'quick' ? 'active' : ''; ?>">⚡ 快速写作</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=longform')); ?>"
           class="linked3-eco-subtab <?php echo $cw_mode === 'longform' ? 'active' : ''; ?>">📚 长文写作</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=csv')); ?>"
           class="linked3-eco-subtab <?php echo $cw_mode === 'csv' ? 'active' : ''; ?>">📊 CSV批量</a>
    </div>

    <?php if ($cw_mode === 'quick'): ?>
    <!-- 快速写作模式 -->

    <!-- v17.0: 写作配置桥接器 (统一本地模版/图片设置/配图画风/输出格式) -->
    <?php include __DIR__ . '/eco-config-bridge.php'; ?>

    <!-- v17.2: 思想DNA选择器 (全写作入口共享组件) -->
    <?php include __DIR__ . '/eco-style-dna-picker.php'; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input type="text" class="linked3-eco-input" id="cw-topic" placeholder="主题" style="flex:1;min-width:200px;">
        <input type="text" class="linked3-eco-input" id="cw-keywords" placeholder="关键词(逗号分隔)" style="flex:1;min-width:200px;">
        <?php
        // v11.5.0: 行业选择器 (P2) — 消费G3的50场景母版
        $p2_industries = [];
        if (class_exists('CloudTemplateFactory')) {
            try { $p2_industries = (new \CloudTemplateFactory())->get_industries(); } catch (\Throwable $e) {}
        }
        if (!empty($p2_industries)) :
        ?>
        <select class="linked3-eco-select" id="cw-industry" title="选择行业变体, AI将按行业调性生成">
            <?php foreach ($p2_industries as $ind_slug => $ind_meta) : ?>
            <option value="<?php echo esc_attr($ind_slug); ?>"><?php echo esc_html($ind_meta['icon'] . ' ' . $ind_meta['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="number" class="linked3-eco-input" id="cw-wordcount" value="800" min="200" max="3000" style="width:80px;">
        <button class="linked3-eco-btn" id="cw-generate">生成内容</button>
    </div>

    <!-- 5阶段进度 -->
    <div id="cw-phases" style="display:none;margin-bottom:12px;">
        <div style="display:flex;gap:4px;">
            <span class="linked3-eco-phase" data-phase="0">① 上下文</span>
            <span class="linked3-eco-phase" data-phase="1">② 简报</span>
            <span class="linked3-eco-phase" data-phase="2">③ 草稿</span>
            <span class="linked3-eco-phase" data-phase="3">④ 自检</span>
            <span class="linked3-eco-phase" data-phase="4">⑤ 交付</span>
        </div>
    </div>

    <div id="cw-result"></div>

    <?php elseif ($cw_mode === 'longform'): ?>
    <!-- 长文写作模式 -->

    <!-- v1.0: 写作配置桥接器 (统一本地模版/图片设置/配图画风) -->
    <?php include __DIR__ . '/eco-config-bridge.php'; ?>

    <!-- v17.2: 思想DNA选择器 (全写作入口共享组件) -->
    <?php include __DIR__ . '/eco-style-dna-picker.php'; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input type="text" class="linked3-eco-input" id="cw-lf-topic" placeholder="长文主题" style="flex:1;min-width:200px;">
        <input type="text" class="linked3-eco-input" id="cw-lf-keywords" placeholder="关键词(逗号分隔)" style="flex:1;min-width:200px;">
        <input type="number" class="linked3-eco-input" id="cw-lf-sections" value="5" min="2" max="20" style="width:80px;" title="段落数">
        <span style="font-size:12px;color:#71717A;align-self:center;">段</span>
        <input type="number" class="linked3-eco-input" id="cw-lf-words" value="3000" min="1000" max="20000" style="width:90px;" title="总字数">
        <span style="font-size:12px;color:#71717A;align-self:center;">字</span>
        <button class="linked3-eco-btn" id="cw-lf-outline">生成大纲</button>
        <button class="linked3-eco-btn" id="cw-lf-generate" disabled>逐段生成</button>
        <!-- v16.0.25: 长文配图 + 保存草稿 (闭环) -->
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="cw-lf-gen-images" disabled title="为长文每段生成配图">🎨 配图</button>
        <button class="linked3-eco-btn linked3-eco-btn-secondary" id="cw-lf-save-draft" disabled>💾 保存草稿</button>
    </div>

    <div id="cw-lf-outline-result" style="margin-bottom:12px;"></div>
    <div id="cw-lf-sections-progress" style="display:none;margin-bottom:12px;"></div>
    <div id="cw-lf-result"></div>

    <?php else: ?>
    <!-- CSV批量模式 -->

    <!-- v1.0: 写作配置桥接器 (统一本地模版/图片设置/配图画风) -->
    <?php include __DIR__ . '/eco-config-bridge.php'; ?>

    <div style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:4px;padding:10px;margin-bottom:12px;font-size:12px;color:#92400E;">
        💡 CSV批量模式: 上传含主题列表的CSV文件, 批量生成文章。支持以下格式:
    </div>

    <!-- v11.0.5 #6: CSV格式样稿说明 -->
    <details style="margin-bottom:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 12px;">
        <summary style="cursor:pointer;font-weight:600;color:#3F3F46;font-size:13px;">📋 CSV格式样稿 (点击展开查看/下载)</summary>
        <div style="margin-top:10px;font-size:12px;">
            <p style="color:#71717A;margin:0 0 8px 0;">支持3种格式, 任选其一:</p>

            <p style="font-weight:600;color:#3F3F46;margin:8px 0 4px 0;">格式1: 单列主题 (最简单)</p>
            <pre style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e5e7eb;font-size:11px;">AI写作工具推荐
ChatGPT使用技巧
大模型微调教程
AI绘画提示词工程</pre>

            <p style="font-weight:600;color:#3F3F46;margin:12px 0 4px 0;">格式2: 主题+关键词 (推荐)</p>
            <pre style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e5e7eb;font-size:11px;">title,keywords
AI写作工具推荐,AI写作|内容生成|效率工具
ChatGPT使用技巧,ChatGPT|提示词|对话技巧
大模型微调教程,大模型|微调|LLM|训练</pre>

            <p style="font-weight:600;color:#3F3F46;margin:12px 0 4px 0;">格式3: 完整字段 (主题+关键词+字数)</p>
            <pre style="background:#fff;padding:8px;border-radius:4px;border:1px solid #e5e7eb;font-size:11px;">title,keywords,word_count
AI写作工具推荐,AI写作|内容生成,800
ChatGPT使用技巧,ChatGPT|提示词,1200
大模型微调教程,大模型|微调,2000</pre>

            <p style="color:#71717A;margin:12px 0 4px 0;">📝 也可使用纯TXT文件, 每行一个主题。</p>
            <button class="button button-small" id="cw-csv-download-sample" style="margin-top:4px;">⬇ 下载样稿CSV</button>
        </div>
    </details>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <input type="file" id="cw-csv-file" accept=".csv,.txt" style="flex:1;min-width:200px;">
        <select class="linked3-eco-select" id="cw-csv-status">
            <option value="draft">草稿</option>
            <option value="publish">直接发布</option>
        </select>
        <button class="linked3-eco-btn" id="cw-csv-upload">上传并预览</button>
        <button class="linked3-eco-btn" id="cw-csv-generate" disabled>批量生成</button>
    </div>
    <div id="cw-csv-preview" style="margin-bottom:12px;"></div>
    <div id="cw-csv-result"></div>
    <?php endif; ?>
</div>

<!-- v16.1.0: 引入生态共享JS库 (收敛 escHtml/generateImages/saveDraft 重复定义) -->
<?php include __DIR__ . '/eco-shared-js.php'; ?>

<script>
(function(){
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var nonce = '<?php echo esc_js($nonce_cw); ?>';
    var cwMode = '<?php echo esc_js($cw_mode); ?>';

    // v16.1.0: escHtml 优先复用 Linked3EcoShared.escapeHtml (消除三处重复定义)
    var escHtml = (window.Linked3EcoShared && window.Linked3EcoShared.escapeHtml) ? window.Linked3EcoShared.escapeHtml : function(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    };

    document.addEventListener('DOMContentLoaded', function(){
        if (cwMode === 'quick') {
            initQuickMode();
        } else if (cwMode === 'longform') {
            initLongformMode();
        } else {
            initCsvMode();
        }
    });

    // 快速写作模式
    function initQuickMode() {
        var genBtn = document.getElementById('cw-generate');
        if (!genBtn) return;

        genBtn.addEventListener('click', function(){
            var topic = document.getElementById('cw-topic').value.trim();
            var keywords = document.getElementById('cw-keywords').value.trim();
            // v17.2: 从共享组件读取风格配置
            var styleCfg = window.lk3_get_style_config ? window.lk3_get_style_config() : { style_dna: '', tone: 'professional', humanize_modules: [] };
            var tone = styleCfg.tone;
            var wordCount = document.getElementById('cw-wordcount').value;
            // v11.5.0: 读取行业选择器 (P2)
            var industryEl = document.getElementById('cw-industry');
            var industry = industryEl ? industryEl.value : 'general';

            if (!topic) { alert('请输入主题'); return; }

            // v10.7.0 Bug#6: 重复点击进度条不重置
            document.querySelectorAll('.linked3-eco-phase').forEach(function(el){ el.classList.remove('active'); });

            var phasesDiv = document.getElementById('cw-phases');
            phasesDiv.style.display = 'block';
            document.getElementById('cw-result').innerHTML = '<div style="color:#71717A;font-size:12px;">生成中...</div>';

            genBtn.disabled = true;
            genBtn.textContent = '生成中...';

            var phaseIdx = 0;
            var timer = setInterval(function(){
                var phases = document.querySelectorAll('.linked3-eco-phase');
                if (phaseIdx < 4) {
                    phases[phaseIdx].classList.add('active');
                    phaseIdx++;
                }
            }, 800);

            var fd = new FormData();
            fd.append('action', 'linked3_eco_content');
            fd.append('nonce', nonce);
            fd.append('topic', topic);
            fd.append('keywords', keywords);
            fd.append('tone', tone);
            fd.append('word_count', wordCount);
            fd.append('industry', industry); // v11.5.0: 行业变体 (P2)
            // v17.2: 传递思想DNA和人类化模块
            fd.append('style_dna', styleCfg.style_dna);
            fd.append('humanize_modules', JSON.stringify(styleCfg.humanize_modules));

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(data){
                    clearInterval(timer);
                    genBtn.disabled = false;
                    genBtn.textContent = '生成内容';
                    if (data.success) {
                        document.querySelectorAll('.linked3-eco-phase').forEach(function(el){ el.classList.add('active'); });

                        var content = data.data.content || '';
                        var displayCount = data.data.word_count || content.replace(/<[^>]+>/g,'').length;
                        // v11.4.2: 生成完成后增加"立即分发"跨Hub推送按钮 (方案⑤)
                        var distUrl = '<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish")); ?>';
                        document.getElementById('cw-result').innerHTML =
                            '<div style="background:#f9fafb;padding:12px;border-radius:4px;max-height:400px;overflow-y:auto;">' +
                            '<div style="font-size:12px;color:#71717A;margin-bottom:8px;">字数: ' + displayCount + ' | 自检: ' + (data.data.checked ? '✅' : '⚠️') + '</div>' +
                            '<div style="white-space:pre-wrap;font-size:13px;line-height:1.6;">' + escHtml(content) + '</div>' +
                            '</div>' +
                            '<div style="margin-top:12px;padding:10px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;">' +
                            '<strong style="font-size:13px;color:#0F172A;">✓ 内容已生成 — 下一步:</strong>' +
                            '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">' +
                            '<a href="' + distUrl + '" class="button button-primary">📤 立即分发到多平台 →</a>' +
                            '<button type="button" class="button" onclick="lk3CopyContent()">📋 复制内容</button>' +
                            '</div>' +
                            '<p style="font-size:11px;color:#71717A;margin:6px 0 0 0;">💡 分发中心支持发布到WordPress/微信公众号/百家号 + 15+社交平台同步</p>' +
                            '</div>';
                    } else {
                        document.getElementById('cw-result').innerHTML =
                            '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '生成失败') + '</p></div>';
                    }
                })
                .catch(function(e){
                    clearInterval(timer);
                    genBtn.disabled = false;
                    genBtn.textContent = '生成内容';
                    document.getElementById('cw-result').innerHTML =
                        '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    }

    // 长文写作模式
    function initLongformMode() {
        var outlineBtn = document.getElementById('cw-lf-outline');
        var genBtn = document.getElementById('cw-lf-generate');
        if (!outlineBtn) return;

        var outline = [];

        outlineBtn.addEventListener('click', function(){
            var topic = document.getElementById('cw-lf-topic').value.trim();
            var keywords = document.getElementById('cw-lf-keywords').value.trim();
            var sections = document.getElementById('cw-lf-sections').value;
            var words = document.getElementById('cw-lf-words').value;

            if (!topic) { alert('请输入主题'); return; }

            outlineBtn.disabled = true;
            outlineBtn.textContent = '生成大纲中...';
            document.getElementById('cw-lf-outline-result').innerHTML = '<div style="color:#71717A;font-size:12px;">生成大纲中...</div>';

            var fd = new FormData();
            fd.append('action', 'linked3_eco_longform_outline');
            fd.append('nonce', nonce);
            fd.append('topic', topic);
            fd.append('keywords', keywords);
            fd.append('section_count', sections);
            fd.append('word_count', words);
            // v17.2: 传递思想DNA
            var lfStyleCfg = window.lk3_get_style_config ? window.lk3_get_style_config() : { style_dna: '', tone: 'professional' };
            fd.append('style_dna', lfStyleCfg.style_dna);
            fd.append('tone', lfStyleCfg.tone);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                .then(function(data){
                    outlineBtn.disabled = false;
                    outlineBtn.textContent = '生成大纲';
                    if (data.success && data.data.outline) {
                        outline = data.data.outline;
                        // v11.0.4 #5: 修复[object Object] — outline是对象数组, 需取.title
                        // 同时支持可编辑大纲 (每段标题可修改)
                        var html = '<div style="background:#f9fafb;padding:10px;border-radius:4px;">';
                        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
                        html += '<strong>大纲 (' + outline.length + '段) — 可直接编辑标题</strong>';
                        html += '<button class="button button-small" id="cw-lf-outline-add" style="font-size:11px;">+ 添加段落</button>';
                        html += '</div>';
                        html += '<ol id="cw-lf-outline-list" style="margin:6px 0 0 20px;">';
                        outline.forEach(function(s, i){
                            var title = (typeof s === 'string') ? s : (s.title || s.name || '');
                            html += '<li style="font-size:13px;margin-bottom:4px;display:flex;align-items:center;gap:6px;">';
                            html += '<input type="text" class="cw-lf-outline-item" value="' + escHtml(title) + '" style="flex:1;padding:4px 8px;border:1px solid #d1d5db;border-radius:3px;font-size:13px;" data-idx="' + i + '">';
                            html += '<button class="button button-small button-link-delete cw-lf-outline-del" data-idx="' + i + '" style="font-size:11px;padding:2px 8px;">✕</button>';
                            html += '</li>';
                        });
                        html += '</ol>';
                        html += '<p style="font-size:11px;color:#71717A;margin-top:6px;">💡 修改标题后点击「逐段生成」即按新标题生成。可添加/删除段落。</p>';
                        html += '</div>';
                        document.getElementById('cw-lf-outline-result').innerHTML = html;
                        genBtn.disabled = false;

                        // v11.0.4 #5: 大纲编辑事件绑定
                        document.querySelectorAll('.cw-lf-outline-item').forEach(function(input){
                            input.addEventListener('change', function(){
                                var i = parseInt(this.getAttribute('data-idx'));
                                if (outline[i]) {
                                    if (typeof outline[i] === 'string') {
                                        outline[i] = this.value;
                                    } else {
                                        outline[i].title = this.value;
                                    }
                                }
                            });
                        });
                        document.querySelectorAll('.cw-lf-outline-del').forEach(function(btn){
                            btn.addEventListener('click', function(){
                                var i = parseInt(this.getAttribute('data-idx'));
                                outline.splice(i, 1);
                                // 重新渲染
                                outlineBtn.click();
                            });
                        });
                        var addBtn = document.getElementById('cw-lf-outline-add');
                        if (addBtn) {
                            addBtn.addEventListener('click', function(){
                                outline.push({index: outline.length, title: '新段落', status: 'pending'});
                                outlineBtn.click();
                            });
                        }
                    } else {
                        document.getElementById('cw-lf-outline-result').innerHTML =
                            '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '大纲生成失败') + '</p></div>';
                    }
                })
                .catch(function(e){
                    outlineBtn.disabled = false;
                    outlineBtn.textContent = '生成大纲';
                    document.getElementById('cw-lf-outline-result').innerHTML =
                        '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });

        genBtn.addEventListener('click', function(){
            if (outline.length === 0) { alert('请先生成大纲'); return; }
            var topic = document.getElementById('cw-lf-topic').value.trim();
            var keywords = document.getElementById('cw-lf-keywords').value.trim();
            var words = document.getElementById('cw-lf-words').value;

            genBtn.disabled = true;
            genBtn.textContent = '逐段生成中...';

            var sections = [];
            var idx = 0;
            var progressDiv = document.getElementById('cw-lf-sections-progress');
            progressDiv.style.display = 'block';

            function generateNext() {
                if (idx >= outline.length) {
                    // 合并全文
                    var merged = sections.join('\n\n');
                    document.getElementById('cw-lf-result').innerHTML =
                        '<div style="background:#F4F4F5;border:1px solid #86efac;padding:8px;margin-bottom:10px;border-radius:4px;"><strong>✓ 全文已合并</strong> (' + merged.length + '字)</div>' +
                        '<pre style="white-space:pre-wrap;background:#fff;padding:12px;border:1px solid #ddd;border-radius:4px;max-height:500px;overflow:auto;">' + escHtml(merged) + '</pre>';
                    genBtn.disabled = false;
                    genBtn.textContent = '逐段生成';
                    // v16.0.25: 全文生成完成后, 启用配图按钮
                    var lfGenImgBtn2 = document.getElementById('cw-lf-gen-images');
                    if (lfGenImgBtn2) lfGenImgBtn2.disabled = false;
                    var lfSaveBtn2 = document.getElementById('cw-lf-save-draft');
                    if (lfSaveBtn2) lfSaveBtn2.disabled = false;
                    return;
                }

                var sectionTitle = outline[idx];
                // v11.0.4 #5: outline项可能是对象, 取title字段
                if (typeof sectionTitle === 'object' && sectionTitle) {
                    sectionTitle = sectionTitle.title || sectionTitle.name || '';
                }
                var fd = new FormData();
                fd.append('action', 'linked3_eco_longform_section');
                fd.append('nonce', nonce);
                fd.append('topic', topic);
                fd.append('keywords', keywords);
                fd.append('section_title', sectionTitle);
                fd.append('section_index', idx);
                fd.append('total_sections', outline.length);
                fd.append('word_count', words);
                // v17.2: 传递思想DNA
                fd.append('style_dna', lfStyleCfg.style_dna);
                fd.append('tone', lfStyleCfg.tone);

                // 更新进度
                var progHtml = '<div style="font-size:12px;color:#71717A;">生成第 ' + (idx+1) + '/' + outline.length + ' 段: ' + escHtml(sectionTitle) + '</div>';
                progHtml += '<div style="background:#e5e7eb;border-radius:4px;height:6px;margin-top:4px;"><div style="background:#0F172A;height:6px;border-radius:4px;width:' + Math.round((idx/outline.length)*100) + '%;"></div></div>';
                progressDiv.innerHTML = progHtml;

                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                    .then(function(data){
                        if (data.success && data.data.content) {
                            sections.push('## ' + sectionTitle + '\n\n' + data.data.content);
                            // 显示已生成段落
                            var h = '';
                            for (var j = 0; j <= idx; j++) {
                                if (sections[j]) {
                                    h += '<div style="border-bottom:1px dashed #ddd;padding:8px 0;"><span style="color:#080;font-size:11px;">✓ 段' + (j+1) + '</span><br>' + escHtml(sections[j]).replace(/\n/g,'<br>') + '</div>';
                                }
                            }
                            document.getElementById('cw-lf-result').innerHTML = h;
                            idx++;
                            generateNext();
                        } else {
                            throw new Error(data.data && data.data.message ? data.data.message : '段落生成失败');
                        }
                    })
                    .catch(function(e){
                        genBtn.disabled = false;
                        genBtn.textContent = '逐段生成';
                        // v16.0.25修复: Failed to fetch 错误友好提示
                        var errMsg = e.message;
                        var hint = '';
                        if (errMsg.indexOf('Failed to fetch') !== -1 || errMsg.indexOf('NetworkError') !== -1) {
                            errMsg = '网络请求失败 (Failed to fetch)';
                            hint = '<br><span style="color:#71717A;font-size:11px;">💡 可能原因: ①AI API响应超时 ②网络中断 ③服务器超时限制。建议: 检查API Key配置, 减少段落数, 或重试。</span>';
                        }
                        document.getElementById('cw-lf-result').innerHTML =
                            '<div class="notice notice-error inline"><p>❌ 错误: ' + escHtml(errMsg) + '</p>' + hint + '<p style="margin-top:8px;"><button class="button button-small" onclick="location.reload()">刷新重试</button></p></div>';
                    });
            }
            generateNext();
        });

        // v16.0.25: 长文配图功能
        var lfGenImgBtn = document.getElementById('cw-lf-gen-images');
        var lfSaveBtn = document.getElementById('cw-lf-save-draft');
        if (lfGenImgBtn) {
            lfGenImgBtn.addEventListener('click', function(){
                if (sections.length === 0) { alert('请先生成长文内容'); return; }
                lfGenImgBtn.disabled = true; lfGenImgBtn.textContent = '配图中...';
                var topic = document.getElementById('cw-lf-topic').value.trim();
                // 为每段生成1张图
                var imgPromises = sections.map(function(sec, i){
                    var title = sec.split('\n')[0].replace(/^##\s*/, '');
                    var fd = new FormData();
                    fd.append('action', 'linked3_eco_generate_images');
                    fd.append('nonce', nonce);
                    fd.append('images', JSON.stringify([{type:'content_'+(i+1), prompt: '配图: ' + topic + ' - ' + title + ', 信息图风格, 清晰专业'}]));
                    return fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json();});
                });
                Promise.all(imgPromises).then(function(results){
                    var imgUrls = [];
                    results.forEach(function(res, i){
                        if (res.success && res.data.results && res.data.results[0] && res.data.results[0].url) {
                            imgUrls.push({idx: i, url: res.data.results[0].url});
                        }
                    });
                    // 将图片插入对应段落
                    imgUrls.forEach(function(img){
                        var imgMd = '\n\n![配图' + (img.idx+1) + '](' + img.url + ')\n\n';
                        sections[img.idx] = sections[img.idx] + imgMd;
                    });
                    // 重新渲染
                    var h = '';
                    sections.forEach(function(s, j){
                        h += '<div style="border-bottom:1px dashed #ddd;padding:8px 0;"><span style="color:#080;font-size:11px;">✓ 段' + (j+1) + (imgUrls.find(function(x){return x.idx===j;}) ? ' 📷' : '') + '</span><br>' + escHtml(s).replace(/\n/g,'<br>') + '</div>';
                    });
                    h += '<div class="notice notice-success inline"><p>✅ 配图完成: ' + imgUrls.length + '/' + sections.length + ' 张</p></div>';
                    document.getElementById('cw-lf-result').innerHTML = h;
                    lfGenImgBtn.disabled = false; lfGenImgBtn.textContent = '🎨 配图';
                    lfSaveBtn.disabled = false; // 启用保存
                }).catch(function(e){
                    lfGenImgBtn.disabled = false; lfGenImgBtn.textContent = '🎨 配图';
                    alert('配图失败: ' + e.message);
                });
            });
        }
        // v16.0.25: 长文保存草稿
        if (lfSaveBtn) {
            lfSaveBtn.addEventListener('click', function(){
                if (sections.length === 0) return;
                var topic = document.getElementById('cw-lf-topic').value.trim() || '长文-' + Date.now();
                var fd = new FormData();
                fd.append('action', 'linked3_eco_save_draft');
                fd.append('nonce', nonce);
                fd.append('title', topic);
                fd.append('content', sections.join('\n\n'));
                lfSaveBtn.disabled = true; lfSaveBtn.textContent = '保存中...';
                fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(d){
                        lfSaveBtn.disabled = false; lfSaveBtn.textContent = '💾 保存草稿';
                        if (d.success) {
                            alert('✅ 已保存为草稿');
                        } else {
                            alert('❌ ' + (d.data.message || '保存失败'));
                        }
                    }).catch(function(e){
                        lfSaveBtn.disabled = false; lfSaveBtn.textContent = '💾 保存草稿';
                        alert('❌ ' + e.message);
                    });
            });
        }
    }

    // CSV批量模式
    function initCsvMode() {
        var uploadBtn = document.getElementById('cw-csv-upload');
        var genBtn = document.getElementById('cw-csv-generate');
        if (!uploadBtn) return;

        // v11.0.5 #6: 下载CSV样稿
        var dlBtn = document.getElementById('cw-csv-download-sample');
        if (dlBtn) {
            dlBtn.addEventListener('click', function(){
                var csv = 'title,keywords,word_count\nAI写作工具推荐,AI写作|内容生成|效率工具,800\nChatGPT使用技巧,ChatGPT|提示词|对话技巧,1200\n大模型微调教程,大模型|微调|LLM|训练,2000\nAI绘画提示词工程,AI绘画|提示词|Midjourney,1000';
                var blob = new Blob(['\ufeff' + csv], {type:'text/csv;charset=utf-8'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url; a.download = 'linked3_csv_sample.csv';
                document.body.appendChild(a); a.click();
                document.body.removeChild(a); URL.revokeObjectURL(url);
            });
        }

        var csvData = [];
        var csvHeaders = ['title', 'keywords', 'word_count'];

        // v11.0.9 #2: 提取renderCsvPreview为独立函数 (原代码内联在onload中, 导致tail_topics跳转调用失败)
        function renderCsvPreview() {
            if (csvData.length === 0) {
                document.getElementById('cw-csv-preview').innerHTML = '';
                genBtn.disabled = true;
                return;
            }
            var html = '<div class="notice notice-success inline"><p>✅ 共 ' + csvData.length + ' 条数据, 可点击「批量生成」</p></div>';
            html += '<table class="widefat striped" style="max-height:200px;overflow:auto;display:block;"><thead><tr>';
            csvHeaders.forEach(function(h){ html += '<th>' + escHtml(String(h || '')) + '</th>'; });
            html += '</tr></thead><tbody>';
            csvData.slice(0, 20).forEach(function(r){
                html += '<tr>';
                csvHeaders.forEach(function(h){ html += '<td>' + escHtml(String(r[h] || '')) + '</td>'; });
                html += '</tr>';
            });
            html += '</tbody></table>';
            if (csvData.length > 20) {
                html += '<p style="font-size:11px;color:#71717A;margin-top:4px;">仅显示前20条, 共' + csvData.length + '条</p>';
            }
            document.getElementById('cw-csv-preview').innerHTML = html;
            genBtn.disabled = false;
        }

        // v11.0.6 #7: 自动填充从长尾词库跳转传来的主题
        var urlParams = new URLSearchParams(window.location.search);
        var tailTopics = urlParams.get('tail_topics');
        if (tailTopics) {
            var lines = tailTopics.split('\n').filter(function(l){return l.trim();});
            csvData = lines.map(function(line, i){
                return {title: line.trim(), keywords: '', word_count: 800, status: 'pending'};
            });
            renderCsvPreview();
        }

        uploadBtn.addEventListener('click', function(){
            var fileInput = document.getElementById('cw-csv-file');
            var file = fileInput.files[0];
            if (!file) { alert('请选择CSV文件'); return; }

            uploadBtn.disabled = true;
            uploadBtn.textContent = '解析中...';

            var reader = new FileReader();
            reader.onload = function(e){
                var text = e.target.result;
                var lines = text.split(/\r?\n/).filter(function(l){return l.trim();});
                csvData = [];
                var headers = [];

                lines.forEach(function(line, i){
                    var cols = line.split(',').map(function(s){return s.trim();});
                    if (i === 0) {
                        // 第一行作为header
                        headers = cols;
                    } else {
                        var row = {};
                        headers.forEach(function(h, j){ row[h] = cols[j] || ''; });
                        csvData.push(row);
                    }
                });

                // 如果只有一列, 补充header
                if (headers.length === 1 && headers[0] === '') headers = ['title'];

                // v11.0.9 #2: 统一headers为标准格式
                csvHeaders = headers.length > 0 ? headers : ['title', 'keywords', 'word_count'];

                uploadBtn.disabled = false;
                uploadBtn.textContent = '上传并预览';

                if (csvData.length === 0) {
                    document.getElementById('cw-csv-preview').innerHTML = '<div class="notice notice-error inline"><p>CSV文件无有效数据</p></div>';
                    return;
                }

                renderCsvPreview();
            };
            reader.readAsText(file);
        });

        genBtn.addEventListener('click', function(){
            if (csvData.length === 0) { alert('请先上传CSV'); return; }
            var status = document.getElementById('cw-csv-status').value;

            genBtn.disabled = true;
            genBtn.textContent = '批量生成中...';
            document.getElementById('cw-csv-result').innerHTML = '<div style="color:#71717A;font-size:12px;">批量生成中, 共 ' + csvData.length + ' 篇...</div>';

            var fd = new FormData();
            fd.append('action', 'linked3_eco_csv_batch');
            fd.append('nonce', nonce);
            // v11.3.3: 对齐后端 — 后端期望 topics(换行分隔文本), 非JSON
            var topicsText = csvData.map(function(r){ return r[csvHeaders[0]] || ''; }).filter(Boolean).join('\n');
            fd.append('topics', topicsText);
            // v18复审修复E3 [公理α: H↓ 消除"选了发布却没发布"不确定性]
            // 传递目标状态(draft/publish)给后端, 后端据此 wp_insert_post
            fd.append('target_status', status);
            // 传递关键词列(若有), 供后端写入文章标签
            if (csvHeaders.length > 1) {
                var keywordsText = csvData.map(function(r){ return r[csvHeaders[1]] || ''; }).filter(Boolean).join('\n');
                fd.append('keywords_list', keywordsText);
            }

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
                .then(function(data){
                    genBtn.disabled = false;
                    genBtn.textContent = '批量生成';
                    if (data.success) {
                        var results = data.data.results || [];
                        var savedCount = results.filter(function(r){ return r.post_id; }).length;
                        var html = '<div class="notice notice-success inline"><p>✅ 批量生成完成: ' + results.length + ' 篇';
                        if (savedCount > 0) {
                            html += ' (已保存' + savedCount + '篇到' + (data.data.target_status === 'publish' ? '已发布' : '草稿') + ')';
                        }
                        html += '</p></div>';
                        html += '<table class="widefat striped"><thead><tr><th>#</th><th>主题</th><th>生成</th><th>字数</th><th>文章状态</th><th>操作</th></tr></thead><tbody>';
                        results.forEach(function(r, i){
                            var postCell = '—';
                            var actionCell = '—';
                            if (r.post_id) {
                                postCell = r.post_status === 'publish' ? '🟢 已发布' : '📝 草稿';
                                var editUrl = '<?php echo esc_js(admin_url("post.php?action=edit&post=")); ?>' + r.post_id;
                                var viewUrl = '<?php echo esc_js(home_url("/?p=")); ?>' + r.post_id;
                                actionCell = '<a href="' + editUrl + '" target="_blank" style="font-size:11px;">编辑</a> | <a href="' + viewUrl + '" target="_blank" style="font-size:11px;">查看</a>';
                            } else if (r.success === false) {
                                postCell = '<span style="color:#DC2626;">' + escHtml(r.error || '失败') + '</span>';
                            }
                            html += '<tr><td>' + (i+1) + '</td><td>' + escHtml(r.topic) + '</td><td>' + (r.success ? '✅' : '❌') + '</td><td>' + (r.word_count || 0) + '</td><td>' + postCell + '</td><td>' + actionCell + '</td></tr>';
                        });
                        html += '</tbody></table>';
                        document.getElementById('cw-csv-result').innerHTML = html;
                    } else {
                        document.getElementById('cw-csv-result').innerHTML =
                            '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '批量生成失败') + '</p></div>';
                    }
                })
                .catch(function(e){
                    genBtn.disabled = false;
                    genBtn.textContent = '批量生成';
                    document.getElementById('cw-csv-result').innerHTML =
                        '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
                });
        });
    }

    // v11.4.2: 复制生成内容到剪贴板 (方案⑤配套)
    window.lk3CopyContent = function() {
        var resultEl = document.getElementById('cw-result');
        if (!resultEl) return;
        var contentDiv = resultEl.querySelector('div[style*="white-space:pre-wrap"]');
        if (!contentDiv) { alert('未找到内容'); return; }
        var text = contentDiv.textContent || contentDiv.innerText;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function(){
                alert('✅ 已复制 ' + text.length + ' 字到剪贴板');
            }, function(){ alert('复制失败, 请手动选择复制'); });
        } else {
            // 降级方案
            var ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); alert('✅ 已复制'); } catch(e) { alert('复制失败'); }
            document.body.removeChild(ta);
        }
    };
})();
</script>
