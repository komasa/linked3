/**
 * linked3-tab-create-center.js
 * Extracted from: admin/views/dashboard/partials/tab-create-center.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-create-center.js
 * Localized via wp_localize_script('linked3-tab-create-center', 'linked3_tab_cc', {...})
 *   Keys: nonce_content_writer
 */

(function(){
    var nonce_content_writer = window.linked3_tab_cc && window.linked3_tab_cc.nonce_content_writer || '';


function l3_switch() {
    var t=document.querySelector('input[name="l3_content_type"]:checked').value;
    var showScript=(t==='comic'||t==='diagram'||t==='video'||t==='xhs');
    var showPlatform=(t==='comic'||t==='video');
    var showCount=(t==='article'||t==='comic'||t==='diagram'||t==='video'||t==='xhs');
    var showStructure=(t==='diagram');
    var showConfig=(t==='article'||t==='diagram'||t==='xhs'||t==='book');
    var showTopic=(t!=='book'); // book uses script input
    document.getElementById('l3-script-row').style.display=showScript?'':'none';
    document.getElementById('l3-platform-row').style.display=showPlatform?'':'none';
    document.getElementById('l3-topic-row').style.display=showTopic?'':'none';
    document.getElementById('l3-count-row').style.display=showCount?'':'none';
    document.getElementById('l3-structure-row').style.display=showStructure?'':'none';
    document.getElementById('l3-config-row').style.display=showConfig?'':'none';
    
    // Update count label and hints
    var labels={article:['字数','建议 800-3000 字'],comic:['分镜数量','5-15 镜'],diagram:['镜数量','1镜=1张完整信息图'],video:['视频组数','每组5-10秒'],xhs:['页数','3-8页小红书笔记'],book:['章节数','5-20章整书输出']};
    var hints={article:'建议 800-3000 字',comic:'5-15 镜',diagram:'1镜=1张完整信息图(8种结构可选)',video:'每组5-10秒',xhs:'3-8页小红书笔记',book:'5-20章整书输出'};
    document.getElementById('l3-count-label').textContent=labels[t][0];
    document.getElementById('l3-count-hint').textContent=hints[t]||'';
    
    // Update count defaults
    var defaults={article:1200,comic:8,diagram:1,video:5,xhs:5,book:10};
    document.getElementById('l3_count').value=defaults[t]||8;
    document.getElementById('l3_count').min=t==='diagram'?1:3;
    document.getElementById('l3_count').max=t==='article'?10000:(t==='book'?50:15);
}

function l3_generate(){
    var btn=document.getElementById('l3_generate_btn');
    btn.disabled=true;btn.textContent='⏳ 生成中...';
    document.getElementById('l3_progress').textContent='正在处理...';
    
    var fd=new FormData();
    fd.append('action','linked3_content_generate');
    fd.append('nonce','linked3_tab_cc.nonce_content_writer');
    fd.append('content_type',document.querySelector('input[name="l3_content_type"]:checked').value);
    fd.append('topic',document.getElementById('l3_topic').value);
    fd.append('script',document.getElementById('l3_script').value);
    fd.append('style',document.getElementById('l3_style').value);
    fd.append('platform',document.getElementById('l3_platform').value);
    // v27.17.9-fix1: 发送结构选择和生成配置到后端
    var structSel=document.getElementById('l3_structure');
    if(structSel) fd.append('structure',structSel.value);
    fd.append('options',JSON.stringify({
        word_count:document.getElementById('l3_count').value,
        panel_count:document.getElementById('l3_count').value,
        video_groups:document.getElementById('l3_count').value,
        page_count:document.getElementById('l3_count').value,
        chapter_count:document.getElementById('l3_count').value,
        cfg_composite:document.getElementById('l3_cfg_composite')?document.getElementById('l3_cfg_composite').checked:false,
        cfg_cos:document.getElementById('l3_cfg_cos')?document.getElementById('l3_cfg_cos').checked:false,
        cfg_seo:document.getElementById('l3_cfg_seo')?document.getElementById('l3_cfg_seo').checked:false,
        cfg_risk:document.getElementById('l3_cfg_risk')?document.getElementById('l3_cfg_risk').checked:false
    }));
    
    fetch(ajaxurl,{method:'POST',body:fd})
    .then(function(r){return r.json()})
    .then(function(r){
        btn.disabled=false;btn.textContent='⚡ 生成';
        document.getElementById('l3_progress').textContent='';
        var d=document.getElementById('l3_result');
        if(r.success){
            var h='<div class="notice notice-success inline"><p>✅ 生成成功</p></div>';
            var data=r.data;
            if(data.edit_url)h+='<p><a href="'+data.edit_url+'" class="button button-primary">编辑文章 →</a></p>';
            if(data.panels)h+='<p>共生成 '+data.total_panels+' 个分镜</p>';
            if(data.diagram)h+='<p>知识图谱已生成</p>';
            d.innerHTML=h;
        }else{
            d.innerHTML='<div class="notice notice-error inline"><p>'+(r.data.message||'生成失败')+'</p></div>';
        }
    })
    .catch(function(e){
        btn.disabled=false;btn.textContent='⚡ 生成';
        document.getElementById('l3_progress').textContent='';
        document.getElementById('l3_result').innerHTML='<div class="notice notice-error inline"><p>网络错误: '+e+'</p></div>';
    });
}
l3_switch();

})();
