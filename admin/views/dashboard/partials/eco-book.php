<?php
/**
 * 写书式学习垂直模块 v18.5 — 完整体系 + 写书工厂
 *
 * v18.5 新增: 写书工厂控制台 (YAML驱动6步自动执行)
 * 保留: v17.2 手动模式 (提示词生成器, 折叠在"手动模式"标签页)
 *
 * 核心哲学: 痛苦精进法 = 自学 + 写书式学习 + 精深练习
 * 第一性原理: 好书都是改出来的
 * 方法论: 写书式学习 = AI搭架子 + 语音主力 + 手写辅助
 * 目标: 每个人每年写3-5本电子书
 *
 * 6步流程: ①AI演示 → ②探索主题 → ③撰写大纲 → ④扩写小节 → ⑤完成初稿 → ⑥阅读修改
 *
 * @package Linked3
 * @version 18.5.0
 */
if (!defined('ABSPATH')) exit;
$nonce_book = wp_create_nonce('linked3_content_writer');
$ajax_url = admin_url('admin-ajax.php');

// 加载写书式学习完整知识库
$book_kb = [];
$book_kb_path = LINKED3_DIR . 'src/Classes/ContentWriter/book_templates/_index.json';
if (file_exists($book_kb_path)) {
    $book_kb = json_decode(file_get_contents($book_kb_path), true) ?: [];
}
$types = $book_kb['types'] ?? [];
$thinking_modes = $book_kb['six_steps']['step4_expand']['thinking_modes'] ?? [];
$tools = $book_kb['tools'] ?? [];
$core = $book_kb['core_philosophy'] ?? [];
$knowledge_systems = $book_kb['knowledge_systems'] ?? [];
$reading_prompts = $book_kb['reading_prompts'] ?? [];

// v18.5: 写书工厂路由表
$factory_types  = class_exists('Linked3_Type_Mode_Router') ? Linked3_Type_Mode_Router::get_all_types() : [];
$factory_modes  = class_exists('Linked3_Type_Mode_Router') ? Linked3_Type_Mode_Router::get_all_modes() : [];
$factory_levels = class_exists('Linked3_Type_Mode_Router') ? Linked3_Type_Mode_Router::get_all_iteration_levels() : [];
$factory_nonce  = wp_create_nonce('linked3_book_factory');
$current_project_id = isset($_GET['book_project']) ? sanitize_text_field($_GET['book_project']) : '';
$progress_nonce = $current_project_id && class_exists('Linked3_Book_Ajax_Actions') ? Linked3_Book_Ajax_Actions::generate_progress_nonce($current_project_id) : '';
?>


<!-- ═══════════════════════════════════════════════════════════════
     v18.5 写书工厂控制台
     ═══════════════════════════════════════════════════════════════ -->
<div class="linked3-eco-card" style="background:linear-gradient(135deg,#0F172A 0%,#1E293B 100%);color:#fff;border:none;margin-bottom:20px;">
    <h3 style="color:#fff;margin-top:0;">🚀 写书工厂 v18.5 — 一键自动出书</h3>
    <p style="color:#CBD5E1;margin-bottom:18px;">选类型 → 选模式 → 选档位 → 一键启动 → 自动6步执行 → 下载书稿</p>

    <!-- 工厂输入区 -->
    <div id="lk3-book-factory-input" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <input type="text" id="lk3-bf-book-title" placeholder="输入书名,如《AI产品经理实战手册》"
               style="flex:1;min-width:240px;padding:10px 12px;border-radius:6px;border:1px solid #334155;background:#1E293B;color:#fff;">

        <select id="lk3-bf-type" style="padding:10px 12px;border-radius:6px;border:1px solid #334155;background:#1E293B;color:#fff;">
<?php foreach ($factory_types as $tkey => $tlabel): ?>
            <option value="<?php echo esc_attr($tkey); ?>"><?php echo esc_html($tlabel); ?></option>
<?php endforeach; ?>
        </select>

        <select id="lk3-bf-mode" style="padding:10px 12px;border-radius:6px;border:1px solid #334155;background:#1E293B;color:#fff;">
<?php foreach ($factory_modes as $mkey => $mlabel): ?>
            <option value="<?php echo esc_attr($mkey); ?>"><?php echo esc_html($mlabel); ?></option>
<?php endforeach; ?>
        </select>

        <select id="lk3-bf-level" style="padding:10px 12px;border-radius:6px;border:1px solid #334155;background:#1E293B;color:#fff;">
<?php foreach ($factory_levels as $lkey => $ldata): ?>
            <option value="<?php echo esc_attr($lkey); ?>" title="<?php echo esc_attr($ldata['description']); ?>"><?php echo esc_html($ldata['label']); ?></option>
<?php endforeach; ?>
        </select>

        <button id="lk3-bf-start" type="button"
                style="padding:10px 24px;border-radius:6px;border:none;background:#3B82F6;color:#fff;font-weight:600;cursor:pointer;">
            🚀 一键启动写书工厂
        </button>
    </div>

    <!-- 错误提示 -->
    <div id="lk3-bf-error" style="display:none;background:#7F1D1D;color:#FECACA;padding:10px 12px;border-radius:6px;margin-bottom:12px;"></div>

    <!-- 进度面板 -->
    <div id="lk3-bf-progress-panel" style="display:none;background:#1E293B;padding:16px;border-radius:6px;border:1px solid #334155;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <h4 style="color:#fff;margin:0;">📊 写书进度</h4>
            <span id="lk3-bf-status" style="color:#94A3B8;font-size:13px;">准备中...</span>
        </div>

        <div style="background:#0F172A;border-radius:4px;height:8px;overflow:hidden;margin-bottom:12px;">
            <div id="lk3-bf-progress-bar" style="background:linear-gradient(90deg,#3B82F6,#10B981);height:100%;width:0%;transition:width 0.5s;"></div>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:12px;font-size:13px;color:#CBD5E1;flex-wrap:wrap;">
            <div>当前步骤: <span id="lk3-bf-current-step" style="color:#10B981;font-weight:600;">-</span></div>
            <div>章节进度: <span id="lk3-bf-chapter-progress" style="color:#10B981;font-weight:600;">0/0</span></div>
            <div>💰 已用: <span id="lk3-bf-cost" style="color:#FBBF24;font-weight:600;">$0.00</span></div>
            <div>📊 Token: <span id="lk3-bf-tokens" style="color:#FBBF24;font-weight:600;">0</span></div>
            <div>⏱️ 耗时: <span id="lk3-bf-elapsed" style="color:#FBBF24;font-weight:600;">00:00</span></div>
        </div>

        <div style="margin-bottom:12px;">
            <div style="color:#94A3B8;font-size:12px;margin-bottom:4px;">实时日志:</div>
            <pre id="lk3-bf-log-content" style="background:#0F172A;color:#A5F3FC;padding:10px;border-radius:4px;max-height:180px;overflow-y:auto;font-size:12px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
        </div>

        <!-- N4: 当前提示词显示区 (执行时可见) -->
        <div id="lk3-bf-current-prompt-area" style="display:none;margin-bottom:12px;background:#0F172A;padding:10px;border-radius:6px;border:1px solid #475569;">
            <div style="color:#A5F3FC;font-size:12px;margin-bottom:4px;font-weight:600;">📝 当前正在使用的提示词:</div>
            <pre id="lk3-bf-current-prompt" style="background:#1E293B;color:#E2E8F0;padding:8px;border-radius:4px;max-height:120px;overflow-y:auto;font-size:11px;line-height:1.4;margin:0;white-space:pre-wrap;"></pre>
        </div>

        <!-- v18.10: 实时输出显示区 (AI生成的完整内容) -->
        <div id="lk3-bf-current-output-area" style="display:none;margin-bottom:12px;background:#0F172A;padding:10px;border-radius:6px;border:1px solid #10B981;">
            <div style="color:#10B981;font-size:12px;margin-bottom:4px;font-weight:600;">📄 AI实时输出内容:</div>
            <pre id="lk3-bf-current-output" style="background:#1E293B;color:#F0FDF4;padding:8px;border-radius:4px;max-height:200px;overflow-y:auto;font-size:11px;line-height:1.5;margin:0;white-space:pre-wrap;"></pre>
        </div>

        <!-- v18.10: 增量下载区 (随时可下载, 不等全部完成) -->
        <div id="lk3-bf-incremental-download" style="display:none;margin-bottom:12px;background:#1E293B;padding:8px;border-radius:6px;border:1px solid #475569;">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span style="color:#94A3B8;font-size:11px;">📥 增量下载(进行中也可下载):</span>
                <button class="lk3-bf-dl-btn" data-format="markdown" style="padding:4px 10px;border-radius:3px;border:none;background:#475569;color:#fff;cursor:pointer;font-size:11px;">📄 Markdown</button>
                <button class="lk3-bf-dl-btn" data-format="html" style="padding:4px 10px;border-radius:3px;border:none;background:#475569;color:#fff;cursor:pointer;font-size:11px;">🌐 HTML</button>
            </div>
        </div>

        <div id="lk3-bf-download-area" style="display:none;background:#0F172A;padding:12px;border-radius:6px;border:1px solid #10B981;">
            <div style="color:#10B981;font-weight:600;margin-bottom:8px;">✅ 书稿已完成! 选择下载格式:</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="lk3-bf-dl-btn" data-format="markdown" style="padding:8px 16px;border-radius:4px;border:none;background:#10B981;color:#fff;cursor:pointer;">📄 下载 Markdown</button>
                <button class="lk3-bf-dl-btn" data-format="html" style="padding:8px 16px;border-radius:4px;border:none;background:#3B82F6;color:#fff;cursor:pointer;">🌐 下载 HTML</button>
                <button id="lk3-bf-copy-clipboard" type="button" style="padding:8px 16px;border-radius:4px;border:none;background:#6366F1;color:#fff;cursor:pointer;">📋 复制到剪贴板</button>
            </div>
        </div>
    </div>
</div>


<!-- N3: 提示词预览/编辑面板 -->
<div class="linked3-eco-card" style="margin-top:16px;border:1px solid #E4E4E7;">
    <details>
        <summary style="cursor:pointer;padding:12px;font-weight:600;color:#0F172A;">📝 提示词预览与编辑 (可见·可改·可保存, 点击展开)</summary>
        <div style="padding:12px;">
            <p style="font-size:12px;color:#71717A;margin:0 0 12px 0;">提示词默认从知识库加载, 根据书名/类型/模式自动填充变量。你可以预览、修改并保存自定义提示词, 工厂执行时优先使用你保存的版本。</p>
            <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center;">
                <label style="font-size:12px;color:#0F172A;">选择步骤:</label>
                <select id="lk3-bf-prompt-step" style="padding:6px 10px;border-radius:4px;border:1px solid #D4D4D8;">
                    <option value="step1_demo">① AI演示</option>
                    <option value="step2_explore">② 探索主题</option>
                    <option value="step3_outline" selected>③ 撰写大纲</option>
                    <option value="step4_expand">④ 扩写小节</option>
                    <option value="step6_review">⑥ 阅读修改</option>
                </select>
                <button id="lk3-bf-preview-prompt" type="button" style="padding:6px 14px;border-radius:4px;border:none;background:#3B82F6;color:#fff;cursor:pointer;font-size:12px;">👁 预览(填充变量)</button>
                <button id="lk3-bf-save-prompt" type="button" style="padding:6px 14px;border-radius:4px;border:none;background:#10B981;color:#fff;cursor:pointer;font-size:12px;">💾 保存自定义提示词</button>
            </div>
            <div style="margin-bottom:8px;">
                <div style="font-size:11px;color:#71717A;margin-bottom:4px;">提示词模板 (变量: {book_title} {book_type} {type_unit} {chapter_title} {section_title} {word_count}):</div>
                <textarea id="lk3-bf-prompt-editor" style="width:100%;min-height:120px;padding:10px;border-radius:4px;border:1px solid #D4D4D8;font-size:12px;font-family:monospace;background:#FAFAFA;" placeholder="点击预览查看填充后的提示词, 或直接编辑后保存"></textarea>
            </div>
            <div id="lk3-bf-prompt-vars" style="font-size:11px;color:#71717A;background:#FAFAFA;padding:8px;border-radius:4px;border:1px solid #E4E4E7;"></div>
        </div>
    </details>
</div>

<!-- v18.5 工厂 JS -->
<script>
(function($){
    var ajaxUrl = '<?php echo esc_js($ajax_url); ?>';
    var factoryNonce = '<?php echo esc_js($factory_nonce); ?>';
    var currentProjectId = '<?php echo esc_js($current_project_id); ?>';
    var progressNonce = '<?php echo esc_js($progress_nonce); ?>';
    var progressTimer = null;
    var startTime = null;
    var stepRunning = false; // R2: 防止并发触发run_step

    $('#lk3-bf-start').on('click', function(){
        var title = $('#lk3-bf-book-title').val().trim();
        if (!title) { showError('请输入书名'); return; }
        var data = {
            action: 'linked3_book_factory_start',
            nonce: factoryNonce,
            book_title: title,
            type: $('#lk3-bf-type').val(),
            mode: $('#lk3-bf-mode').val(),
            iteration_level: $('#lk3-bf-level').val()
        };
        $(this).prop('disabled', true).text('启动中...');
        hideError();
        $.post(ajaxUrl, data, function(resp){
            if (resp.success) {
                currentProjectId = resp.data.project_id;
                progressNonce = resp.data.progress_nonce;
                startTime = Date.now();
                $('#lk3-bf-progress-panel').show();
                log('✅ 工厂已启动, 项目ID: ' + currentProjectId);
                log('📦 开始执行6步管线...');
                startProgressPolling();
            } else {
                showError(resp.data && resp.data.message ? resp.data.message : '启动失败');
                $('#lk3-bf-start').prop('disabled', false).text('🚀 一键启动写书工厂');
            }
        }).fail(function(){
            showError('网络错误,请重试');
            $('#lk3-bf-start').prop('disabled', false).text('🚀 一键启动写书工厂');
        });
    });

    function startProgressPolling() {
        if (progressTimer) clearInterval(progressTimer);
        progressTimer = setInterval(pollProgress, 2000);
        pollProgress();
    }

    function pollProgress() {
        if (!currentProjectId || !progressNonce) return;
        $.get(ajaxUrl, {
            action: 'linked3_book_factory_progress',
            nonce: progressNonce,
            project_id: currentProjectId
        }, function(resp){
            if (!resp.success) return;
            var p = resp.data;
            updateProgressUI(p);

            // v18.7: 只要不是done/failed, 就触发run_step (智能路由step1-6)
            if (p.status !== 'done' && p.status !== 'failed' && !stepRunning) {
                stepRunning = true;
                $.post(ajaxUrl, {
                    action: 'linked3_book_factory_run_step',
                    nonce: factoryNonce,
                    project_id: currentProjectId
                }, function(stepResp){
                    stepRunning = false;
                    if (stepResp.success) {
                        var d = stepResp.data;
                        if (d.done) {
                            log('🎉 全部完成!');
                        } else if (d.step) {
                            var stepLabels = {
                                'step1_demo':'①AI演示', 'step2_explore':'②探索主题',
                                'step3_outline':'③撰写大纲', 'step4_expand':'④扩写小节',
                                'step5_complete':'⑤完成初稿', 'step6_review':'⑥阅读修改'
                            };
                            var label = stepLabels[d.step] || d.step;
                            if (d.step === 'step3_outline' && d.iter) {
                                log('📝 ' + label + ' (迭代 ' + d.iter + '/' + d.max_iter + ')');
                            } else if (d.step === 'step4_expand' && d.completed !== undefined) {
                                log('✍️ ' + label + ' (' + d.completed + '/' + d.total + ' 节)');
                            } else {
                                log('▶ ' + label + ' 完成');
                            }
                        }
                    } else {
                        var msg = stepResp.data && stepResp.data.message ? stepResp.data.message : '未知错误';
                        log('❌ 步骤失败: ' + msg);
                        if (msg.indexOf('Quota') !== -1 || msg.indexOf('配额') !== -1) {
                            showError('AI配额已用完, 请明天再试或升级套餐');
                        }
                    }
                }).fail(function(xhr, status){
                    stepRunning = false;
                    // v18.8: 详细错误诊断
                    var errMsg = '网络错误: ' + status + ' (HTTP ' + (xhr.status||'?') + ')';
                    if (xhr.responseText) {
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.data && resp.data.message) {
                                errMsg = resp.data.message;
                                if (resp.data.file) errMsg += '\n📍 ' + resp.data.file + ':' + resp.data.line;
                            }
                        } catch(e) {
                            // 非JSON响应, 截取前200字符
                            errMsg += '\n响应: ' + xhr.responseText.substring(0, 200);
                        }
                    }
                    log('❌ ' + errMsg);
                    showError(errMsg.split('\n')[0]);
                });
            }

            if (p.status === 'done') {
                clearInterval(progressTimer);
                $('#lk3-bf-download-area').show();
                $('#lk3-bf-status').text('✅ 完成').css('color','#10B981');
                log('🎉 书稿已完成!');
                $('#lk3-bf-start').prop('disabled', false).text('🚀 再写一本');
            } else if (p.status === 'failed') {
                clearInterval(progressTimer);
                $('#lk3-bf-status').text('❌ 失败').css('color','#EF4444');
                showError('工厂执行失败, 请查看日志');
                $('#lk3-bf-start').prop('disabled', false).text('🚀 重新启动');
            }
        });
    }

    function updateProgressUI(p) {
        var stepLabels = {idle:'待启动',demoing:'①AI演示',exploring:'②探索主题',outlining:'③撰写大纲',expanding:'④扩写小节',completing:'⑤完成初稿',reviewing:'⑥阅读修改',done:'✅完成',failed:'❌失败',paused:'⏸已暂停'};
        $('#lk3-bf-status').text(stepLabels[p.status] || p.status);
        $('#lk3-bf-current-step').text(stepLabels[p.status] || p.status);
        $('#lk3-bf-progress-bar').css('width', (p.progress_percent||0) + '%');
        $('#lk3-bf-chapter-progress').text((p.current_chapter||0) + '/' + (p.total_chapters||0));
        $('#lk3-bf-cost').text('$' + (p.cost_total||0).toFixed(4));
        $('#lk3-bf-tokens').text((p.tokens_total||0).toLocaleString());
        if (startTime) {
            var sec = Math.floor((Date.now() - startTime) / 1000);
            var m = String(Math.floor(sec/60)).padStart(2,'0');
            var s = String(sec%60).padStart(2,'0');
            $('#lk3-bf-elapsed').text(m + ':' + s);
        }

        // N4: 显示当前正在使用的提示词 (从progress接口获取)
        if (p.current_prompt) {
            $('#lk3-bf-current-prompt-area').show();
            $('#lk3-bf-current-prompt').text(p.current_prompt);
        }

        // v18.10: 显示AI实时输出内容
        if (p.current_output) {
            $('#lk3-bf-current-output-area').show();
            $('#lk3-bf-current-output').text(p.current_output);
        }

        // v18.10: 显示增量下载区 (有draft_markdown就显示)
        if (p.draft_markdown && p.draft_markdown.length > 50) {
            $('#lk3-bf-incremental-download').show();
        }

        // v18.10: 显示章节统计
        if (p.chapters_count !== undefined) {
            var info = p.chapters_count + '章 / ' + (p.sections_count || 0) + '节';
            $('#lk3-bf-chapter-progress').text(info);
        }
    }

    $('.lk3-bf-dl-btn[data-format]').on('click', function(){
        var format = $(this).data('format');
        window.location.href = ajaxUrl + '?action=linked3_book_factory_download&nonce=' + factoryNonce + '&project_id=' + currentProjectId + '&format=' + format;
    });

    $('#lk3-bf-copy-clipboard').on('click', function(){
        $.get(ajaxUrl, {action:'linked3_book_factory_progress',nonce:progressNonce,project_id:currentProjectId}, function(resp){
            if (resp.success && resp.data.draft_markdown) {
                navigator.clipboard.writeText(resp.data.draft_markdown).then(function(){ alert('已复制到剪贴板'); });
            }
        });
    });

    function log(msg) {
        var time = new Date().toLocaleTimeString();
        $('#lk3-bf-log-content').append('[' + time + '] ' + msg + '\n');
        $('#lk3-bf-log-content').scrollTop($('#lk3-bf-log-content')[0].scrollHeight);
    }
    function showError(msg) { $('#lk3-bf-error').text('❌ ' + msg).show(); }
    function hideError() { $('#lk3-bf-error').hide(); }

    // N3: 提示词预览
    $('#lk3-bf-preview-prompt').on('click', function(){
        var stepKey = $('#lk3-bf-prompt-step').val();
        var title = $('#lk3-bf-book-title').val().trim() || '示例书名';
        var type = $('#lk3-bf-type').val();
        var mode = $('#lk3-bf-mode').val();
        var level = $('#lk3-bf-level').val();
        $.post(ajaxUrl, {
            action: 'linked3_book_factory_preview_prompt',
            nonce: factoryNonce,
            step_key: stepKey,
            book_title: title,
            type: type, mode: mode, iteration_level: level
        }, function(resp){
            if (resp.success) {
                $('#lk3-bf-prompt-editor').val(resp.data.prompt);
                var varsHtml = '<strong>当前变量:</strong> ';
                $.each(resp.data.vars, function(k,v){ varsHtml += k+'='+v+'; '; });
                $('#lk3-bf-prompt-vars').html(varsHtml);
            } else {
                alert(resp.data && resp.data.message ? resp.data.message : '预览失败');
            }
        });
    });

    // N3: 保存自定义提示词
    $('#lk3-bf-save-prompt').on('click', function(){
        var stepKey = $('#lk3-bf-prompt-step').val();
        var promptText = $('#lk3-bf-prompt-editor').val();
        if (!promptText.trim()) { alert('提示词不能为空'); return; }
        $.post(ajaxUrl, {
            action: 'linked3_book_factory_save_prompt',
            nonce: factoryNonce,
            step_key: stepKey,
            prompt_text: promptText
        }, function(resp){
            if (resp.success) { alert('✅ 提示词已保存, 工厂将优先使用此版本'); }
            else { alert(resp.data && resp.data.message ? resp.data.message : '保存失败'); }
        });
    });

    if (currentProjectId && progressNonce) {
        $('#lk3-bf-progress-panel').show();
        startTime = Date.now();
        startProgressPolling();
    }
})(jQuery);
</script>


<!-- ═══════════════════════════════════════════════════════════════
     v17.2 手动模式 (折叠保留, 向后兼容)
     ═══════════════════════════════════════════════════════════════ -->
<details style="margin-top:20px;">
<summary style="cursor:pointer;padding:12px;background:#F1F5F9;border-radius:6px;font-weight:600;color:#0F172A;">📝 手动模式 (v17.2 提示词生成器, 点击展开)</summary>

<div class="linked3-eco-card" style="margin-top:12px;">
<div class="linked3-eco-card">
    <h3>📖 写书式学习 — 完整垂直生态</h3>

    <!-- 核心哲学 -->
    <?php if (!empty($core)): ?>
    <div style="background:#FAFAFA;border:1px solid #E4E4E7;border-left:3px solid #0F172A;border-radius:6px;padding:12px;margin-bottom:16px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:6px;">🧭 核心哲学: <?php echo esc_html($core['name'] ?? '痛苦精进法'); ?></div>
        <div style="font-size:12px;color:#52525B;line-height:1.8;">
            <strong>公式:</strong> <?php echo esc_html($core['formula'] ?? ''); ?><br>
            <strong>第一性原理:</strong> <?php echo esc_html($core['first_principle'] ?? ''); ?><br>
            <strong>方法论:</strong> <?php echo esc_html($core['methodology'] ?? ''); ?><br>
            <strong>目标:</strong> <?php echo esc_html($core['goal'] ?? ''); ?><br>
            <strong>忌讳:</strong> <span style="color:#DC2626;"><?php echo esc_html($core['taboo'] ?? ''); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- 工具链推荐 -->
    <?php if (!empty($tools)): ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;padding:10px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:6px;">
        <span style="font-size:11px;color:#71717A;align-self:center;">🔧 工具链:</span>
        <?php foreach ($tools as $category => $tool_list): ?>
            <?php foreach ($tool_list as $tname => $tdesc): ?>
                <span style="font-size:11px;padding:2px 8px;background:#FFFFFF;border:1px solid #D4D4D8;border-radius:4px;" title="<?php echo esc_attr($tdesc); ?>"><?php echo esc_html($tname); ?></span>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 写作类型选择 -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <label class="lk3-form-label" style="margin:0;white-space:nowrap;">写作类型:</label>
        <select class="linked3-eco-select" id="lk3-book-type" style="width:140px;" onchange="lk3BookTypeChange(this.value)">
            <?php foreach ($types as $tid => $tinfo): ?>
                <option value="<?php echo esc_attr($tid); ?>"><?php echo esc_html($tinfo['icon'] . ' ' . $tinfo['name_cn']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" class="linked3-eco-input" id="lk3-book-title" placeholder="书名/论文标题/剧本名..." style="flex:1;min-width:200px;" value="写书式学习">
    </div>

    <!-- 6步流程 -->
    <?php
    $steps = $book_kb['six_steps'] ?? [];
    $step_icons = ['①', '②', '③', '④', '⑤', '⑥'];
    $step_colors = ['#0F172A', '#059669', '#7C3AED', '#DB2777', '#475569', '#6366F1'];
    foreach ($steps as $step_key => $step_info):
        $idx = intval(substr($step_key, 4, 1)) - 1;
        $icon = $step_icons[$idx] ?? '';
        $color = $step_colors[$idx] ?? '#0F172A';
    ?>
    <div style="border:1px solid #E4E4E7;border-left:3px solid <?php echo esc_attr($color); ?>;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <span style="font-size:16px;"><?php echo esc_html($icon); ?></span>
            <strong style="font-size:13px;color:<?php echo esc_attr($color); ?>;"><?php echo esc_html($step_info['name'] ?? ''); ?></strong>
            <span style="font-size:11px;color:#71717A;"><?php echo esc_html($step_info['desc'] ?? ''); ?></span>
        </div>

        <?php
        // 渲染提示词
        $all_prompts = [];
        if (isset($step_info['prompts'])) $all_prompts = $step_info['prompts'];
        if (isset($step_info['prompts_simple'])) $all_prompts = array_merge($all_prompts, $step_info['prompts_simple']);
        if (isset($step_info['prompts_advanced'])) $all_prompts = array_merge($all_prompts, $step_info['prompts_advanced']);
        foreach ($all_prompts as $p):
        ?>
        <div style="margin-bottom:8px;">
            <div style="display:flex;gap:6px;align-items:flex-start;">
                <textarea class="linked3-eco-input" readonly style="flex:1;font-size:12px;min-height:60px;background:#FAFAFA;" id="lk3-book-<?php echo esc_attr($step_key); ?>-<?php echo esc_attr($p['id'] ?? ''); ?>"><?php echo esc_textarea($p['text'] ?? ''); ?></textarea>
                <button class="linked3-eco-btn linked3-eco-btn-sm" onclick="lk3CopyPrompt('lk3-book-<?php echo esc_attr($step_key); ?>-<?php echo esc_attr($p['id'] ?? ''); ?>')" style="white-space:nowrap;">📋 复制</button>
            </div>
            <?php if (!empty($p['note'])): ?>
            <div style="font-size:10px;color:#A1A1AA;margin-top:2px;">💡 <?php echo esc_html($p['note']); ?> · 🔧 <?php echo esc_html($p['tool'] ?? '任意大模型'); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (isset($step_info['variables']) && $step_key === 'step4_expand'): ?>
        <!-- 第四步: 扩写变量控制 -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;margin-top:8px;padding:10px;background:#FAFAFA;border-radius:4px;">
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;">语言</label>
                <select class="linked3-eco-select" id="lk3-book-s4-lang" style="font-size:11px;" onchange="lk3GenPromptS4()">
                    <option value="中文">中文</option>
                    <option value="English">English</option>
                    <option value="日本語">日本語</option>
                    <option value="法语">法语</option>
                    <option value="德语">德语</option>
                </select>
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;">读者人群</label>
                <input type="text" class="linked3-eco-input" id="lk3-book-s4-readers" value="所有人群" style="font-size:11px;" onchange="lk3GenPromptS4()">
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;">思维模式</label>
                <select class="linked3-eco-select" id="lk3-book-s4-thinking" style="font-size:11px;" onchange="lk3GenPromptS4()">
                    <?php foreach ($thinking_modes as $mode): ?>
                        <option value="<?php echo esc_attr($mode); ?>"><?php echo esc_html($mode); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;">小节名</label>
                <input type="text" class="linked3-eco-input" id="lk3-book-s4-section" value="1.1 写书式学习的起源与发展" style="font-size:11px;" onchange="lk3GenPromptS4()">
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;">字数</label>
                <input type="number" class="linked3-eco-input" id="lk3-book-s4-words" value="3000" style="font-size:11px;" onchange="lk3GenPromptS4()">
            </div>
            <div>
                <label style="font-size:10px;color:#71717A;display:block;margin-bottom:2px;">例子数</label>
                <input type="text" class="linked3-eco-input" id="lk3-book-s4-examples" value="2-3" style="font-size:11px;" onchange="lk3GenPromptS4()">
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($step_info['checklist'])): ?>
        <!-- 检查清单 -->
        <div style="margin-top:8px;padding:8px;background:#FAFAFA;border-radius:4px;">
            <div style="font-size:11px;font-weight:600;color:#3F3F46;margin-bottom:4px;">✅ 检查清单</div>
            <?php foreach ($step_info['checklist'] as $item): ?>
                <label style="display:block;font-size:11px;color:#52525B;margin-bottom:2px;"><input type="checkbox"> <?php echo esc_html($item); ?></label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- 知识体系库 -->
    <?php if (!empty($knowledge_systems)): ?>
    <div style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:10px;">🧠 知识体系库</div>
        <?php foreach ($knowledge_systems as $ks_id => $ks_info): ?>
        <details style="margin-bottom:8px;">
            <summary style="font-size:12px;font-weight:600;color:#3F3F46;cursor:pointer;padding:4px 0;"><?php echo esc_html($ks_info['name'] ?? $ks_id); ?> — <?php echo esc_html($ks_info['desc'] ?? ''); ?></summary>
            <div style="padding:8px 12px;font-size:11px;color:#52525B;line-height:1.8;">
                <?php if (!empty($ks_info['prompts'])): ?>
                    <?php foreach ($ks_info['prompts'] as $p): ?>
                    <div style="margin-bottom:6px;padding:6px;background:#FAFAFA;border-radius:4px;">
                        <?php echo esc_html($p); ?>
                        <button class="linked3-eco-btn linked3-eco-btn-sm" style="margin-left:4px;font-size:10px;" onclick="navigator.clipboard.writeText('<?php echo esc_js($p); ?>').then(function(){alert('已复制');})">📋</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($ks_info['phases'])): ?>
                    <?php foreach ($ks_info['phases'] as $phase_id => $phase): ?>
                    <div><strong><?php echo esc_html($phase_id); ?>:</strong> <?php echo esc_html($phase['name'] ?? ''); ?> — <?php echo esc_html($phase['desc'] ?? ''); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($ks_info['layers'])): ?>
                    <?php foreach ($ks_info['layers'] as $lid => $layer): ?>
                    <div><strong><?php echo esc_html($lid); ?>:</strong> <?php echo esc_html($layer['name'] ?? ''); ?> — <?php echo esc_html($layer['desc'] ?? ''); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($ks_info['levels'])): ?>
                    <?php foreach ($ks_info['levels'] as $level => $desc): ?>
                    <div><strong><?php echo esc_html($level); ?>:</strong> <?php echo esc_html($desc); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($ks_info['template'])): ?>
                <div style="margin-top:6px;padding:6px;background:#FAFAFA;border-radius:4px;">
                    <strong>模板:</strong> <?php echo esc_html($ks_info['template']); ?>
                </div>
                <?php endif; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 阅读提示词库 -->
    <?php if (!empty($reading_prompts)): ?>
    <div style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:10px;">📚 阅读提示词库</div>
        <?php foreach ($reading_prompts as $rp_id => $rp_text): ?>
        <div style="margin-bottom:6px;padding:6px;background:#FAFAFA;border-radius:4px;font-size:11px;color:#52525B;">
            <strong><?php echo esc_html($rp_id); ?>:</strong> <?php echo esc_html($rp_text); ?>
            <button class="linked3-eco-btn linked3-eco-btn-sm" style="margin-left:4px;font-size:10px;" onclick="navigator.clipboard.writeText('<?php echo esc_js($rp_text); ?>').then(function(){alert('已复制');})">📋</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 万能协作法 -->
    <?php if (!empty($book_kb['collaboration_method'])): ?>
    <div style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:10px;">🤝 <?php echo esc_html($book_kb['collaboration_method']['name'] ?? '万能协作法'); ?></div>
        <div style="font-size:11px;color:#52525B;margin-bottom:8px;"><?php echo esc_html($book_kb['collaboration_method']['desc'] ?? ''); ?></div>
        <?php foreach ($book_kb['collaboration_method']['steps'] ?? [] as $step): ?>
        <div style="margin-bottom:6px;padding:6px;background:#FAFAFA;border-radius:4px;font-size:11px;color:#52525B;">
            <strong>步骤<?php echo esc_html($step['step']); ?>:</strong> <?php echo esc_html($step['name']); ?> — <?php echo esc_html($step['desc']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- AI原生工作流 -->
    <?php if (!empty($book_kb['workflow'])): ?>
    <div style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:10px;">⚡ <?php echo esc_html($book_kb['workflow']['name'] ?? 'AI原生工作流'); ?></div>
        <div style="font-size:11px;color:#52525B;margin-bottom:8px;"><?php echo esc_html($book_kb['workflow']['desc'] ?? ''); ?></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;">
            <?php foreach ($book_kb['workflow']['roles'] ?? [] as $role_id => $role_desc): ?>
            <div style="padding:6px;background:#FAFAFA;border-radius:4px;font-size:11px;color:#52525B;">
                <strong><?php echo esc_html($role_id); ?>:</strong> <?php echo esc_html($role_desc); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($book_kb['workflow']['metrics'])): ?>
        <div style="margin-top:8px;font-size:11px;color:#52525B;">
            <strong>实测数据:</strong>
            日迭代<?php echo esc_html($book_kb['workflow']['metrics']['daily_iterations'] ?? ''); ?> ·
            并行项目<?php echo esc_html($book_kb['workflow']['metrics']['parallel_projects'] ?? ''); ?> ·
            人效提升<?php echo esc_html($book_kb['workflow']['metrics']['efficiency_boost'] ?? ''); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 出版社资源 -->
    <?php if (!empty($book_kb['publishing'])): ?>
    <details style="border:1px solid #E4E4E7;border-radius:6px;padding:14px;margin-bottom:12px;">
        <summary style="font-size:13px;font-weight:600;color:#0F172A;cursor:pointer;">🏢 <?php echo esc_html($book_kb['publishing']['name'] ?? '出版社资源'); ?> (共<?php echo esc_html($book_kb['publishing']['total'] ?? 100); ?>家)</summary>
        <div style="font-size:11px;color:#52525B;margin-top:8px;line-height:1.8;">
            <?php echo esc_html($book_kb['publishing']['desc'] ?? ''); ?>
            <div style="margin-top:6px;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:4px;">
                <?php foreach ($book_kb['publishing']['categories'] ?? [] as $cat => $count): ?>
                <div style="padding:4px 6px;background:#FAFAFA;border-radius:4px;"><?php echo esc_html($cat); ?>类: <?php echo esc_html($count); ?>家</div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php endif; ?>
</div>

<script>
function lk3CopyPrompt(id) {
    var el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(function(){ alert('已复制到剪贴板'); });
}

function lk3BookTypeChange(type) {
    var titleEl = document.getElementById('lk3-book-title');
    if (!titleEl) return;
    // 根据类型调整placeholder
    var placeholders = {book:'书名', thesis:'论文标题', script:'剧本名', manual:'手册名', textbook:'教材名', whitepaper:'白皮书标题'};
    titleEl.placeholder = placeholders[type] || '标题';
}

function lk3GenPromptS4() {
    var title = document.getElementById('lk3-book-title').value || '写书式学习';
    var section = document.getElementById('lk3-book-s4-section').value || '1.1 小节名';
    var words = document.getElementById('lk3-book-s4-words').value || 3000;
    var examples = document.getElementById('lk3-book-s4-examples').value || '2-3';
    var readers = document.getElementById('lk3-book-s4-readers').value || '所有人群';
    var lang = document.getElementById('lk3-book-s4-lang').value || '中文';
    var thinking = document.getElementById('lk3-book-s4-thinking').value || '第一性原理';
    var type = document.getElementById('lk3-book-type').value || 'book';
    var typeMap = {book:'本书', thesis:'篇论文', script:'部短剧', manual:'本手册', textbook:'本教材', whitepaper:'份白皮书'};
    var typeUnit = typeMap[type] || '本书';
    var typeNameMap = {book:'图书', thesis:'论文', script:'剧本', manual:'手册', textbook:'教材', whitepaper:'白皮书'};
    var typeName = typeNameMap[type] || '图书';

    var prompt = '开始完善{《' + title + '》}这{' + typeUnit + '}的小节,全文符合{' + typeName + '}{' + lang + '}语言表述习惯，用{' + readers + '}能听懂的方式，采用{' + thinking + '}深入系统详细完善扩写{' + section + '},生成{' + words + '}字更加丰富的正文内容,依据内容需要,给出适当{' + examples + '}个例子,不输出总结和解释';
    var el = document.getElementById('lk3-book-step4_expand-p1');
    if (el) el.value = prompt;
}

// 初始化
lk3GenPromptS4();
</script>

</div><!-- /手动模式 linked3-eco-card -->
</details><!-- /手动模式 details -->

