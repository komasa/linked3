<?php
if (!defined('ABSPATH')) exit;
/** @var array $tasks */
$nonce = wp_create_nonce('linked3_autogpt');
$ajax_url = admin_url('admin-ajax.php');
// v3.0.0: 获取可用分发平台列表
$dist_platforms = [];
if (class_exists('\\Linked3\\Classes\\Distribute\\DistributeManager')) {
    $dist_platforms = \Linked3\Classes\Distribute\DistributeManager::instance()->available_platforms();
}
// v3.0.0: 获取发布目标列表
$publish_targets = [];
if (class_exists('\\Linked3\\Classes\\Publish\\Linked3_Publish_Target_Repository')) {
    $repo = new \Linked3\Classes\Publish\Linked3_Publish_Target_Repository();
    $publish_targets = $repo->all_for_user(get_current_user_id());
}
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Dashboard</h1>
        <a href="admin.php?page=linked3-dashboard" class="button">← 返回总览</a>
    </div>
    <h1><?php echo esc_html__('AutoGPT — 自进化 Agent', 'linked3'); ?>
        <button class="page-title-action" id="linked3-ag-new"><?php echo esc_html__('新建 Agent', 'linked3'); ?></button>
    </h1>
    <p><?php echo esc_html__('Agent 按计划自动写作、增强、索引、回复。支持定时发布到多平台。', 'linked3'); ?></p>

    <table class="widefat striped" id="linked3-ag-table">
        <thead><tr>
            <th><?php echo esc_html__('名称', 'linked3'); ?></th>
            <th><?php echo esc_html__('类型', 'linked3'); ?></th>
            <th><?php echo esc_html__('状态', 'linked3'); ?></th>
            <th><?php echo esc_html__('上次运行', 'linked3'); ?></th>
            <th><?php echo esc_html__('运行次数', 'linked3'); ?></th>
            <th><?php echo esc_html__('操作', 'linked3'); ?></th>
        </tr></thead>
        <tbody>
        <?php if (empty($tasks)) : ?>
            <tr><td colspan="6"><?php echo esc_html__('暂无 Agent,点击「新建 Agent」。', 'linked3'); ?></td></tr>
        <?php else : foreach ($tasks as $t) : ?>
            <tr data-id="<?php echo esc_attr($t['id']); ?>">
                <td><?php echo esc_html($t['name']); ?></td>
                <td><code><?php echo esc_html($t['task_type']); ?></code></td>
                <td><?php echo esc_html($t['status']); ?></td>
                <td><?php echo esc_html($t['last_run_time'] ?: '—'); ?></td>
                <td><?php echo (int) $t['run_count']; ?></td>
                <td>
                    <?php if ($t['status'] === 'active') : ?>
                    <button class="button linked3-ag-pause"><?php echo esc_html__('暂停', 'linked3'); ?></button>
                    <?php else : ?>
                    <button class="button linked3-ag-resume"><?php echo esc_html__('启用', 'linked3'); ?></button>
                    <?php endif; ?>
                    <button class="button button-link-delete linked3-ag-del"><?php echo esc_html__('删除', 'linked3'); ?></button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div id="linked3-ag-dialog" style="display:none;background:#fff;border:1px solid #ddd;padding:20px;margin-top:20px;border-radius:6px;max-width:800px;">
        <h3><?php echo esc_html__('新建 Agent', 'linked3'); ?></h3>
        <p><label><?php echo esc_html__('名称', 'linked3'); ?><br><input type="text" id="linked3-ag-name" class="regular-text" /></label></p>
        <p><label><?php echo esc_html__('类型', 'linked3'); ?><br>
            <select id="linked3-ag-type">
                <option value="content-writing"><?php echo esc_html__('内容写作', 'linked3'); ?></option>
                <option value="collect-rewrite"><?php echo esc_html__('采集改写 (v3.2.0)', 'linked3'); ?></option>
                <option value="content-enhancement"><?php echo esc_html__('内容增强', 'linked3'); ?></option>
                <option value="content-indexing"><?php echo esc_html__('内容索引', 'linked3'); ?></option>
                <option value="comment-reply"><?php echo esc_html__('评论回复', 'linked3'); ?></option>
            </select>
        </label></p>
        <p><label><?php echo esc_html__('计划', 'linked3'); ?><br>
            <select id="linked3-ag-schedule">
                <option value="every_10min"><?php echo esc_html__('每 10 分钟', 'linked3'); ?></option>
                <option value="hourly" selected><?php echo esc_html__('每小时', 'linked3'); ?></option>
                <option value="daily"><?php echo esc_html__('每日', 'linked3'); ?></option>
                <option value="weekly"><?php echo esc_html__('每周', 'linked3'); ?></option>
            </select>
        </label></p>

        <!-- v3.0.0: smart schedule 时间窗 -->
        <details style="margin:10px 0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:10px;">
            <summary style="cursor:pointer;font-weight:600;color:#666;">⏰ 智能时间窗 (可选)</summary>
            <div style="margin-top:10px;">
                <p><label>时间窗 (多个用逗号分隔,留空=不限制):<br>
                    <input type="text" id="linked3-ag-time-window" class="regular-text" placeholder="如:09:00-12:00,14:00-18:00" />
                </label></p>
                <p style="color:#666;font-size:12px;">只在指定时间段内执行 Agent。适合避免深夜发布。</p>
                <p><label>精确到小时 (留空=不限制):<br>
                    <input type="time" id="linked3-ag-specific-time" style="width:120px;" />
                </label></p>
                <p style="color:#666;font-size:12px;">只在指定小时内的 cron tick 执行(实际精度受 cron 10min 粒度限制)。</p>
            </div>
        </details>

        <div id="linked3-ag-cfg-writing">
            <p><label><?php echo esc_html__('关键词', 'linked3'); ?><br><input type="text" id="linked3-ag-keyword" class="regular-text" /></label></p>
            <p><label><?php echo esc_html__('每次生成篇数', 'linked3'); ?><br><input type="number" id="linked3-ag-count" value="1" min="1" max="10" /></label></p>
            <p><label><input type="checkbox" id="linked3-ag-publish-directly" /> <?php echo esc_html__('直接发布(跳过草稿审核)', 'linked3'); ?></label>
               <span style="color:#666;display:block;margin-top:4px;"><?php echo esc_html__('默认关闭 — 文章保存为草稿,发布前需人工审核。', 'linked3'); ?></span></p>
            <p><label><input type="checkbox" id="linked3-ag-inject-images" /> <?php echo esc_html__('自动配图', 'linked3'); ?></label>
               <span style="color:#666;display:block;margin-top:4px;"><?php echo esc_html__('勾选后生成文章时自动注入配图(使用全局图片设置)。', 'linked3'); ?></span></p>

            <!-- v3.0.0: 发布目标选择 -->
            <?php if (!empty($publish_targets)) : ?>
            <p><label><?php echo esc_html__('发布目标', 'linked3'); ?> (留空=本地 WordPress):<br>
                <select id="linked3-ag-publish-target" style="min-width:300px;">
                    <option value="0"><?php echo esc_html__('本地 WordPress', 'linked3'); ?></option>
                    <?php foreach ($publish_targets as $pt) : ?>
                        <option value="<?php echo esc_attr($pt['id']); ?>"><?php echo esc_html($pt['name'] . ' (' . $pt['type'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label></p>
            <?php endif; ?>

            <!-- v3.0.0: 分发平台子集 (per-task) -->
            <?php if (!empty($dist_platforms)) : ?>
            <details style="margin:10px 0;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:10px;">
                <summary style="cursor:pointer;font-weight:600;color:#666;">📡 分发平台 (勾选后,文章生成后自动分发到这些平台)</summary>
                <div style="margin-top:10px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;">
                    <?php foreach ($dist_platforms as $slug => $label) : ?>
                        <label><input type="checkbox" class="linked3-ag-platform" value="<?php echo esc_attr($slug); ?>" /> <?php echo esc_html($label); ?></label>
                    <?php endforeach; ?>
                </div>
                <p style="color:#666;font-size:12px;margin-top:6px;">不勾选=不分发。可勾选多个。失败的平台会自动入队重试(最多 3 次)。</p>
            </details>
            <?php endif; ?>
        </div>

        <!-- v3.2.0: 采集改写配置 -->
        <div id="linked3-ag-cfg-collect" style="display:none;">
            <p><label>URL 列表 (每行一个,最多 5 个/次):<br>
                <textarea id="linked3-ag-urls" rows="4" class="large-text" style="width:100%;" placeholder="https://example.com/article1&#10;https://example.com/article2"></textarea>
            </label></p>
            <p>
                <label>语气:
                    <select id="linked3-ag-tone">
                        <option value="professional">专业</option>
                        <option value="casual">随意</option>
                        <option value="academic">学术</option>
                        <option value="persuasive">说服</option>
                    </select>
                </label>
                <label style="margin-left:15px;">复杂度:
                    <select id="linked3-ag-complexity">
                        <option value="beginner">入门</option>
                        <option value="intermediate" selected>中级</option>
                        <option value="expert">专家</option>
                    </select>
                </label>
            </p>
            <p>
                <label><input type="checkbox" id="linked3-ag-seo" checked /> SEO 优化</label>
                <label style="margin-left:15px;"><input type="checkbox" id="linked3-ag-simplify" /> 简化语言</label>
                <label style="margin-left:15px;"><input type="checkbox" id="linked3-ag-publish-directly2" /> 直接发布</label>
            </p>
            <p style="color:#666;font-size:12px;">工作流: 采集 URL → AI 改写 (保留原意+原创) → 保存草稿 → (可选)分发</p>
        </div>
        <p>
            <button class="button button-primary" id="linked3-ag-save"><?php echo esc_html__('创建', 'linked3'); ?></button>
            <button class="button" id="linked3-ag-cancel"><?php echo esc_html__('取消', 'linked3'); ?></button>
        </p>
    </div>

    <script>
    (function(){
        var nonce=<?php echo wp_json_encode($nonce);?>, ajaxUrl=<?php echo wp_json_encode($ajax_url);?>;
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
            if(!confirm('<?php echo esc_js(__('Delete this agent?','linked3'));?>'))return;
            post('linked3_autogpt_delete_task',{id:b.closest('tr').dataset.id},function(res){if(res.success)location.reload();});
        });});
    })();
    </script>
</div>
