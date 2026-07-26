/**
 * Linked3 Eco Book JS
 * Extracted from: admin/views/dashboard/partials/eco-book.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-eco-book.js
 * Localized via wp_localize_script('linked3-eco-book', 'linked3_eco_book', {...})
 *   Keys: ajax_url, project_id, factory_nonce, progress_nonce
 */

(function($){
    var ajaxUrl = 'window.linked3_eco_book.ajax_url';
    var factoryNonce = 'window.linked3_eco_book.factory_nonce';
    var currentProjectId = 'window.linked3_eco_book.project_id';
    var progressNonce = 'window.linked3_eco_book.progress_nonce';
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
