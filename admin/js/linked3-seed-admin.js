/**
 * Linked3 Seed Admin JS
 * Extracted from: src/Classes/Genesis/SeedAdminRender.php + SeedAdminPages.php
 * Depends on: jQuery, linked3-fetch
 */
(function($) {
    'use strict';

    // === Checkbox "select all" toggle (from SeedAdminPages.php) ===
    $(function() {
        $("#linked3-cb-all").on("change", function() {
            $(".linked3-seed-cb").prop("checked", this.checked);
        });
    });

    // === Tab switching ===
    $(function() {
        $('.linked3-tabs').each(function() {
            var $wrap = $(this);
            $wrap.find('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $wrap.find('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $wrap.find('.tab-panel').removeClass('active');
                $wrap.find($(this).attr('href')).addClass('active');
            });
        });
    });

    // === Wizard navigation ===
    $(function() {
        $('.linked3-wizard').each(function() {
            var $wiz = $(this);
            function showStep(i) {
                $wiz.find('.step-pane').removeClass('active');
                $wiz.find('.step-pane').eq(i).addClass('active');
                $wiz.find('.step-indicator .step').removeClass('active done');
                $wiz.find('.step-indicator .step').each(function(idx) {
                    if (idx < i) $(this).addClass('done');
                    else if (idx === i) $(this).addClass('active');
                });
                $wiz.data('step', i);
            }
            showStep(0);
            $wiz.on('click', '.step-next', function(e) {
                e.preventDefault();
                var i = $wiz.data('step');
                if (i < 6) showStep(i + 1);
            });
            $wiz.on('click', '.step-prev', function(e) {
                e.preventDefault();
                var i = $wiz.data('step');
                if (i > 0) showStep(i - 1);
            });
        });
    });

    // === Bulk actions ===
    $(document).on('change', '.linked3-bulk-action-select', function() {
        var action = $(this).val();
        if (!action) return;
        var ids = $('.linked3-seed-cb:checked').map(function(){ return $(this).val(); }).get();
        if (ids.length === 0) { alert('请先勾选 Seed'); $(this).val(''); return; }
        if (action === 'trash' && !confirm('确定要软删除选中的 ' + ids.length + ' 个 Seed 吗？(30天内可恢复)')) { $(this).val(''); return; }
        $('#linked3-bulk-action-input').val(action);
        $('#linked3-bulk-ids-input').val(ids.join(','));
        $('#linked3-bulk-form').submit();
    });

    $(document).on('click', '.linked3-trash-all-btn', function(e) {
        e.preventDefault();
        if (!confirm('警告: 此操作将软删除所有 Seed! 30天内可在回收站恢复, 确认前请先导出备份。')) return;
        if (!prompt('请输入 CONFIRM 以继续清空:')) return;
        var $btn = $(this);
        $.post(ajaxurl, {
            action: 'linked3_trash_all_seeds',
            nonce: $btn.data('nonce'),
            confirm: 'CONFIRM'
        }, function(resp) {
            if (resp.success) {
                alert('已软删除 ' + resp.data.count + ' 个 Seed');
                location.reload();
            } else {
                alert('失败: ' + (resp.data && resp.data.message ? resp.data.message : '未知错误'));
            }
        });
    });

    // === Save seed (edit form) ===
    $(document).on('click', '.linked3-save-seed-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $('#linked3-seed-edit-form');
        var data = $form.serializeArray();
        data.push({name: 'action', value: 'linked3_save_seed'});
        data.push({name: 'nonce', value: $btn.data('nonce')});
        $btn.prop('disabled', true).text('保存中...');
        $.post(ajaxurl, data, function(resp) {
            $btn.prop('disabled', false).text('保存');
            if (resp.success) {
                $form.find('.linked3-save-ok').remove();
                $btn.after(' <span class="linked3-save-ok" style="color:#16a34a;">✓ 已保存</span>');
                setTimeout(function(){ $('.linked3-save-ok').fadeOut(); }, 2000);
                if (resp.data.post_id && !$('input[name="post_id"]').length) {
                    $form.append('<input type="hidden" name="post_id" value="' + resp.data.post_id + '">');
                }
            } else {
                alert('保存失败: ' + (resp.data && resp.data.message ? resp.data.message : '未知错误'));
            }
        });
    });

    // === Save seed (new wizard form) ===
    $(document).on('click', '.linked3-wizard-save', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $('#linked3-seed-new-form');
        var data = $form.serializeArray();
        data.push({name: 'action', value: 'linked3_save_seed'});
        data.push({name: 'nonce', value: $btn.data('nonce')});
        data.push({name: 'is_new', value: '1'});
        $btn.prop('disabled', true).text('保存中...');
        $.post(ajaxurl, data, function(resp) {
            $btn.prop('disabled', false).text('保存 Seed');
            if (resp.success) {
                alert('Seed 已创建, ID=' + resp.data.seed_id);
                location.href = (window.linked3_seed && window.linked3_seed.list_url) ? window.linked3_seed.list_url : '';
            } else {
                alert('保存失败: ' + (resp.data && resp.data.message ? resp.data.message : '未知错误'));
            }
        });
    });

})(jQuery);
