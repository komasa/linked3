/**
 * linked3-tab-queue.js
 * Extracted from: admin/views/dashboard/partials/tab-queue.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-queue.js
 * Localized via wp_localize_script('linked3-tab-queue', 'linked3_tab_queue', {...})
 *   Keys: nonce_q, ajax_url
 */

(function(){
    var nonce_q = window.linked3_tab_queue && window.linked3_tab_queue.nonce_q || '';
    var ajax_url = window.linked3_tab_queue && window.linked3_tab_queue.ajax_url || '';


                (function(){
                    var n=linked3_tab_queue.nonce_q,u=linked3_tab_queue.ajax_url;
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
                
})();
