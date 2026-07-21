<?php
/**
 * 生态协同面板 v17.0 — 一键全流程
 *
 * v17.0 更新:
 *   - UI全量优化: 参照Linear/Notion的一键生产界面规范
 *   - 8模块全链路: 关键词→模版→内容→图片→SEO→标题→摘要→改写
 *   - 保留 feicai4.0 文案5阶段法进度可视化
 *   - 保留 v10.7.0 修复: 进度同步/内容预览/模版名称/HTML转义
 *
 * @package Linked3
 * @version 17.2.0
 * @date 2026-06-28
 */
if (!defined('ABSPATH')) exit;
// v17.2.0: 支持从URL参数预填主题 (跨子面板传递)
$eco_topic_preset = isset($_GET['topic']) ? sanitize_text_field($_GET['topic']) : '';
// v17.2.0: 云模版总控链接
$cloud_master_url = admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud');
// v11.8.0: 确保媒体库API可用 (图库导入功能依赖)
if (function_exists('wp_enqueue_media')) {
    wp_enqueue_media();
}
?>

<div class="linked3-eco-card">
    <h3>⚡ 一键生态生产</h3>
    <p style="color:#71717A;font-size:12px;margin-bottom:16px;">输入主题, 自动完成8模块全链路: 关键词 → 模版 → 内容 → 图片 → SEO → 标题 → 摘要 → 改写</p>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <input type="text" class="linked3-eco-input" id="eco-topic" placeholder="输入主题, 如: AI写作工具推荐" style="flex:1;min-width:300px;" value="<?php echo esc_attr($eco_topic_preset); ?>">
        <?php
        // v17.2.0 R6: 从长尾词库选题
        $saved_tail = (array) get_option(LINKED3_OPTION_PREFIX . 'tail_keywords', []);
        if (!empty($saved_tail)) :
        ?>
        <select class="linked3-eco-select" id="eco-tail-select" title="从长尾词库选择主题">
            <option value="">📋 从长尾词库选...</option>
            <?php foreach (array_slice($saved_tail, 0, 50) as $tail) : ?>
            <option value="<?php echo esc_attr($tail); ?>"><?php echo esc_html(mb_substr($tail, 0, 30)); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select class="linked3-eco-select" id="eco-category">
            <option value="content">内容模版</option>
            <option value="seo">SEO模版</option>
            <option value="social">社媒模版</option>
            <option value="video">视频模版</option>
        </select>
        <button class="linked3-eco-btn" id="eco-run-all">一键生态生产</button>
        <label style="font-size:12px;color:#52525B;display:flex;align-items:center;gap:4px;" title="勾选后: 生产完成→自动生图→自动组装保存草稿, 全流程无需手动">
            <input type="checkbox" id="eco-auto-gen-images" checked> 🔄 自动生图+组装
        </label>
    </div>

    <!-- v17.2: 思想DNA选择器 (全写作入口共享组件) -->
    <?php include __DIR__ . '/eco-style-dna-picker.php'; ?>

    <!-- feicai4.0 5阶段法进度 -->
    <div id="eco-phases" style="display:none;">
        <h4 style="font-size:13px;color:#3F3F46;margin-bottom:8px;">feicai4.0 文案5阶段法</h4>
        <div style="display:flex;gap:4px;margin-bottom:12px;">
            <div class="linked3-eco-flow-step" id="phase-1">① 上下文收集</div>
            <span class="linked3-eco-flow-arrow">→</span>
            <div class="linked3-eco-flow-step" id="phase-2">② 简报锁定</div>
            <span class="linked3-eco-flow-arrow">→</span>
            <div class="linked3-eco-flow-step" id="phase-3">③ 草稿生成</div>
            <span class="linked3-eco-flow-arrow">→</span>
            <div class="linked3-eco-flow-step" id="phase-4">④ 自检</div>
            <span class="linked3-eco-flow-arrow">→</span>
            <div class="linked3-eco-flow-step" id="phase-5">⑤ 交付</div>
        </div>
        <div class="linked3-eco-progress"><div class="linked3-eco-progress-bar" id="eco-bar"></div></div>
        <p style="margin-top:8px;color:#666;font-size:13px;" id="eco-status">准备中...</p>
    </div>

    <div id="eco-result" style="margin-top:16px;"></div>
</div>

<!-- v16.1.0: 引入生态共享JS库 (收敛 escHtml/generateImages/saveDraft 重复定义) -->
<?php include __DIR__ . '/eco-shared-js.php'; ?>

<script>
(function(){
    var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
    var nonce = '<?php echo esc_js(wp_create_nonce("linked3_content_writer")); ?>';

    // v16.1.0: escHtml 优先复用 Linked3EcoShared.escapeHtml (消除三处重复定义)
    var escHtml = (window.Linked3EcoShared && window.Linked3EcoShared.escapeHtml) ? window.Linked3EcoShared.escapeHtml : function(s) {
        if (s == null) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    };

    // v16.0.16 [公理α/β]: 错误四元组解析器 — 将任意异常归类为 {stage, http_status, server_msg, hint}
    // stage: 1=网络 2=权限/nonce 3=AI调用 4=组装 5=未知
    function parseEcoError(e) {
        var msg = (e && e.message) ? String(e.message) : String(e || '未知错误');
        var httpMatch = msg.match(/HTTP\s*(\d{3})/i);
        var httpStatus = httpMatch ? httpMatch[1] : 'N/A';
        var stage = 5, stageLabel = '未知', hint = '请重试, 若持续失败请联系管理员';
        if (/Failed to fetch|NetworkError|network|timeout|超时|网络/i.test(msg)) {
            stage = 1; stageLabel = '网络连接';
            hint = '网络连接失败。请检查: ①网络是否正常 ②服务器是否可达 ③防火墙是否拦截 admin-ajax.php';
        } else if (httpStatus === '403' || /403|无权限|安全校验|nonce|forbidden/i.test(msg)) {
            stage = 2; stageLabel = '权限/安全校验';
            hint = '权限或安全校验失败。请: ①重新登录WordPress后台 ②刷新页面获取新nonce ③检查用户角色是否有 edit_posts 权限';
        } else if (/AI|api|key|模型|model|quota|限流|429|500|502|503/i.test(msg)) {
            stage = 3; stageLabel = 'AI服务调用';
            if (/429|限流|rate/i.test(msg)) {
                hint = 'AI API 限流。请等待1-2分钟后重试, 或在设置→API设置中切换备用模型';
            } else if (/key|401|unauthorized|认证/i.test(msg)) {
                hint = 'AI API Key 无效或未配置。请前往 设置→API设置 检查并填写有效的 API Key';
            } else {
                hint = 'AI 服务异常。请: ①检查API Key配置 ②查看PHP错误日志 ③稍后重试';
            }
        } else if (/组装|assemble|template|模版|image|图片/i.test(msg)) {
            stage = 4; stageLabel = '内容组装';
            hint = '内容组装阶段失败。请: ①检查模版是否完整 ②检查图片服务是否可用 ③查看PHP错误日志';
        }
        return { stage: stage, stage_label: stageLabel, http_status: httpStatus, server_msg: msg, hint: hint };
    }

    document.addEventListener('DOMContentLoaded', function(){
        var runBtn = document.getElementById('eco-run-all');
        if (!runBtn) return;

        // v17.2.0 R6: 长尾词库下拉 → 自动填入主题
        var tailSelect = document.getElementById('eco-tail-select');
        if (tailSelect) {
            tailSelect.addEventListener('change', function(){
                if (this.value) {
                    document.getElementById('eco-topic').value = this.value;
                }
            });
        }

        // v10.7.0: 若URL带topic参数, 自动聚焦提示
        var presetTopic = document.getElementById('eco-topic').value;
        if (presetTopic) {
            document.getElementById('eco-topic').focus();
        }

        runBtn.addEventListener('click', function(){
            var topic = document.getElementById('eco-topic').value.trim();
            if (!topic) { alert('请输入主题'); return; }

            var category = document.getElementById('eco-category').value;
            // v18复审修复E2 [公理α: H↓ 消除"点了没反应"不确定性]
            // 根因: HTML无eco-platform元素, getElementById返回null, .value抛TypeError致handler中断
            // 修复: null防御 + 默认generic, 恢复AJAX链路
            var platformEl = document.getElementById('eco-platform');
            var platform = platformEl ? platformEl.value : 'generic';

            document.getElementById('eco-phases').style.display = 'block';
            document.getElementById('eco-result').innerHTML = '';

            // v10.7.0 Bug#3: 进度上限锁80%, AJAX完成才到100%
            var phases = ['phase-1','phase-2','phase-3','phase-4','phase-5'];
            var phaseNames = ['上下文收集','简报锁定','草稿生成','自检','交付'];
            var phaseIdx = 0;

            function updateProgress(idx, pct) {
                phases.forEach(function(p, i){
                    var el = document.getElementById(p);
                    if (el) {
                        if (i < idx) { el.classList.add('active'); }
                        else { el.classList.remove('active'); }
                    }
                });
                var bar = document.getElementById('eco-bar');
                if (bar) bar.style.width = pct + '%';
                var status = document.getElementById('eco-status');
                if (status && idx < phaseNames.length) {
                    status.textContent = '阶段' + (idx+1) + ': ' + phaseNames[idx] + '...';
                }
            }

            updateProgress(0, 10);

            var fd = new FormData();
            fd.append('action', 'linked3_eco_synergy');
            fd.append('nonce', nonce);
            fd.append('topic', topic);
            fd.append('category', category);
            fd.append('platform', platform);
            // v17.2: 从共享组件读取风格配置
            var styleCfg = window.lk3_get_style_config ? window.lk3_get_style_config() : { style_dna: '', tone: 'professional', humanize_modules: [] };
            fd.append('style_dna', styleCfg.style_dna);
            fd.append('tone', styleCfg.tone);
            fd.append('humanize_modules', JSON.stringify(styleCfg.humanize_modules));

            // v10.7.0 Bug#3: 进度模拟上限锁80% (phaseIdx最大到3=80%), 留20%给AJAX完成
            var progressTimer = setInterval(function(){
                phaseIdx++;
                if (phaseIdx < 4) {
                    updateProgress(phaseIdx, (phaseIdx+1) * 20);
                }
                // phaseIdx到达3(80%)后停止推进, 等待AJAX
            }, 600);

            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function(data){
                    clearInterval(progressTimer);

                    if (data.success) {
                        // v10.7.0 Bug#3: 成功才到100%
                        updateProgress(4, 100);
                        document.getElementById('eco-status').textContent = '生态生产完成!';

                        var ir = data.data.ir || {};
                        var q = data.data.quality || {};
                        var keywords = ir.keywords || [];
                        var content = ir.content || '';
                        var images = ir.images || [];
                        var template = ir.template || null;
                        // v11.0.1 #1: 保存到外层供组装使用
                        ecoContentForAssemble = content;
                        var ecoTopicForAssemble = topic;

                        // v10.7.0 Bug#4: 内容预览截断修复
                        var contentPreview;
                        if (!content) {
                            contentPreview = '未生成';
                        } else {
                            var plain = content.replace(/<[^>]+>/g, '');
                            contentPreview = escHtml(plain.length > 150 ? plain.substring(0, 150) + '...' : plain);
                        }

                        // v10.7.0 Bug#9: 模版名称三级fallback
                        var templateName = template ? (template.name || template.category || '已加载') : '未加载';

                        var html = '<div class="linked3-eco-card">' +
                            '<h3>生态生产结果</h3>' +
                            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">' +
                            '<div><strong>关键词 (' + keywords.length + '):</strong><br><span style="font-size:12px;color:#666;">' + keywords.slice(0,8).map(escHtml).join(', ') + '</span></div>' +
                            '<div><strong>模版:</strong><br><span style="font-size:12px;color:#666;">' + escHtml(templateName) + '</span></div>' +
                            '</div>' +
                            // v10.7.3: 完整文章展示 (SOP闭环)
                            '<div style="margin-bottom:12px;">' +
                            '<strong>完整文章 (' + content.replace(/<[^>]+>/g,'').length + ' 字):</strong>' +
                            '<div style="background:#f9fafb;padding:12px;border-radius:4px;max-height:500px;overflow-y:auto;margin-top:8px;border:1px solid #e5e7eb;">' +
                            '<div style="white-space:pre-wrap;font-size:13px;line-height:1.8;">' + escHtml(content) + '</div>' +
                            '</div></div>' +
                            // v10.7.3: 图片详情展示
                            '<div style="margin-bottom:12px;">' +
                            '<strong>图片配置 (' + images.length + ' 张):</strong>' +
                            '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;margin-top:8px;">';
                        images.forEach(function(img, idx) {
                            html += '<div style="background:#f9fafb;padding:8px;border-radius:4px;border:1px solid #e5e7eb;">' +
                                '<div style="font-size:12px;font-weight:600;color:#3F3F46;margin-bottom:4px;">📷 ' + escHtml(img.type || ('img_' + idx)) + '</div>' +
                                '<div style="font-size:11px;color:#71717A;margin-bottom:2px;">分辨率: ' + escHtml(img.resolution || '1280*1280') + '</div>' +
                                '<div style="font-size:11px;color:#71717A;margin-bottom:2px;">布局: ' + escHtml(img.layout || 'list') + '</div>' +
                                '<div style="font-size:11px;color:#9ca3af;line-height:1.4;">Prompt: ' + escHtml((img.prompt || '').substring(0, 80)) + (img.prompt && img.prompt.length > 80 ? '...' : '') + '</div>' +
                                '</div>';
                        });
                        html += '</div></div>' +
                            // v11.1.0 #1: 生产用提示词展示 (方便第三方复用)
                            '<div style="margin-bottom:12px;" id="eco-prompts-section">' +
                            '<details style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:4px;padding:10px;">' +
                            '<summary style="cursor:pointer;font-weight:600;color:#92400E;font-size:13px;">📋 生产用提示词 (点击展开, 可复制到第三方工具复用)</summary>' +
                            '<div style="margin-top:10px;">';

                        // 文章生成提示词
                        var cfg = template ? (template.config || template) : {};
                        var articlePrompt = '请为主题「' + topic + '」撰写一篇文章。\n\n模版要求:\n';
                        if (cfg.role) articlePrompt += '你的角色: ' + cfg.role + '\n';
                        if (cfg.scene) articlePrompt += '适用场景: ' + cfg.scene + '\n';
                        if (cfg.background) articlePrompt += '背景: ' + cfg.background + '\n';
                        if (cfg.goals) articlePrompt += '目标: ' + (Array.isArray(cfg.goals) ? cfg.goals.join('、') : cfg.goals) + '\n';
                        if (cfg.skills) articlePrompt += '技能要求: ' + (Array.isArray(cfg.skills) ? cfg.skills.join('、') : cfg.skills) + '\n';
                        if (cfg.style) articlePrompt += '风格: ' + cfg.style + '\n';
                        if (cfg.limit) articlePrompt += '限制: ' + (Array.isArray(cfg.limit) ? cfg.limit.join('、') : cfg.limit) + '\n';
                        if (cfg.step) articlePrompt += '写作步骤: ' + (Array.isArray(cfg.step) ? cfg.step.join(' → ') : cfg.step) + '\n';
                        if (cfg.output) articlePrompt += '输出格式: ' + cfg.output + '\n';
                        articlePrompt += '\n关键词: ' + keywords.join('、') + '\n字数: 约800字\n语气: professional\n\n严格要求:\n1. 内容具体、有信息量, 不要空话套话\n2. 不要使用「赋能/闭环/抓手/底层逻辑」等AI高频词\n3. 适合博客/公众号发布\n4. 直接输出正文, 不要说明';

                        html += '<div style="margin-bottom:12px;">' +
                            '<div style="font-size:12px;font-weight:600;color:#3F3F46;margin-bottom:4px;">📝 文章生成提示词</div>' +
                            '<pre style="white-space:pre-wrap;font-size:11px;line-height:1.6;color:#1f2937;background:#fff;padding:10px;border-radius:4px;border:1px solid #e5e7eb;max-height:300px;overflow:auto;">' + escHtml(articlePrompt) + '</pre>' +
                            '<button class="button button-small eco-copy-prompt" data-prompt="' + escHtml(articlePrompt).replace(/"/g, '&quot;') + '" style="margin-top:4px;">📋 复制文章提示词</button>' +
                            '</div>';

                        // 图片生成提示词
                        if (images && images.length > 0) {
                            html += '<div style="margin-bottom:12px;">' +
                                '<div style="font-size:12px;font-weight:600;color:#3F3F46;margin-bottom:4px;">🎨 图片生成提示词 (' + images.length + '张)</div>';
                            images.forEach(function(img, idx) {
                                html += '<div style="margin-bottom:8px;">' +
                                    '<div style="font-size:11px;font-weight:600;color:#0F172A;">📷 ' + escHtml(img.type || ('img_' + idx)) + ' (' + escHtml(img.resolution || '1280*1280') + ')</div>' +
                                    '<pre style="white-space:pre-wrap;font-size:11px;line-height:1.5;color:#1f2937;background:#fff;padding:8px;border-radius:4px;border:1px solid #e5e7eb;max-height:150px;overflow:auto;">' + escHtml(img.prompt || '') + '</pre>' +
                                    '<button class="button button-small eco-copy-prompt" data-prompt="' + escHtml(img.prompt || '').replace(/"/g, '&quot;') + '" style="margin-top:2px;font-size:10px;">📋 复制' + escHtml(img.type || ('img_' + idx)) + '提示词</button>' +
                                    '</div>';
                            });
                            html += '</div>';
                        }

                        html += '</div></details></div>' +
                            // v10.7.4: 生成图片按钮 (SOP闭环下一步)
                            '<div style="margin-bottom:12px;" id="eco-gen-images-section">' +
                            '<button class="linked3-eco-btn" id="eco-gen-images-btn">🎨 生成图片 (调用AI API)</button>' +
                            '<span id="eco-gen-images-status" style="margin-left:8px;font-size:12px;color:#71717A;"></span>' +
                            '<div id="eco-gen-images-result" style="margin-top:8px;"></div>' +
                            '</div>' +
                            // 质检结果
                            '<div style="margin-top:12px;padding:12px;background:' + (q.passed ? '#dcfce7' : '#FEF2F2') + ';border-radius:4px;border:1px solid ' + (q.passed ? '#86efac' : '#FECACA') + ';">' +
                            '<strong>质检: ' + (q.score||0) + '/100 ' + (q.passed ? '✅通过' : '❌未通过') + '</strong>';
                        // 质检详情
                        if (q.checks) {
                            html += '<div style="margin-top:8px;font-size:12px;">';
                            for (var ck in q.checks) {
                                var passed = q.checks[ck];
                                html += '<span style="margin-right:12px;">' + (passed ? '✅' : '❌') + ' ' + escHtml(ck) + '</span>';
                            }
                            html += '</div>';
                        }
                        html += '</div>' +
                            // v11.8.0: 操作按钮 (SOP闭环 — 图文组装后才可保存)
                            '<div style="margin-top:12px;padding:12px;background:#fffbeb;border:1px solid #FDE68A;border-radius:6px;">' +
                            '<strong style="font-size:13px;color:#92400E;">📋 下一步: 图文组装</strong>' +
                            '<p style="font-size:12px;color:#78716c;margin:4px 0 8px 0;">当前为纯文本文章。建议先生成/导入图片并组装为图文文章, 再保存草稿或发布。</p>' +
'<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
                            '<button class="linked3-eco-btn" id="eco-quick-save-text" style="opacity:0.6;">💾 仅保存文本草稿</button>' +
                            '<button class="linked3-eco-btn linked3-eco-btn-secondary" id="eco-go-content">✏️ 去内容写作编辑</button>' +
                            '<button class="linked3-eco-btn linked3-eco-btn-secondary" id="eco-go-publish">📤 去发布</button>' +
                            '</div></div>';
                        window.ecoCurrentImages = images;
                        document.getElementById('eco-result').innerHTML = html;
                        // v11.9.1: 用事件绑定替代内联onclick, 避免转义地狱
                        var goContent = document.getElementById('eco-go-content');
                        if (goContent) goContent.addEventListener('click', function(){ location.href = '<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content")); ?>'; });
                        var goPublish = document.getElementById('eco-go-publish');
                        if (goPublish) goPublish.addEventListener('click', function(){ location.href = '<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish")); ?>'; });
                        // v11.9.1: 生成图片按钮事件绑定
                        var genImgBtn = document.getElementById('eco-gen-images-btn');
                        if (genImgBtn) genImgBtn.addEventListener('click', function(){ window.ecoGenerateImages(); });

                        // v16.0.24修复: 一键生态自动闭环 — 生产成功后自动生图→自动组装
                        // [公理α: H↓ 消除"需手动生图组装"不确定性] [公理β: dim↓ 1键全自动替代3步手动]
                        var autoGenCheckbox = document.getElementById('eco-auto-gen-images');
                        if (!autoGenCheckbox || autoGenCheckbox.checked) {
                            // 延迟1秒自动触发生图 (让用户看到生产完成结果)
                            setTimeout(function(){
                                if (window.ecoGenerateImages) {
                                    var autoStatus = document.getElementById('eco-status');
                                    if (autoStatus) autoStatus.textContent = '自动生成图片中... (一键闭环)';
                                    window.ecoGenerateImages(true); // true=自动模式, 生图后自动组装
                                }
                            }, 1000);
                        }
                    } else {
                        // v10.7.0 Bug#3: 失败时进度条标红, 不显示100%
                        clearInterval(progressTimer);
                        var bar = document.getElementById('eco-bar');
                        if (bar) { bar.style.background = '#EF4444'; bar.style.width = '100%'; }
                        document.getElementById('eco-status').textContent = '生产失败';
                        document.getElementById('eco-result').innerHTML =
                            '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '生产失败') + '</p></div>';
                    }
                })
                .catch(function(e){
                    clearInterval(progressTimer);
                    var bar = document.getElementById('eco-bar');
                    if (bar) { bar.style.background = '#EF4444'; bar.style.width = '100%'; }

                    // v16.0.16 [公理α: H↓ 消除"为什么失败"不确定性] [公理β: dim↓ 1维四元组替代3维排查]
                    // 错误四元组: {stage, http_status, server_msg, hint}
                    // stage: 1=网络 2=权限/nonce 3=AI调用 4=组装 5=未知
                    var errQuad = parseEcoError(e);

                    var status = document.getElementById('eco-status');
                    if (status) status.textContent = '错误 [' + errQuad.stage_label + ']: ' + errQuad.hint;

                    var errHtml = '<div class="notice notice-error inline" style="border-left:4px solid #DC2626;">'
                        + '<p style="font-weight:600;color:#DC2626;">❌ 一键生态生产失败</p>'
                        + '<div style="background:#fef2f2;padding:10px;border-radius:4px;font-size:12px;line-height:1.8;">'
                        + '<div><strong>阶段:</strong> ' + escHtml(errQuad.stage_label) + '</div>'
                        + '<div><strong>HTTP状态:</strong> ' + escHtml(errQuad.http_status) + '</div>'
                        + '<div><strong>服务器消息:</strong> ' + escHtml(errQuad.server_msg) + '</div>'
                        + '<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #FCA5A5;"><strong>💡 修复建议:</strong> ' + escHtml(errQuad.hint) + '</div>'
                        + '</div></div>';
                    document.getElementById('eco-result').innerHTML = errHtml;
                });
        });
    });

    // v10.7.4: 图片生成函数 (SOP闭环下一步)
    // v11.0.1 #1: generatedImageUrls 收集成功图片供组装使用
    var generatedImageUrls = [];
    var ecoContentForAssemble = '';
    // v16.0.24: 支持自动模式参数 isAuto — 生图后自动组装
    window.ecoGenerateImages = function(isAuto) {
        var images = window.ecoCurrentImages || [];
        var btn = document.getElementById('eco-gen-images-btn');
        var status = document.getElementById('eco-gen-images-status');
        var result = document.getElementById('eco-gen-images-result');
        if (!btn) return;
        btn.disabled = true;
        btn.textContent = '生成中...';
        status.textContent = '正在调用AI图片API, 请耐心等待...';
        status.style.color = '#0F172A';
        result.innerHTML = '';

        var fd = new FormData();
        fd.append('action', 'linked3_eco_generate_images');
        fd.append('nonce', nonce);
        fd.append('images', JSON.stringify(images));

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(function(data){
                btn.disabled = false;
                btn.textContent = '🎨 重新生成图片';
                if (data.success) {
                    var results = data.data.results || [];
                    status.textContent = data.data.message || '生成完成';
                    status.style.color = '#10B981';
                    var html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">';
                    results.forEach(function(r) {
                        if (r.success && r.url) {
                            // v16.0.25: 图片预览改为可点击大图(lightbox) + 缩略图尺寸适中
                            html += '<div style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;cursor:pointer;" onclick="ecoOpenLightbox(\'' + escHtml(r.url) + '\')">' +
                                '<img src="' + escHtml(r.url) + '" style="width:100%;max-height:200px;object-fit:cover;display:block;" />' +
                                '<div style="padding:6px;font-size:11px;background:#FAFAFA;">' +
                                '<strong>✅ ' + escHtml(r.type) + '</strong> <span style="color:#0F172A;font-size:10px;">🔍 点击查看大图</span><br>' +
                                '<span style="color:#71717A;">模型: ' + escHtml(r.model) + '</span>' +
                                '</div></div>';
                            // v11.0.1 #1: 收集成功图片URL供组装使用
                            generatedImageUrls.push({type: r.type, url: r.url});
                        } else {
                            html += '<div style="border:1px solid #FECACA;border-radius:6px;padding:8px;background:#fef2f2;">' +
                                '<strong>❌ ' + escHtml(r.type) + '</strong><br>' +
                                '<span style="color:#DC2626;font-size:11px;">' + escHtml(r.error || '未知错误') + '</span>' +
                                '</div>';
                        }
                    });
                    html += '</div>';
                    result.innerHTML = html;

                    // v11.0.1 #1: SOP闭环最后一步 — 组装文章+图片, 提供发布按钮
                    // v11.8.0: 增强组装逻辑 — 支持require_html格式 + 媒体库导入
                    if (generatedImageUrls.length > 0) {
                        var assembleHtml = '<div style="margin-top:16px;padding:12px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;">' +
                            '<h4 style="margin:0 0 8px 0;color:#0F172A;">📦 组装最终文章 (SOP闭环)</h4>' +
                            '<p style="font-size:12px;color:#71717A;margin:0 0 8px 0;">已生成 ' + generatedImageUrls.length + ' 张图片, 可组装为含图文章并保存为草稿。</p>' +
                            '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">' +
                            '<button class="linked3-eco-btn" id="eco-assemble-btn">📦 组装并保存草稿</button>' +
                            '<button class="linked3-eco-btn linked3-eco-btn-secondary" id="eco-media-import-btn">📁 从媒体库导入图片</button>' +
                            '<span id="eco-assemble-status" style="font-size:12px;"></span>' +
                            '</div></div>';
                        result.innerHTML += assembleHtml;

                        // v11.8.0: 从媒体库导入图片
                        document.getElementById('eco-media-import-btn').addEventListener('click', function() {
                            var frame = wp.media({
                                title: '选择要插入文章的图片',
                                multiple: true,
                                button: { text: '插入到文章' }
                            });
                            frame.on('select', function() {
                                var selection = frame.state().get('selection').toJSON();
                                selection.forEach(function(attachment) {
                                    if (attachment.url) {
                                        generatedImageUrls.push({
                                            type: 'content_' + (generatedImageUrls.length + 1),
                                            url: attachment.url
                                        });
                                    }
                                });
                                var st = document.getElementById('eco-assemble-status');
                                st.textContent = '✓ 已导入 ' + selection.length + ' 张媒体库图片, 共 ' + generatedImageUrls.length + ' 张';
                                st.style.color = '#10B981';
                            });
                            frame.open();
                        });

                        document.getElementById('eco-assemble-btn').addEventListener('click', function() {
                            var btn = this;
                            var st = document.getElementById('eco-assemble-status');

                            // R5修复: 组装前检查内容是否为空
                            if (!ecoContentForAssemble || ecoContentForAssemble.trim().length < 10) {
                                st.innerHTML = '<span style="color:#EF4444;">❌ 文章内容为空或过短, 无法组装。请先重新生成内容。</span>';
                                return;
                            }

                            btn.disabled = true; btn.textContent = '组装中...';
                            st.innerHTML = '<span style="color:#0F172A;">⏳ 正在组装并保存草稿... <span class="eco-spinner"></span></span>';
                            // R4修复: 组装重试计数器
                            if (typeof assembleRetryCount === 'undefined') {
                                assembleRetryCount = 0;
                                maxRetries = 2;
                            }
                            // v16.0.25修复: 组装超时处理 — v17.0: 增加到120秒+重试机制
                            var assembleTimeout = setTimeout(function(){
                                btn.disabled = false; btn.textContent = '📦 组装并保存草稿';
                                st.innerHTML = '<span style="color:#F59E0B;">⏰ 组装超时(120秒), 可能是图片下载慢。</span><br><button class="linked3-eco-btn linked3-eco-btn-sm" style="margin-top:4px;" onclick="document.getElementById(\'eco-assemble-btn\').click();">🔄 重试组装</button> · <a href="' + '<?php echo esc_js(admin_url("edit.php?post_status=draft&post_type=post")); ?>' + '" target="_blank" style="font-size:12px;color:#0F172A;">查看草稿列表</a>';
                            }, 120000);
                            // v11.8.0: 根据require_html选择图片插入格式
                            var requireHtml = <?php echo esc_js(!empty(get_option(LINKED3_OPTION_PREFIX . 'advanced_settings', [])['require_html']) ? 'true' : 'false'); ?>;
                            var assembledContent = ecoContentForAssemble;
                            generatedImageUrls.forEach(function(img) {
                                var imgTag = requireHtml
                                    ? '\n\n<figure><img src="' + escHtml(img.url) + '" alt="' + escHtml(img.type) + '" style="max-width:100%;height:auto;" /></figure>\n\n'
                                    : '\n\n![' + escHtml(img.type) + '](' + escHtml(img.url) + ')\n\n';
                                if (img.type === 'featured') {
                                    assembledContent = imgTag + assembledContent;
                                } else {
                                    // content_1, content_2 插入到对应段落后
                                    var idx = parseInt((img.type.match(/\d+/) || [1])[0]) || 1;
                                    // v11.8.0: 同时支持Markdown ## 和HTML <h2> 分割
                                    var parts = assembledContent.split(/\n(?=## |<h2)/);
                                    if (parts.length > idx) {
                                        parts.splice(idx, 0, imgTag);
                                        assembledContent = parts.join('\n');
                                    } else {
                                        assembledContent += imgTag;
                                    }
                                }
                            });
                            // 保存草稿
                            var title = ecoTopicForAssemble || 'AI生成文章';
                            var fd = new FormData();
                            fd.append('action', 'linked3_eco_save_draft');
                            fd.append('nonce', nonce);
                            fd.append('title', title);
                            fd.append('content', assembledContent);
                            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                                .then(function(r){ return r.json(); })
                                .then(function(d){
                                    clearTimeout(assembleTimeout); // v16.0.25: 清除超时
                                    btn.disabled = false; btn.textContent = '📦 组装并保存草稿';
                                    if (d.success) {
                                        st.innerHTML = '✅ 已组装并保存为草稿 (ID: ' + d.data.post_id + ') <a href="' + d.data.edit_url + '" target="_blank" style="margin-left:8px;color:#3B82F6;">→ 编辑草稿</a>';
                                        st.style.color = '#10B981';
                                    } else {
                                        // R4修复: 失败时显示详细错误 + 自动重试
                                        var errMsg = d.data && d.data.message ? d.data.message : '未知错误';
                                        assembleRetryCount++;
                                        if (assembleRetryCount <= maxRetries) {
                                            st.innerHTML = '<span style="color:#F59E0B;">⚠ 第' + assembleRetryCount + '次失败: ' + escHtml(errMsg) + ', 2秒后自动重试...</span>';
                                            setTimeout(function(){ btn.click(); }, 2000);
                                        } else {
                                            st.innerHTML = '<span style="color:#EF4444;">❌ 组装失败(' + assembleRetryCount + '次): ' + escHtml(errMsg) + '</span><br><button class="linked3-eco-btn linked3-eco-btn-sm" style="margin-top:4px;" onclick="document.getElementById(\'eco-assemble-btn\').click();">🔄 手动重试</button>';
                                        }
                                    }
                                    // R4修复: 临时变量占位 (原成功分支)
                                    if (false && d.success) {
                                        st.innerHTML = '✅ 已组装并保存为草稿 <a href="' + escHtml(d.data.edit_url || '') + '" target="_blank">→ 编辑文章</a>';
                                        st.style.color = '#10B981';
                                    } else {
                                        st.textContent = '❌ ' + (d.data && d.data.message ? d.data.message : '保存失败');
                                        st.style.color = '#EF4444';
                                    }
                                })
                                .catch(function(e){
                                    clearTimeout(assembleTimeout); // v16.0.25: 清除超时
                                    btn.disabled = false; btn.textContent = '📦 组装并保存草稿';
                                    st.textContent = '❌ 错误: ' + e.message;
                                    st.style.color = '#EF4444';
                                });
                        });

                        // v16.0.24: 自动模式 — 生图成功后自动点击组装按钮, 形成闭环
                        if (isAuto && generatedImageUrls.length > 0) {
                            var assembleBtn = document.getElementById('eco-assemble-btn');
                            if (assembleBtn) {
                                var autoStatus = document.getElementById('eco-status');
                                if (autoStatus) autoStatus.textContent = '自动组装图文并保存草稿中... (一键闭环)';
                                setTimeout(function(){ assembleBtn.click(); }, 800);
                            }
                        }
                    }
                } else {
                    status.textContent = '❌ ' + (data.data && data.data.message ? data.data.message : '生成失败');
                    status.style.color = '#EF4444';
                    result.innerHTML = '<div class="notice notice-error inline"><p>' + escHtml(data.data && data.data.message ? data.data.message : '生成失败') + '</p><p><a href="' + '<?php echo esc_js(admin_url("admin.php?page=linked3-dashboard&tab=system&sy_sub=api")); ?>' + '">→ 去API设置页配置图片API</a></p></div>';
                }
            })
            .catch(function(e){
                btn.disabled = false;
                btn.textContent = '🎨 生成图片 (调用AI API)';
                status.textContent = '❌ 错误: ' + e.message;
                status.style.color = '#EF4444';
                result.innerHTML = '<div class="notice notice-error inline"><p>错误: ' + escHtml(e.message) + '</p></div>';
            });
    };

    // v11.8.0: "仅保存文本草稿"按钮事件委托
    document.addEventListener('click', function(e){
        if (e.target && e.target.id === 'eco-quick-save-text') {
            var btn = e.target;
            var title = ecoTopicForAssemble || prompt('请输入文章标题:', ecoTopicForAssemble || 'AI生成文章');
            if (!title) return;
            btn.disabled = true; btn.textContent = '保存中...';
            var fd = new FormData();
            fd.append('action', 'linked3_eco_save_draft');
            fd.append('nonce', nonce);
            fd.append('title', title);
            fd.append('content', ecoContentForAssemble);
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(d){
                    btn.disabled = false; btn.textContent = '💾 仅保存文本草稿';
                    if (d.success) {
                        alert('✅ 已保存为草稿(纯文本, 无图)');
                    } else {
                        alert('❌ ' + (d.data && d.data.message ? d.data.message : '保存失败'));
                    }
                })
                .catch(function(e){
                    btn.disabled = false; btn.textContent = '💾 仅保存文本草稿';
                    alert('❌ 错误: ' + e.message);
                });
        }
    });

    // v11.1.0 #1: 复制提示词按钮 (事件委托, 支持动态生成)
    document.addEventListener('click', function(e){
        if (e.target && e.target.classList.contains('eco-copy-prompt')) {
            var promptText = e.target.getAttribute('data-prompt') || '';
            // 解码HTML实体
            var txt = document.createElement('textarea');
            txt.innerHTML = promptText;
            promptText = txt.value;
            // 复制到剪贴板
            if (navigator.clipboard) {
                navigator.clipboard.writeText(promptText).then(function(){
                    var orig = e.target.textContent;
                    e.target.textContent = '✅ 已复制';
                    setTimeout(function(){ e.target.textContent = orig; }, 1500);
                });
            } else {
                // 降级方案
                var ta = document.createElement('textarea');
                ta.value = promptText;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                var orig = e.target.textContent;
                e.target.textContent = '✅ 已复制';
                setTimeout(function(){ e.target.textContent = orig; }, 1500);
            }
        }
    });
})();

// v16.0.25: 图片大图预览(lightbox)
window.ecoOpenLightbox = function(url) {
    var overlay = document.getElementById('eco-lightbox');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'eco-lightbox';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:99999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
        overlay.innerHTML = '<img src="" style="max-width:90%;max-height:90%;object-fit:contain;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.5);" /><div style="position:absolute;top:16px;right:24px;color:#fff;font-size:28px;cursor:pointer;">✕</div>';
        overlay.addEventListener('click', function(){ overlay.style.display = 'none'; });
        document.body.appendChild(overlay);
    }
    overlay.querySelector('img').src = url;
    overlay.style.display = 'flex';
};
</script>

<style>
/* v16.0.25: 组装中spinner动画 */
.eco-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid #E4E4E7;
    border-top-color: #0F172A;
    border-radius: 50%;
    animation: eco-spin 0.8s linear infinite;
    vertical-align: middle;
}
@keyframes eco-spin {
    to { transform: rotate(360deg); }
}
</style>
