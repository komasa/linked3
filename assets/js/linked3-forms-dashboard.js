/**
 * linked3-forms-dashboard.js
 * Extracted from: admin/views/forms/dashboard.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-forms-dashboard.js
 * Localized via wp_localize_script('linked3-forms', 'linked3_forms', {...})
 *   Keys: nonce, ajax_url, data, val, val, val, val, val, val, val
 */

(function(){
    var nonce = window.linked3_forms && window.linked3_forms.nonce || '';
    var ajax_url = window.linked3_forms && window.linked3_forms.ajax_url || '';
    var data = window.linked3_forms && window.linked3_forms.data || '';
    var val = window.linked3_forms && window.linked3_forms.val || '';
    var val = window.linked3_forms && window.linked3_forms.val || '';
    var val = window.linked3_forms && window.linked3_forms.val || '';
    var val = window.linked3_forms && window.linked3_forms.val || '';
    var val = window.linked3_forms && window.linked3_forms.val || '';
    var val = window.linked3_forms && window.linked3_forms.val || '';
    var val = window.linked3_forms && window.linked3_forms.val || '';


    (function(){
        var nonce = linked3_forms.nonce;
        var ajaxUrl = linked3_forms.ajax_url;
        var forms = linked3_forms.data;
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
            document.getElementById('linked3-form-submit-label').value = 'linked3_forms.val';
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
                '<td><button type="button" class="button button-link-delete f-remove">linked3_forms.val</button></td>';
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
            document.getElementById('linked3-form-dialog-title').textContent = 'linked3_forms.val';
            clearDialog();
            document.getElementById('linked3-form-dialog').style.display = 'block';
        });

        document.querySelectorAll('.linked3-form-edit').forEach(function(btn){
            btn.addEventListener('click', function(){
                var id = btn.getAttribute('data-id');
                var form = forms[id];
                if(!form){ alert('linked3_forms.val'); return; }
                document.getElementById('linked3-form-dialog-title').textContent = 'linked3_forms.val';
                document.getElementById('linked3-form-id').value = id;
                document.getElementById('linked3-form-title').value = form.title || '';
                document.getElementById('linked3-form-submit-label').value = form.submit_label || 'linked3_forms.val';
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
                else { alert((res.data && res.data.message) || 'linked3_forms.val'); }
            });
        });

        document.querySelectorAll('.linked3-form-del').forEach(function(btn){
            btn.addEventListener('click', function(){
                if(!confirm('linked3_forms.val')) return;
                post('linked3_form_delete', {id: btn.getAttribute('data-id')}, function(res){
                    if(res.success) location.reload();
                    else alert((res.data && res.data.message) || 'linked3_forms.val');
                });
            });
        });
    })();
    
})();
