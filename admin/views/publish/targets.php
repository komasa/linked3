<?php
if (!defined('ABSPATH')) exit;
/** @var array $targets */
$nonce = wp_create_nonce('linked3_publish');
$ajax_url = admin_url('admin-ajax.php');
$plan = \Linked3\Classes\License\Linked3_License_Service::instance()->plan();
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Targets</h1>
        <a href="admin.php?page=linked3-dashboard" class="button">← 返回总览</a>
    </div>
    <h1><?php echo esc_html__('发布目标', 'linked3'); ?>
        <button class="page-title-action" id="linked3-pub-new"><?php echo esc_html__('添加目标', 'linked3'); ?></button>
    </h1>
    <p><?php echo esc_html(sprintf(__('当前套餐:%s。非本地目标需要 Pro+。', 'linked3'), ucfirst($plan))); ?></p>

    <table class="widefat striped" id="linked3-pub-table">
        <thead><tr>
            <th><?php echo esc_html__('名称', 'linked3'); ?></th>
            <th><?php echo esc_html__('类型', 'linked3'); ?></th>
            <th><?php echo esc_html__('默认?', 'linked3'); ?></th>
            <th><?php echo esc_html__('操作', 'linked3'); ?></th>
        </tr></thead>
        <tbody>
        <?php if (empty($targets)) : ?>
            <tr><td colspan="4"><?php echo esc_html__('暂无目标,点击「添加目标」。', 'linked3'); ?></td></tr>
        <?php else : foreach ($targets as $t) : ?>
            <tr data-id="<?php echo esc_attr($t['id']); ?>">
                <td><?php echo esc_html($t['name']); ?></td>
                <td><code><?php echo esc_html($t['type']); ?></code></td>
                <td><?php echo $t['is_default'] ? '<span class="dashicons dashicons-yes"></span>' : ''; ?></td>
                <td>
                    <button class="button linked3-pub-test"><?php echo esc_html__('测试', 'linked3'); ?></button>
                    <button class="button linked3-pub-edit"><?php echo esc_html__('编辑', 'linked3'); ?></button>
                    <button class="button button-link-delete linked3-pub-del"><?php echo esc_html__('删除', 'linked3'); ?></button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <!-- Edit dialog (simple) -->
    <div id="linked3-pub-dialog" style="display:none;">
        <h3 id="linked3-pub-dialog-title"><?php echo esc_html__('新建目标', 'linked3'); ?></h3>
        <input type="hidden" id="linked3-pub-id" />
        <p><label><?php echo esc_html__('名称', 'linked3'); ?><br><input type="text" id="linked3-pub-name" class="regular-text" /></label></p>
        <p><label><?php echo esc_html__('类型', 'linked3'); ?><br>
            <select id="linked3-pub-type">
                <option value="local"><?php echo esc_html__('本地站点', 'linked3'); ?></option>
                <option value="remote_wp"><?php echo esc_html__('远程 WordPress(REST)', 'linked3'); ?></option>
                <option value="remote_db"><?php echo esc_html__('Remote Database', 'linked3'); ?></option>
                <option value="custom_api"><?php echo esc_html__('自定义 API / Webhook', 'linked3'); ?></option>
            </select>
        </label></p>
        <div id="linked3-pub-fields-remote_wp" class="linked3-pub-fields" style="display:none;">
            <p><label><?php echo esc_html__('站点 URL', 'linked3'); ?><br><input type="url" id="linked3-pub-site_url" class="regular-text" /></label></p>
            <p><label><?php echo esc_html__('Username', 'linked3'); ?><br><input type="text" id="linked3-pub-username" class="regular-text" /></label></p>
            <p><label><?php echo esc_html__('应用密码', 'linked3'); ?><br><input type="password" id="linked3-pub-app_password" class="regular-text" /></label></p>
        </div>
        <div id="linked3-pub-fields-remote_db" class="linked3-pub-fields" style="display:none;">
            <p><label><?php echo esc_html__('数据库主机', 'linked3'); ?><br><input type="text" id="linked3-pub-db_host" class="regular-text" /></label></p>
            <p><label><?php echo esc_html__('数据库用户', 'linked3'); ?><br><input type="text" id="linked3-pub-db_user" class="regular-text" /></label></p>
            <p><label><?php echo esc_html__('数据库密码', 'linked3'); ?><br><input type="password" id="linked3-pub-db_password" class="regular-text" /></label></p>
            <p><label><?php echo esc_html__('数据库名', 'linked3'); ?><br><input type="text" id="linked3-pub-db_name" class="regular-text" /></label></p>
            <p><label><?php echo esc_html__('表前缀', 'linked3'); ?><br><input type="text" id="linked3-pub-table_prefix" class="regular-text" value="wp_" /></label></p>
        </div>
        <div id="linked3-pub-fields-custom_api" class="linked3-pub-fields" style="display:none;">
            <p><label><?php echo esc_html__('Webhook URL', 'linked3'); ?><br><input type="url" id="linked3-pub-webhook_url" class="regular-text" /></label></p>
            <p><label><?php echo esc_html__('Webhook Secret', 'linked3'); ?><br><input type="password" id="linked3-pub-webhook_secret" class="regular-text" /></label></p>
        </div>
        <p><label><input type="checkbox" id="linked3-pub-is_default" /> <?php echo esc_html__('Set as default', 'linked3'); ?></label></p>
        <p>
            <button class="button button-primary" id="linked3-pub-save"><?php echo esc_html__('保存', 'linked3'); ?></button>
            <button class="button" id="linked3-pub-cancel"><?php echo esc_html__('取消', 'linked3'); ?></button>
        </p>
    </div>

    <script>
    (function(){
        var nonce=<?php echo wp_json_encode($nonce);?>, ajaxUrl=<?php echo wp_json_encode($ajax_url);?>;
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
            document.getElementById('linked3-pub-dialog-title').textContent='<?php echo esc_js(__('New Target','linked3'));?>';
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
                if(!confirm('<?php echo esc_js(__('Delete this target?','linked3'));?>'))return;
                var id=btn.closest('tr').dataset.id;
                post('linked3_publish_delete_target',{id:id},function(res){if(res.success)location.reload();});
            });
        });
    })();
    </script>
</div>
