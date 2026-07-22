<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap linked3-writing-center">
    <h2>✍️ 写作中心</h2>
    <p class="description">从选题到发布 · 五步写出高质量文章</p>

    <!-- Progress indicator -->
    <div class="l3-progress-bar" style="display:flex;margin-bottom:20px;border-radius:4px;overflow:hidden;">
        <div class="l3-step active" data-step="1" style="flex:1;padding:10px;text-align:center;background:#2271b1;color:#fff;cursor:pointer;font-size:13px;">① 选题</div>
        <div class="l3-step" data-step="2" style="flex:1;padding:10px;text-align:center;background:#ddd;cursor:pointer;font-size:13px;">② 大纲</div>
        <div class="l3-step" data-step="3" style="flex:1;padding:10px;text-align:center;background:#ddd;cursor:pointer;font-size:13px;">③ 正文</div>
        <div class="l3-step" data-step="4" style="flex:1;padding:10px;text-align:center;background:#ddd;cursor:pointer;font-size:13px;">④ 优化</div>
        <div class="l3-step" data-step="5" style="flex:1;padding:10px;text-align:center;background:#ddd;cursor:pointer;font-size:13px;">⑤ 发布</div>
    </div>

    <!-- Step 1: Topic Selection -->
    <div class="l3-panel" id="l3-step-1">
        <h3>① 选题 · 确定写什么</h3>
        <table class="form-table">
            <tr>
                <th><label>关键词 / 主题</label></th>
                <td><input type="text" id="l3_wt_topic" class="large-text" placeholder="输入关键词或主题" /></td>
            </tr>
            <tr>
                <th><label>热词采集</label></th>
                <td>
                    <button type="button" class="button" onclick="l3_fetch_hotwords()">🔥 采集热词</button>
                    <span id="l3_hotwords_result" style="margin-left:10px;"></span>
                </td>
            </tr>
            <tr>
                <th><label>写作风格</label></th>
                <td>
                    <select id="l3_wt_style">
                        <option value="professional">专业严谨</option>
                        <option value="casual">轻松口语</option>
                        <option value="academic">学术规范</option>
                        <option value="marketing">营销转化</option>
                        <option value="storytelling">故事叙事</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>字数</label></th>
                <td>
                    <input type="number" id="l3_wt_wordcount" value="1200" min="300" max="5000" step="100" />
                    <span class="description">建议 800-2000 字</span>
                </td>
            </tr>
            <tr>
                <th><label>🧠 思维杠杆</label></th>
                <td>
                    <select id="l3_wt_lever">
                        <option value="">不使用杠杆</option>
                        <option value="universal_trio">万能思维新三法 (本质×反向×系统)</option>
                        <option value="creative_engine">创意生成引擎 (创造×类比×跨界)</option>
                        <option value="quality_gauntlet">质量绞杀阵 (批判×压测×校准)</option>
                        <option value="meta_socratic">苏格拉底追问</option>
                        <option value="meta_essence">本质追问</option>
                    </select>
                    <p class="description">应用思维杠杆提升文章深度 — 杠杆会在生成时自动注入AI提示词</p>
                </td>
            </tr>
        </table>
        <p><button type="button" class="button button-primary" onclick="l3_generate_outline()">→ 生成大纲</button></p>
    </div>

    <!-- Step 2: Outline -->
    <div class="l3-panel" id="l3-step-2" style="display:none;">
        <h3>② 大纲 · 结构设计</h3>
        <textarea id="l3_wt_outline" rows="10" class="large-text" placeholder="大纲将在此显示, 可编辑修改"></textarea>
        <p>
            <button type="button" class="button" onclick="l3_show_step(1)">← 返回选题</button>
            <button type="button" class="button button-primary" onclick="l3_generate_content()">→ 生成正文</button>
        </p>
    </div>

    <!-- Step 3: Content -->
    <div class="l3-panel" id="l3-step-3" style="display:none;">
        <h3>③ 正文 · AI 写作</h3>
        <div id="l3_wt_progress" style="margin-bottom:10px;"></div>
        <textarea id="l3_wt_content" rows="20" class="large-text" placeholder="正文将在此显示, 可编辑修改"></textarea>
        <p>
            <button type="button" class="button" onclick="l3_show_step(2)">← 返回大纲</button>
            <button type="button" class="button button-primary" onclick="l3_optimize_seo()">→ SEO 优化</button>
        </p>
    </div>

    <!-- Step 4: SEO Optimization -->
    <div class="l3-panel" id="l3-step-4" style="display:none;">
        <h3>④ 优化 · SEO + 质量评分</h3>
        <table class="form-table">
            <tr>
                <th><label>SEO 标题</label></th>
                <td><input type="text" id="l3_wt_seo_title" class="large-text" /></td>
            </tr>
            <tr>
                <th><label>Meta 描述</label></th>
                <td><textarea id="l3_wt_meta_desc" rows="3" class="large-text"></textarea></td>
            </tr>
            <tr>
                <th><label>标签</label></th>
                <td><input type="text" id="l3_wt_tags" class="large-text" placeholder="逗号分隔" /></td>
            </tr>
            <tr>
                <th><label>质量评分</label></th>
                <td id="l3_wt_quality"></td>
            </tr>
        </table>
        <p>
            <button type="button" class="button" onclick="l3_show_step(3)">← 返回正文</button>
            <button type="button" class="button button-primary" onclick="l3_publish()">→ 发布</button>
        </p>
    </div>

    <!-- Step 5: Publish -->
    <div class="l3-panel" id="l3-step-5" style="display:none;">
        <h3>⑤ 发布</h3>
        <table class="form-table">
            <tr>
                <th><label>发布状态</label></th>
                <td>
                    <select id="l3_wt_status">
                        <option value="draft">草稿</option>
                        <option value="publish">立即发布</option>
                        <option value="pending">待审</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>分类</label></th>
                <td>
                    <?php wp_dropdown_categories(['hide_empty' => 0, 'name' => 'l3_wt_category', 'show_option_none' => '选择分类']); ?>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" class="button" onclick="l3_show_step(4)">← 返回优化</button>
            <button type="button" class="button button-primary button-large" onclick="l3_do_publish()">⚡ 发布文章</button>
        </p>
        <div id="l3_publish_result"></div>
    </div>
</div>

<script>
function l3_show_step(n) {
    for(var i=1;i<=5;i++){
        document.getElementById('l3-step-'+i).style.display=(i===n)?'':'none';
        var step=document.querySelector('.l3-step[data-step="'+i+'"]');
        if(step){step.style.background=(i<=n)?'#2271b1':'#ddd';step.style.color=(i<=n)?'#fff':'#333';}
    }
}

function l3_fetch_hotwords(){
    var r=document.getElementById('l3_hotwords_result');
    r.innerHTML='⏳ 采集中...';
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({action:'linked3_keyword_fetch_hot',nonce:'<?php echo wp_create_nonce("linked3_content_writer"); ?>',seed:document.getElementById('l3_wt_topic').value,source:'auto'})})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.success&&d.data.keywords){
            r.innerHTML='✅ '+d.data.count+'个热词';
            var kw=d.data.keywords.slice(0,5).join(', ');
            r.innerHTML+=': '+kw;
            if(!document.getElementById('l3_wt_topic').value&&d.data.keywords[0]){
                document.getElementById('l3_wt_topic').value=d.data.keywords[0];
            }
        }else{r.innerHTML='❌ 采集失败';}
    }).catch(function(){r.innerHTML='❌ 网络错误';});
}

function l3_generate_outline(){
    var topic=document.getElementById('l3_wt_topic').value;
    if(!topic){alert('请输入主题');return;}
    l3_show_step(2);
    document.getElementById('l3_wt_outline').value='⏳ 正在生成大纲...';
    
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({
        action:'linked3_generate_outline',
        nonce:'<?php echo wp_create_nonce("linked3_content_writer"); ?>',
        topic:topic,
        style:document.getElementById('l3_wt_style').value,
        word_count:document.getElementById('l3_wt_wordcount').value,
        lever:document.getElementById('l3_wt_lever').value
    })})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.success){document.getElementById('l3_wt_outline').value=d.data.outline||d.data.content||'大纲生成失败';}
        else{document.getElementById('l3_wt_outline').value='错误: '+(d.data.message||'未知');}
    }).catch(function(e){document.getElementById('l3_wt_outline').value='网络错误: '+e;});
}

function l3_generate_content(){
    l3_show_step(3);
    var p=document.getElementById('l3_wt_progress');
    p.innerHTML='⏳ AI 正在写作...';
    document.getElementById('l3_wt_content').value='';
    
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({
        action:'linked3_generate_content',
        nonce:'<?php echo wp_create_nonce("linked3_content_writer"); ?>',
        topic:document.getElementById('l3_wt_topic').value,
        outline:document.getElementById('l3_wt_outline').value,
        style:document.getElementById('l3_wt_style').value,
        word_count:document.getElementById('l3_wt_wordcount').value,
        lever:document.getElementById('l3_wt_lever').value
    })})
    .then(function(r){return r.json()})
    .then(function(d){
        p.innerHTML='';
        if(d.success){document.getElementById('l3_wt_content').value=d.data.content||d.data.html||'内容生成失败';}
        else{document.getElementById('l3_wt_content').value='错误: '+(d.data.message||'未知');}
    }).catch(function(e){p.innerHTML='❌ 网络错误';});
}

function l3_optimize_seo(){
    l3_show_step(4);
    var content=document.getElementById('l3_wt_content').value;
    var topic=document.getElementById('l3_wt_topic').value;
    
    // Generate SEO title
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({
        action:'linked3_generate_title',
        nonce:'<?php echo wp_create_nonce("linked3_content_writer"); ?>',
        content:content,topic:topic
    })})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.success){document.getElementById('l3_wt_seo_title').value=d.data.title||topic;}
    }).catch(function(){});
    
    // Generate meta description
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({
        action:'linked3_generate_meta',
        nonce:'<?php echo wp_create_nonce("linked3_content_writer"); ?>',
        content:content,topic:topic
    })})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.success){document.getElementById('l3_wt_meta_desc').value=d.data.meta_description||d.data.meta||'';}
    }).catch(function(){});
    
    // Generate tags
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({
        action:'linked3_generate_tags',
        nonce:'<?php echo wp_create_nonce("linked3_content_writer"); ?>',
        content:content,topic:topic
    })})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.success){document.getElementById('l3_wt_tags').value=(d.data.tags||[]).join(', ');}
    }).catch(function(){});
    
    // Quality score
    var wc=document.getElementById('l3_wt_content').value.length;
    var score=Math.min(100,Math.round(wc/15));
    document.getElementById('l3_wt_quality').innerHTML='<div style="background:#f0f7fc;padding:10px;border-radius:4px;">质量评分: <strong>'+score+'/100</strong> ('+wc+'字) | '+(score>=70?'✅ 可发布':(score>=40?'⚠️ 建议补充':'❌ 内容不足'))+'</div>';
}

function l3_do_publish(){
    var btn=event.target;btn.disabled=true;btn.textContent='⏳ 发布中...';
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({
        action:'linked3_content_generate',
        nonce:'<?php echo wp_create_nonce("linked3_content_writer"); ?>',
        content_type:'article',
        topic:document.getElementById('l3_wt_topic').value,
        style:document.getElementById('l3_wt_style').value,
        options:JSON.stringify({
            word_count:document.getElementById('l3_wt_wordcount').value,
            content:document.getElementById('l3_wt_content').value,
            title:document.getElementById('l3_wt_seo_title').value,
            meta_desc:document.getElementById('l3_wt_meta_desc').value,
            tags:document.getElementById('l3_wt_tags').value,
            status:document.getElementById('l3_wt_status').value,
            category:document.getElementById('l3_wt_category').value
        })
    })})
    .then(function(r){return r.json()})
    .then(function(d){
        btn.disabled=false;btn.textContent='⚡ 发布文章';
        var el=document.getElementById('l3_publish_result');
        if(d.success&&d.data.post_id){
            el.innerHTML='<div class="notice notice-success"><p>✅ 发布成功! <a href="'+(d.data.edit_url||'')+'" class="button">编辑文章 →</a></p></div>';
        }else{
            el.innerHTML='<div class="notice notice-error"><p>'+(d.data.message||'发布失败')+'</p></div>';
        }
    }).catch(function(){btn.disabled=false;btn.textContent='⚡ 发布文章';});
}
</script>
