/**
 * linked3-publish-targets.js
 * Extracted from: admin/views/publish/targets.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-publish-targets.js
 * Localized via wp_localize_script('linked3-publish-targets', 'linked3_publish_targets', {...})
 *   Keys: nonce, ajax_url, val, val
 */

(function(){
    var nonce = window.linked3_publish_targets && window.linked3_publish_targets.nonce || '';
    var ajax_url = window.linked3_publish_targets && window.linked3_publish_targets.ajax_url || '';
    var val = window.linked3_publish_targets && window.linked3_publish_targets.val || '';
    var val = window.linked3_publish_targets && window.linked3_publish_targets.val || '';


    (function(){
        var nonce=linked3_publish_targets.nonce, ajaxUrl=linked3_publish_targets.ajax_url;
        function post(action,data,cb){
            var fd=new FormData();fd.append('action',action);fd.append('nonce',nonce);
            Object.keys(data).forEach(function(k){fd.append(k,data[k]);});
            fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb);
        }
        function showFields(type) {
            document.querySelectorAll('.linked3-pub-fields').forEach(function(el){el.style.display='none';});
            var f=document.getElementById('linked3-pub-fields-'+type);if(f)f.style.display='block';
        }
        document.getElementById('linked3-pub-type').addEventListener('change',function(){showFields(this.value);});
        document.getElementById('linked3-pub-new').addEventListener('click',function(){
            document.getElementById('linked3-pub-dialog').style.display='block';
            document.getElementById('linked3-pub-dialog-title').textContent='linked3_publish_targets.val';
            document.getElementById('linked3-pub-id').value='';
            ['name','site_url','username','app_password','db_host','db_user','db_password','db_name','table_prefix','webhook_url','webhook_secret'].forEach(function(id){document.getElementById('linked3-pub-'+id).value='';});
            document.getElementById('linked3-pub-type').value='local';showFields('local');
            document.getElementById('linked3-pub-table_prefix').value='wp_';
            document.getElementById('linked3-pub-is_default').checked=false;
        });
        document.getElementById('linked3-pub-cancel').addEventListener('click',function(){document.getElementById('linked3-pub-dialog').style.display='none';});
        document.getElementById('linked3-pub-save').addEventListener('click',function(){
            var type=document.getElementById('linked3-pub-type').value;
            var data={id:document.getElementById('linked3-pub-id').value,name:document.getElementById('linked3-pub-name').value,type:type,is_default:document.getElementById('linked3-pub-is_default').checked?1:0};
            if(type==='remote_wp'){data.site_url=document.getElementById('linked3-pub-site_url').value;data.username=document.getElementById('linked3-pub-username').value;data.app_password=document.getElementById('linked3-pub-app_password').value;}
            if(type==='remote_db'){data.db_host=document.getElementById('linked3-pub-db_host').value;data.db_user=document.getElementById('linked3-pub-db_user').value;data.db_password=document.getElementById('linked3-pub-db_password').value;data.db_name=document.getElementById('linked3-pub-db_name').value;data.table_prefix=document.getElementById('linked3-pub-table_prefix').value;}
            if(type==='custom_api'){data.webhook_url=document.getElementById('linked3-pub-webhook_url').value;data.webhook_secret=document.getElementById('linked3-pub-webhook_secret').value;}
            post('linked3_publish_save_target',data,function(res){if(res.success){location.reload();}else{alert(res.data.message||'Error');}});
        });
        document.querySelectorAll('.linked3-pub-test').forEach(function(btn){
            btn.addEventListener('click',function(){
                var id=btn.closest('tr').dataset.id;
                post('linked3_publish_test_target',{id:id},function(res){alert(res.success?(res.data.message||'OK'):(res.data.message||'Fail'));});
            });
        });
        document.querySelectorAll('.linked3-pub-del').forEach(function(btn){
            btn.addEventListener('click',function(){
                if(!confirm('linked3_publish_targets.val'))return;
                var id=btn.closest('tr').dataset.id;
                post('linked3_publish_delete_target',{id:id},function(res){if(res.success)location.reload();});
            });
        });
    })();
    
})();
