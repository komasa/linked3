<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('linked3_collect');
$ajax_url = admin_url('admin-ajax.php');
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">采集与改写</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard')); ?>" class="button">← 返回总览</a>
    </div>
    <div class="notice notice-info inline"><p><strong>功能说明:</strong>输入 URL 采集内容 → AI 改写(伪原创)→ 保存为草稿或直接发布。批量改写支持 SSE 流式进度。</p></div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div>
            <h2>单个 URL 采集</h2>
            <p><input type="url" id="linked3-col-url" class="regular-text" placeholder="https://example.com/article" /></p>
            <p><button class="button" id="linked3-col-scrape">采集</button></p>
            <h3>采集内容</h3>
            <textarea id="linked3-col-original" rows="12" class="large-text" style="width:100%;"></textarea>

            <h3>改写选项</h3>
            <p>
                <label>语气:
                    <select id="linked3-col-tone">
                        <option value="professional">专业</option>
                        <option value="casual">随意</option>
                        <option value="academic">学术</option>
                        <option value="persuasive">说服</option>
                        <option value="custom">自定义提示词</option>
                    </select>
                </label>
                <label style="margin-left:15px;">复杂度:
                    <select id="linked3-col-complexity">
                        <option value="beginner">入门</option>
                        <option value="intermediate" selected>中级</option>
                        <option value="expert">专家</option>
                    </select>
                </label>
            </p>
            <p>
                <label><input type="checkbox" id="linked3-col-seo" checked /> SEO 优化</label>
                <label style="margin-left:15px;"><input type="checkbox" id="linked3-col-simplify" /> 简化语言</label>
                <label style="margin-left:15px;">发布状态:
                    <select id="linked3-col-rewrite-status">
                        <option value="">仅显示(不保存)</option>
                        <option value="draft">保存为草稿</option>
                        <option value="publish">直接发布</option>
                    </select>
                </label>
            </p>
            <div id="linked3-col-custom-prompt-box" style="display:none;">
                <p><label>自定义改写提示词:<br>
                    <textarea id="linked3-col-custom-prompt" rows="4" cols="60" class="large-text" style="width:100%;" placeholder="例如:请将以下内容改写为科技博客风格,加入代码示例,保持技术准确性。保留原文的核心观点,但用不同的表达方式。"></textarea>
                </label></p>
                <p class="description">用 {content} 代表原文内容。留空则用默认改写逻辑。</p>
            </div>

            <!-- v3.2.0: 查看默认改写 prompt -->
            <details style="margin:10px 0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:10px;">
                <summary style="cursor:pointer;font-weight:600;color:#666;">📝 查看默认改写 Prompt (点击展开)</summary>
                <div style="margin-top:10px;">
                    <p style="font-size:12px;color:#666;">以下是默认改写 System Prompt (语气=专业, 复杂度=中级, SEO=开, 简化=关)。选择不同选项会动态调整。</p>
                    <pre style="white-space:pre-wrap;background:#fff;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:11px;">您是专业的文章改写器,改写用户提供的文章使其原创(通过查重),同时保留所有事实和含义。

语气:{tone}。 复杂度:{complexity}。

[SEO 优化:自然包含相关关键词,使用 H2/H3 小标题。]
[简化复杂句子,目标 8 年级阅读水平。]

仅输出 Markdown,不要前言。

User Prompt:
Rewrite the following article:

{original}</pre>
                    <p style="font-size:11px;color:#666;">{tone} = 语气选项 (专业/随意/学术/说服)<br>{complexity} = 复杂度选项 (入门/中级/专家)<br>{original} = 采集的原文内容<br>[...] 方括号段落是可选的,根据开关动态拼接</p>
                </div>
            </details>
            <p><button class="button button-primary" id="linked3-col-rewrite">AI 改写</button></p>
        </div>
        <div>
            <h2>批量改写 (SSE 流式进度)</h2>
            <p>每行一个 URL,最多 20 个。逐条采集+改写,实时显示进度。</p>
            <p><textarea id="linked3-col-bulk-urls" rows="6" class="large-text" style="width:100%;" placeholder="每行一个 URL (最多 20 个)"></textarea></p>
            <p>
                <label>发布状态:
                    <select id="linked3-col-status">
                        <option value="draft">草稿(推荐,人工审核后发布)</option>
                        <option value="publish">直接发布</option>
                    </select>
                </label>
                <button class="button" id="linked3-col-bulk">开始批量</button>
            </p>
            <div id="linked3-col-bulk-log" style="background:#fff;border:1px solid #ddd;padding:10px;height:300px;overflow:auto;font-family:monospace;font-size:12px;"></div>
        </div>
    </div>

    <h2>改写结果</h2>
    <div id="linked3-col-output" style="background:#fff;border:1px solid #ddd;padding:12px;min-height:200px;">
        <p style="color:#999;">改写后的内容将显示在此。</p>
    </div>

    <script>
    (function(){
        var nonce=<?php echo wp_json_encode($nonce);?>, ajaxUrl=<?php echo wp_json_encode($ajax_url);?>;
        function post(action,data,cb){
            var fd=new FormData();fd.append('action',action);fd.append('nonce',nonce);
            Object.keys(data).forEach(function(k){fd.append(k,data[k]);});
            fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb);
        }
        document.getElementById('linked3-col-scrape').addEventListener('click',function(){
            var url=document.getElementById('linked3-col-url').value;
            post('linked3_collect_scrape',{url:url},function(res){
                if(res.success){
                    document.getElementById('linked3-col-original').value=res.data.title+'\n\n'+res.data.content;
                }else{alert(res.data.message||'错误');}
            });
        });
        // 自定义提示词显示/隐藏
        document.getElementById('linked3-col-tone').addEventListener('change',function(){
            document.getElementById('linked3-col-custom-prompt-box').style.display = this.value === 'custom' ? 'block' : 'none';
        });

        document.getElementById('linked3-col-rewrite').addEventListener('click',function(){
            var content=document.getElementById('linked3-col-original').value;
            post('linked3_collect_rewrite',{
                content:content,
                tone:document.getElementById('linked3-col-tone').value,
                complexity:document.getElementById('linked3-col-complexity').value,
                seo_focus:document.getElementById('linked3-col-seo').checked?1:0,
                simplify:document.getElementById('linked3-col-simplify').checked?1:0,
                custom_prompt:document.getElementById('linked3-col-custom-prompt').value,
                post_status:document.getElementById('linked3-col-rewrite-status').value
            },function(res){
                if(res.success){
                    var pre=document.createElement('pre');pre.style.whiteSpace='pre-wrap';pre.textContent=res.data.content;
                    document.getElementById('linked3-col-output').innerHTML='';document.getElementById('linked3-col-output').appendChild(pre);
                }else{alert(res.data.message||'错误');}
            });
        });
        document.getElementById('linked3-col-bulk').addEventListener('click',function(){
            var urls=document.getElementById('linked3-col-bulk-urls').value.split('\n').map(function(s){return s.trim();}).filter(Boolean);
            if(!urls.length)return;
            var fd=new FormData();fd.append('action','linked3_collect_bulk_rewrite');fd.append('nonce',nonce);
            urls.forEach(function(u){fd.append('urls[]',u);});
            fd.append('post_status',document.getElementById('linked3-col-status').value);
            fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){
                var reader=r.body.getReader();var dec=new TextDecoder();var buf='';
                function pump() {reader.read().then(function(x){
                    if(x.done)return;
                    buf+=dec.decode(x.value,{stream:true});
                    var parts=buf.split('\n\n');buf=parts.pop();
                    parts.forEach(function(p){
                        var m=p.match(/event:\s*(\w+)\ndata:\s*(.*)/s);
                        if(m){try{var d=JSON.parse(m[2]);log(d,m[1]);}catch(e){}}
                    });
                    pump();
                });}
                pump();
            });
            var logEl=document.getElementById('linked3-col-bulk-log');
            function log(d,ev) {
                var div=document.createElement('div');
                div.textContent='['+ev+'] '+(d.url||'')+': '+(d.ok?'✓':'✗')+' '+(d.message||'');
                div.style.color=d.ok?'#080':'#800';
                logEl.appendChild(div);logEl.scrollTop=logEl.scrollHeight;
            }
        });
    })();
    </script>
</div>
