/**
 * linked3-tab-writing-center.js
 * Extracted from: admin/views/dashboard/partials/tab-writing-center.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-writing-center.js
 * Localized via wp_localize_script('linked3-tab-writing-center', 'linked3_tab_wc', {...})
 *   Keys: nonce_content_writer
 */

(function(){
    var nonce_content_writer = window.linked3_tab_wc && window.linked3_tab_wc.nonce_content_writer || '';


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
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({action:'linked3_keyword_fetch_hot',nonce:'linked3_tab_wc.nonce_content_writer',seed:document.getElementById('l3_wt_topic').value,source:'auto'})})
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
        nonce:'linked3_tab_wc.nonce_content_writer',
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
        nonce:'linked3_tab_wc.nonce_content_writer',
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
        nonce:'linked3_tab_wc.nonce_content_writer',
        content:content,topic:topic
    })})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.success){document.getElementById('l3_wt_seo_title').value=d.data.title||topic;}
    }).catch(function(){});
    
    // Generate meta description
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({
        action:'linked3_generate_meta',
        nonce:'linked3_tab_wc.nonce_content_writer',
        content:content,topic:topic
    })})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.success){document.getElementById('l3_wt_meta_desc').value=d.data.meta_description||d.data.meta||'';}
    }).catch(function(){});
    
    // Generate tags
    fetch(ajaxurl,{method:'POST',body:new URLSearchParams({
        action:'linked3_generate_tags',
        nonce:'linked3_tab_wc.nonce_content_writer',
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
        nonce:'linked3_tab_wc.nonce_content_writer',
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

})();
