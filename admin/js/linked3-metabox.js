/**
 * Linked3 Post Metabox JS
 * Extracted from: src/Classes/Admin/PostMetabox.php
 * Loaded on post-edit screens via wp_enqueue_script
 * Localized data: linked3_metabox (ajax_url, post_id)
 */
(function() {
    'use strict';

    var nonce = document.getElementById('linked3_metabox_nonce').value;
    var ajaxUrl = (window.linked3_metabox && window.linked3_metabox.ajax_url) || '';
    var postId = (window.linked3_metabox && window.linked3_metabox.post_id) || 0;

    function getCurrentContent() {
        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            return tinymce.get('content').getContent();
        }
        var ta = document.getElementById('content');
        return ta ? ta.value : '';
    }
    function getCurrentTitle() {
        var t = document.getElementById('title');
        return t ? t.value : '';
    }
    function setResult(html) {
        var r = document.getElementById('linked3-mb-result');
        r.innerHTML = html;
    }

    // 文章级 AI 操作
    document.querySelectorAll('.linked3-mb-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var action = btn.dataset.action;
            var fd = new FormData();
            fd.append('action', 'linked3_metabox_ai');
            fd.append('nonce', nonce);
            fd.append('sub_action', action);
            fd.append('post_id', postId);
            fd.append('title', getCurrentTitle());
            fd.append('content', getCurrentContent());
            setResult('生成中...');
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){
                    if (res.success) {
                        var html = '';
                        var title = document.getElementById('title');
                        if (res.data.title && title) title.value = res.data.title;
                        if (res.data.excerpt) {
                            var ex = document.getElementById('excerpt');
                            if (ex) ex.value = res.data.excerpt;
                        }
                        if (res.data.tags) {
                            var tg = document.getElementById('new-tag-post_tag');
                            if (tg) tg.value = res.data.tags;
                        }
                        if (res.data.image_url) {
                            html += '<p>已设置特色图片</p><img src="' + res.data.image_url + '" style="max-width:100%;" />';
                        }
                        if (res.data.message) html += '<p>' + res.data.message + '</p>';
                        setResult(html || '完成');
                    } else {
                        setResult(res.data && res.data.message ? res.data.message : '错误');
                    }
                }).catch(function(e){ setResult('网络错误: ' + e.message); });
        });
    });

    // 文本操作 (aipower 风格 v2.8.0)
    document.querySelectorAll('.linked3-mb-text').forEach(function(btn){
        btn.addEventListener('click', function(){
            var action = btn.dataset.action;
            var content = getCurrentContent();
            if (!content || content.length < 10) {
                alert('请先在编辑器里输入内容');
                return;
            }
            var selected = '';
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                var ed = tinymce.get('content');
                selected = ed.selection.getContent({format: 'text'});
            }
            var textToProcess = selected || content;
            var fd = new FormData();
            fd.append('action', 'linked3_metabox_process_text');
            fd.append('nonce', nonce);
            fd.append('process_action', action);
            fd.append('text', textToProcess);
            setResult('AI 处理中...');
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){
                    if (res.success) {
                        var html = '<p style="color:#080;font-weight:600;">✓ 处理完成</p>';
                        html += '<div style="background:#f9fafb;border:1px solid #e5e7eb;padding:8px;margin-top:6px;border-radius:4px;max-height:200px;overflow-y:auto;font-size:11px;">' +
                            String(res.data.result).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') + '</div>';
                        html += '<p style="margin-top:6px;"><button type="button" class="button button-small linked3-mb-insert">插入到编辑器</button></p>';
                        setResult(html);
                        var insBtn = document.querySelector('.linked3-mb-insert');
                        if (insBtn) {
                            insBtn.addEventListener('click', function(){
                                var insertText = res.data.result;
                                if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                                    var ed2 = tinymce.get('content');
                                    ed2.execCommand('mceInsertContent', false, '<p>' + insertText.replace(/\n/g, '</p><p>') + '</p>');
                                } else {
                                    var ta = document.getElementById('content');
                                    if (ta) ta.value += '\n\n' + insertText;
                                }
                                setResult('已插入到编辑器');
                            });
                        }
                    } else {
                        setResult(res.data && res.data.message ? res.data.message : '错误');
                    }
                }).catch(function(e){ setResult('网络错误: ' + e.message); });
        });
    });
})();
