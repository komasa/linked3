<?php
if (!defined('ABSPATH')) exit;
$forms = get_option(LINKED3_OPTION_PREFIX . 'ai_forms', []);
$submissions = get_option(LINKED3_OPTION_PREFIX . 'ai_form_submissions', []);
$nonce = wp_create_nonce('linked3_forms_admin');
$ajax_url = admin_url('admin-ajax.php');
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Dashboard</h1>
        <a href="admin.php?page=linked3-dashboard" class="button">← 返回总览</a>
    </div>
    <h1><?php echo esc_html__('AI 表单', 'linked3'); ?>
        <button class="page-title-action" id="linked3-form-new"><?php echo esc_html__('新建表单', 'linked3'); ?></button>
    </h1>
    <p><?php echo esc_html__('用 [linked3_form id="N"] 嵌入表单。提交内容会存储并可 AI 分析。', 'linked3'); ?></p>

    <h2><?php echo esc_html__('已定义表单', 'linked3'); ?></h2>
    <?php if (empty($forms) || !is_array($forms)) : ?>
        <p><?php echo esc_html__('暂无表单,点击「新建表单」创建。', 'linked3'); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead><tr>
                <th><?php echo esc_html__('Shortcode', 'linked3'); ?></th>
                <th><?php echo esc_html__('标题', 'linked3'); ?></th>
                <th><?php echo esc_html__('字段', 'linked3'); ?></th>
                <th><?php echo esc_html__('AI 提示词', 'linked3'); ?></th>
                <th><?php echo esc_html__('通知邮箱', 'linked3'); ?></th>
                <th><?php echo esc_html__('操作', 'linked3'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($forms as $id => $f) : ?>
                <tr data-id="<?php echo esc_attr($id); ?>">
                    <td><code>[linked3_form id="<?php echo esc_attr($id); ?>"]</code></td>
                    <td class="linked3-form-title"><?php echo esc_html($f['title']); ?></td>
                    <td><?php echo count($f['fields']); ?></td>
                    <td><?php echo !empty($f['ai_prompt']) ? esc_html__('是', 'linked3') : esc_html__('否', 'linked3'); ?></td>
                    <td><?php echo esc_html($f['notify_email'] ?? ''); ?></td>
                    <td>
                        <button class="button linked3-form-edit" data-id="<?php echo esc_attr($id); ?>"><?php echo esc_html__('编辑', 'linked3'); ?></button>
                        <button class="button button-link-delete linked3-form-del" data-id="<?php echo esc_attr($id); ?>"><?php echo esc_html__('删除', 'linked3'); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2><?php echo esc_html__('最近提交', 'linked3'); ?></h2>
    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('时间', 'linked3'); ?></th><th><?php echo esc_html__('表单', 'linked3'); ?></th><th><?php echo esc_html__('Values', 'linked3'); ?></th></tr></thead><tbody>
    <?php if (empty($submissions)) : ?><tr><td colspan="3"><?php echo esc_html__('暂无提交。', 'linked3'); ?></td></tr>
    <?php else : foreach (array_slice(array_reverse($submissions), 0, 20) as $s) : ?>
        <tr><td><?php echo esc_html(gmdate('Y-m-d H:i', $s['ts'])); ?></td><td>#<?php echo (int) $s['form_id']; ?></td><td><pre style="margin:0;max-height:80px;overflow:auto;"><?php echo esc_html(wp_json_encode($s['values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre></td></tr>
    <?php endforeach; endif; ?>
    </tbody></table>

    <!-- Form editor dialog (used for both create + edit) -->
    <div id="linked3-form-dialog" style="display:none;background:#fff;border:1px solid #ddd;padding:20px;margin-top:20px;">
        <h3 id="linked3-form-dialog-title"><?php echo esc_html__('新建表单', 'linked3'); ?></h3>
        <input type="hidden" id="linked3-form-id" value="0" />
        <p><label><?php echo esc_html__('标题', 'linked3'); ?><br><input type="text" id="linked3-form-title" class="regular-text" /></label></p>
        <p><label><?php echo esc_html__('Submit Button Label', 'linked3'); ?><br><input type="text" id="linked3-form-submit-label" class="regular-text" value="<?php echo esc_attr(__('提交', 'linked3')); ?>" /></label></p>
        <p><label><?php echo esc_html__('AI 提示词(可选,与提交值一起发送用于分析)', 'linked3'); ?><br><textarea id="linked3-form-ai-prompt" rows="3" class="large-text"></textarea></label></p>
        <p><label><?php echo esc_html__('通知邮箱(可选,每次提交时发送)', 'linked3'); ?><br><input type="email" id="linked3-form-notify-email" class="regular-text" /></label></p>
        <h4><?php echo esc_html__('字段', 'linked3'); ?></h4>
        <table class="widefat" id="linked3-form-fields-table">
            <thead><tr><th><?php echo esc_html__('标签', 'linked3'); ?></th><th><?php echo esc_html__('类型', 'linked3'); ?></th><th><?php echo esc_html__('必填', 'linked3'); ?></th><th><?php echo esc_html__('选项(仅下拉,逗号分隔)', 'linked3'); ?></th><th>&nbsp;</th></tr></thead>
            <tbody></tbody>
        </table>
        <p><button type="button" class="button" id="linked3-form-add-field"><?php echo esc_html__('添加字段', 'linked3'); ?></button></p>
        <p>
            <button class="button button-primary" id="linked3-form-save"><?php echo esc_html__('保存', 'linked3'); ?></button>
            <button class="button" id="linked3-form-cancel"><?php echo esc_html__('取消', 'linked3'); ?></button>
        </p>
    </div>

    <script>
    (function(){
        var nonce = <?php echo wp_json_encode($nonce); ?>;
        var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        var forms = <?php echo wp_json_encode($forms, JSON_UNESCAPED_UNICODE); ?>;
        var FIELD_TYPES = ['text','email','url','tel','textarea','select','number'];

        function post(action, data, cb){
            var fd = new FormData();
            fd.append('action', action);
            fd.append('nonce', nonce);
            Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
            fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){return r.json();}).then(cb);
        }

        function clearDialog() {
            document.getElementById('linked3-form-id').value = '0';
            document.getElementById('linked3-form-title').value = '';
            document.getElementById('linked3-form-submit-label').value = '<?php echo esc_js(__('Submit','linked3')); ?>';
            document.getElementById('linked3-form-ai-prompt').value = '';
            document.getElementById('linked3-form-notify-email').value = '';
            document.querySelector('#linked3-form-fields-table tbody').innerHTML = '';
            addFieldRow();
        }

        function addFieldRow(field) {
            field = field || {label:'', type:'text', required:false, options:[]};
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" class="f-label regular-text" value="' + (field.label||'').replace(/"/g,'&quot;') + '" /></td>' +
                '<td><select class="f-type">' + FIELD_TYPES.map(function(t){return '<option value="'+t+'"'+(t===field.type?' selected':'')+'>'+t+'</option>';}).join('') + '</select></td>' +
                '<td><input type="checkbox" class="f-required" ' + (field.required ? 'checked' : '') + ' /></td>' +
                '<td><input type="text" class="f-options regular-text" value="' + (field.options||[]).join(', ') + '" /></td>' +
                '<td><button type="button" class="button button-link-delete f-remove"><?php echo esc_js(__('Remove','linked3')); ?></button></td>';
            document.querySelector('#linked3-form-fields-table tbody').appendChild(tr);
            tr.querySelector('.f-remove').addEventListener('click', function(){ tr.remove(); });
        }

        function collectFields(){
            var rows = document.querySelectorAll('#linked3-form-fields-table tbody tr');
            var out = [];
            rows.forEach(function(tr){
                var label = tr.querySelector('.f-label').value.trim();
                if(!label) return;
                out.push({
                    label: label,
                    type: tr.querySelector('.f-type').value,
                    required: tr.querySelector('.f-required').checked,
                    options: tr.querySelector('.f-options').value.split(',').map(function(s){return s.trim();}).filter(Boolean)
                });
            });
            return out;
        }

        document.getElementById('linked3-form-new').addEventListener('click', function(){
            document.getElementById('linked3-form-dialog-title').textContent = '<?php echo esc_js(__('New Form','linked3')); ?>';
            clearDialog();
            document.getElementById('linked3-form-dialog').style.display = 'block';
        });

        document.querySelectorAll('.linked3-form-edit').forEach(function(btn){
            btn.addEventListener('click', function(){
                var id = btn.getAttribute('data-id');
                var form = forms[id];
                if(!form){ alert('<?php echo esc_js(__('Form not found.','linked3')); ?>'); return; }
                document.getElementById('linked3-form-dialog-title').textContent = '<?php echo esc_js(__('Edit Form','linked3')); ?>';
                document.getElementById('linked3-form-id').value = id;
                document.getElementById('linked3-form-title').value = form.title || '';
                document.getElementById('linked3-form-submit-label').value = form.submit_label || '<?php echo esc_js(__('Submit','linked3')); ?>';
                document.getElementById('linked3-form-ai-prompt').value = form.ai_prompt || '';
                document.getElementById('linked3-form-notify-email').value = form.notify_email || '';
                document.querySelector('#linked3-form-fields-table tbody').innerHTML = '';
                (form.fields || []).forEach(addFieldRow);
                document.getElementById('linked3-form-dialog').style.display = 'block';
            });
        });

        document.getElementById('linked3-form-add-field').addEventListener('click', function(){ addFieldRow(); });

        document.getElementById('linked3-form-cancel').addEventListener('click', function(){
            document.getElementById('linked3-form-dialog').style.display = 'none';
        });

        document.getElementById('linked3-form-save').addEventListener('click', function(){
            var id = document.getElementById('linked3-form-id').value;
            var data = {
                title: document.getElementById('linked3-form-title').value,
                submit_label: document.getElementById('linked3-form-submit-label').value,
                ai_prompt: document.getElementById('linked3-form-ai-prompt').value,
                notify_email: document.getElementById('linked3-form-notify-email').value,
                fields: JSON.stringify(collectFields())
            };
            if(id && id !== '0'){ data.id = id; }
            post(id && id !== '0' ? 'linked3_form_update' : 'linked3_form_create', data, function(res){
                if(res.success){ location.reload(); }
                else { alert((res.data && res.data.message) || '<?php echo esc_js(__('Error','linked3')); ?>'); }
            });
        });

        document.querySelectorAll('.linked3-form-del').forEach(function(btn){
            btn.addEventListener('click', function(){
                if(!confirm('<?php echo esc_js(__('Delete this form? Submissions are kept.','linked3')); ?>')) return;
                post('linked3_form_delete', {id: btn.getAttribute('data-id')}, function(res){
                    if(res.success) location.reload();
                    else alert((res.data && res.data.message) || '<?php echo esc_js(__('Error','linked3')); ?>');
                });
            });
        });
    })();
    </script>
</div>
