<?php
/**
 * Dashboard partial: queue tab.
 *
 * Extracted from tabs.php in v4.4.1 to keep the router file under
 * 100 lines. Each partial owns its own HTML fragment and is
 * included by tabs.php inside the .linked3-tab-content wrapper.
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

                echo '<p style="font-size:12px;color:#666;">队列项类型: <span style="background:#F4F4F5;padding:2px 8px;border-radius:10px;">发布重试</span> <span style="background:#FEF3C7;padding:2px 8px;border-radius:10px;">AI重试</span> <span style="background:#fce7f3;padding:2px 8px;border-radius:10px;">分发重试</span> <span style="background:#F4F4F5;padding:2px 8px;border-radius:10px;">增强重试</span> <span style="background:#d1fae5;padding:2px 8px;border-radius:10px;">评论重试</span></p>';
                echo '<h2>任务队列</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong>查看 AutoGPT 任务队列的实时状态。可按状态筛选、单条重试/删除、批量清理。每 10 秒自动刷新。</p></div>';
                $nonce_q = wp_create_nonce('linked3_autogpt');
                ?>
                <p>
                    <label>状态筛选:
                        <select id="linked3-queue-filter">
                            <option value="">全部</option>
                            <option value="pending">等待中</option>
                            <option value="processing">处理中</option>
                            <option value="done">已完成</option>
                            <option value="error">失败</option>
                            <option value="skipped">已跳过</option>
                        </select>
                    </label>
                    <button class="button" id="linked3-queue-refresh">刷新</button>
                    <button class="button button-link-delete" id="linked3-queue-clear">清理已完成/失败</button>
                </p>
                <div id="linked3-queue-table" style="margin-top:10px;"></div>
                <script>
                (function(){
                    var n=<?php echo wp_json_encode($nonce_q);?>,u=<?php echo wp_json_encode(admin_url('admin-ajax.php'));?>;
                    function post(a,d,cb){var fd=new FormData();fd.append('action',a);fd.append('nonce',n);Object.keys(d).forEach(function(k){fd.append(k,d[k]);});fetch(u,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb);}
                    function loadQueue() {
                        var status=document.getElementById('linked3-queue-filter').value;
                        post('linked3_queue_list',{status:status},function(res){
                            var el=document.getElementById('linked3-queue-table');
                            if(!res.success||!res.data.items||res.data.items.length===0){
                                el.innerHTML='<div class="notice notice-success inline"><p>✅ 队列为空 — 所有任务已处理完毕。</p></div><p style="color:#666;">💡 新任务会在 AutoGPT 执行发布/AI生成/分发失败时自动入队。前往 <a href="'+u.replace('admin-ajax.php','admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt')+'">自动 Agent</a> 查看任务运行状态。</p>';
                                return;
                            }
                            var html='<table class="widefat striped"><thead><tr><th>ID</th><th>类型</th><th>任务ID</th><th>状态</th><th>尝试</th><th>详情</th><th>错误信息</th><th>计划重试</th><th>上次尝试</th><th>创建时间</th><th>操作</th></tr></thead><tbody>';
                            // v3.1.0: 类型标签颜色映射
                            var typeColors={
                                'publish_retry':'#F4F4F5','ai_retry':'#FEF3C7',
                                'distribute_retry':'#fce7f3','enhance_retry':'#F4F4F5',
                                'comment_retry':'#d1fae5'
                            };
                            var typeLabels={
                                'publish_retry':'发布重试','ai_retry':'AI重试',
                                'distribute_retry':'分发重试','enhance_retry':'增强重试',
                                'comment_retry':'评论重试'
                            };
                            res.data.items.forEach(function(item){
                                var bg=item.status==='error'?' style="background:#FEF2F2;"':(item.status==='done'?' style="background:#F4F4F5;"':'');
                                var pt=item.payload_type||'';
                                var typeColor=typeColors[pt]||'#f3f4f6';
                                var typeLabel=typeLabels[pt]||pt||'—';
                                // 详情列: 根据 type 显示不同字段
                                var details='';
                                if(pt==='distribute_retry'){details='平台: '+(item.payload_platform||'—')+'<br>文章: '+(item.payload_post_id||'—');}
                                else if(pt==='publish_retry'){details='目标: '+(item.payload_target_id||'—');}
                                else if(pt==='enhance_retry'||pt==='ai_retry'){details='文章: '+(item.payload_post_id||'—');}
                                else if(pt==='comment_retry'){details='评论: '+(item.payload_comment_id||'—');}
                                // 计划重试时间相对显示
                                var scheduled=item.scheduled_for||'—';
                                if(scheduled!=='—'){
                                    // 简单相对时间
                                    var d=new Date(scheduled.replace(' ','T')+'Z');
                                    var diff=Math.round((d-new Date())/60000);
                                    if(diff>0){scheduled=diff+' 分钟后';}else if(diff>-60){scheduled='已到期';}else{scheduled=item.scheduled_for;}
                                }
                                var lastAttempt=item.last_attempt_time||'—';
                                html+='<tr'+bg+'><td>'+item.id+'</td><td><span style="background:'+typeColor+';padding:2px 8px;border-radius:10px;font-size:11px;">'+typeLabel+'</span></td><td>'+(item.task_id||'0')+'</td><td><strong>'+item.status+'</strong></td><td>'+item.attempts+'/'+item.max_attempts+'</td><td style="font-size:11px;">'+details+'</td><td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;font-size:11px;" title="'+(item.error_message||'')+'">'+(item.error_message||'—')+'</td><td style="font-size:11px;">'+scheduled+'</td><td style="font-size:11px;">'+lastAttempt+'</td><td style="font-size:11px;">'+item.added_at+'</td><td>';
                                if(item.status==='error'||item.status==='pending'){
                                    html+='<button class="button button-small linked3-q-retry" data-id="'+item.id+'">重试</button> ';
                                }
                                html+='<button class="button button-small button-link-delete linked3-q-del" data-id="'+item.id+'">删除</button>';
                                html+='</td></tr>';
                            });
                            html+='</tbody></table>';
                            el.innerHTML=html;
                            // 绑定按钮
                            document.querySelectorAll('.linked3-q-retry').forEach(function(b){
                                b.addEventListener('click',function(){
                                    post('linked3_queue_retry',{id:b.dataset.id},function(r){if(r.success)loadQueue();});
                                });
                            });
                            document.querySelectorAll('.linked3-q-del').forEach(function(b){
                                b.addEventListener('click',function(){
                                    if(!confirm('确认删除?'))return;
                                    post('linked3_queue_delete',{id:b.dataset.id},function(r){if(r.success)loadQueue();});
                                });
                            });
                        });
                    }
                    document.getElementById('linked3-queue-refresh').addEventListener('click',loadQueue);
                    document.getElementById('linked3-queue-filter').addEventListener('change',loadQueue);
                    document.getElementById('linked3-queue-clear').addEventListener('click',function(){
                        if(!confirm('清理所有已完成和失败的队列项?'))return;
                        post('linked3_queue_bulk_delete',{},function(r){if(r.success){alert('已清理');loadQueue();}});
                    });
                    loadQueue();
                    setInterval(loadQueue,10000); // 每10秒自动刷新
                })();
                </script>
                <?php
