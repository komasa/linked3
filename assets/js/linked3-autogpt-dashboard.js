/**
 * linked3-autogpt-dashboard.js
 * Extracted from: admin/views/autogpt/dashboard.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-autogpt-dashboard.js
 * Localized via wp_localize_script('linked3-autogpt', 'linked3_autogpt', {...})
 *   Keys: nonce, ajax_url, val
 */

(function(){
    var nonce = window.linked3_autogpt && window.linked3_autogpt.nonce || '';
    var ajax_url = window.linked3_autogpt && window.linked3_autogpt.ajax_url || '';
    var val = window.linked3_autogpt && window.linked3_autogpt.val || '';


    (function(){
        var nonce=linked3_autogpt.nonce, ajaxUrl=linked3_autogpt.ajax_url;
        function post(action,data,cb){
            var fd=new FormData();fd.append('action',action);fd.append('nonce',nonce);
            Object.keys(data).forEach(function(k){fd.append(k,data[k]);});
            fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb).catch(function(e){cb({success:false,data:{message:e.message}});});
        }
        document.getElementById('linked3-ag-new').addEventListener('click',function(){document.getElementById('linked3-ag-dialog').style.display='block';});
        document.getElementById('linked3-ag-cancel').addEventListener('click',function(){document.getElementById('linked3-ag-dialog').style.display='none';});

        // v3.2.0: 类型切换显示对应配置区
        document.getElementById('linked3-ag-type').addEventListener('change',function(){
            var type=this.value;
            document.getElementById('linked3-ag-cfg-writing').style.display=(type==='content-writing')?'block':'none';
            document.getElementById('linked3-ag-cfg-collect').style.display=(type==='collect-rewrite')?'block':'none';
        });

        document.getElementById('linked3-ag-save').addEventListener('click',function(){
            var cfg={};
            var type=document.getElementById('linked3-ag-type').value;
            if(type==='content-writing'){
                cfg.keyword=document.getElementById('linked3-ag-keyword').value;
                cfg.count_per_run=document.getElementById('linked3-ag-count').value;
                cfg.publish_directly=document.getElementById('linked3-ag-publish-directly').checked?1:0;
                cfg.inject_images=document.getElementById('linked3-ag-inject-images').checked?1:0;
                cfg.publish_target_id=document.getElementById('linked3-ag-publish-target').value;
                // v3.0.0: 收集勾选的分发平台
                var platforms=[];
                document.querySelectorAll('.linked3-ag-platform:checked').forEach(function(cb){platforms.push(cb.value);});
                cfg.distribute_platforms=platforms;
            } else if(type==='collect-rewrite'){
                cfg.urls=document.getElementById('linked3-ag-urls').value;
                cfg.tone=document.getElementById('linked3-ag-tone').value;
                cfg.complexity=document.getElementById('linked3-ag-complexity').value;
                cfg.seo_focus=document.getElementById('linked3-ag-seo').checked?1:0;
                cfg.simplify=document.getElementById('linked3-ag-simplify').checked?1:0;
                cfg.publish_directly=document.getElementById('linked3-ag-publish-directly2').checked?1:0;
            }
            // v3.0.0: smart schedule
            cfg.publish_time_window=document.getElementById('linked3-ag-time-window').value;
            cfg.publish_at_specific_time=document.getElementById('linked3-ag-specific-time').value;

            post('linked3_autogpt_create_task',{
                name:document.getElementById('linked3-ag-name').value,
                task_type:type,
                schedule:document.getElementById('linked3-ag-schedule').value,
                config:JSON.stringify(cfg)
            },function(res){if(res.success){location.reload();}else{alert(res.data.message||'Error');}});
        });
        document.querySelectorAll('.linked3-ag-pause').forEach(function(b){b.addEventListener('click',function(){
            post('linked3_autogpt_toggle_task',{id:b.closest('tr').dataset.id,status:'paused'},function(res){if(res.success)location.reload();});
        });});
        document.querySelectorAll('.linked3-ag-resume').forEach(function(b){b.addEventListener('click',function(){
            post('linked3_autogpt_toggle_task',{id:b.closest('tr').dataset.id,status:'active'},function(res){if(res.success)location.reload();});
        });});
        document.querySelectorAll('.linked3-ag-del').forEach(function(b){b.addEventListener('click',function(){
            if(!confirm('linked3_autogpt.val'))return;
            post('linked3_autogpt_delete_task',{id:b.closest('tr').dataset.id},function(res){if(res.success)location.reload();});
        });});
    })();
    
})();
