<?php
/**
 * Dashboard partial: autogpt tab v10.7.3.
 *
 * v10.7.3: 任务队列作为自动Agent的子面板
 *
 * @package Linked3
 * @subpackage Admin\Views\Dashboard\Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

// v10.7.3: 子面板路由 (queue作为子面板)
// v17.2.0: 修复 — 链接改用新路由 tab=automation&au_sub=autogpt&sub=queue
$ag_sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : 'main';

// 子面板切换
echo '<h2>🤖 自动 Agent <span style="font-size:12px;color:#71717A;font-weight:normal;">v17.2.0</span></h2>';
echo '<div class="linked3-eco-subtabs">';
echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt&sub=main')) . '" class="linked3-eco-subtab ' . ($ag_sub === 'main' ? 'active' : '') . '">🤖 Agent管理</a>';
echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt&sub=queue')) . '" class="linked3-eco-subtab ' . ($ag_sub === 'queue' ? 'active' : '') . '">📋 任务队列</a>';
echo '</div>';

if ($ag_sub === 'queue') {
    // 任务队列子面板
    $queue_partial = __DIR__ . '/tab-queue.php';
    if (file_exists($queue_partial)) {
        include $queue_partial;
    }
    return;
}

// 主面板: Agent管理
                echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:15px;margin:15px 0;">';
                echo '<h3 style="margin-top:0;">使用场景</h3>';
                echo '<table class="widefat" style="font-size:13px;"><thead><tr><th>场景</th><th>操作</th><th>适用人群</th></tr></thead><tbody>';
                echo '<tr><td><strong>每日自动发文</strong></td><td>创建内容写作Agent,每天9:00生成2篇草稿</td><td>内容站长</td></tr>';
                echo '<tr><td><strong>采集改写流水线</strong></td><td>创建采集改写Agent,定时从URL采集→改写→草稿</td><td>内容运营</td></tr>';
                echo '<tr><td><strong>旧文SEO优化</strong></td><td>创建内容增强Agent,扫描低分文章→自动改写</td><td>SEO运营</td></tr>';
                echo '<tr><td><strong>知识库构建</strong></td><td>创建内容索引Agent,自动索引到向量库供RAG检索</td><td>AI客服</td></tr>';
                echo '</tbody></table>';
                echo '</div>';
                echo '<p style="font-size:12px;color:#666;">支持5种类型: 内容写作 / 采集改写 / 内容增强 / 内容索引 / 评论回复。⚠️ 内容索引需先在「AI对话」tab配置向量库。</p>';
                echo '<h2>自动 Agent</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong>定时自动执行 AI 任务,4种类型:</p>';
                echo '<ul style="list-style:disc;margin-left:20px;">';
                echo '<li><strong>内容写作:</strong>按关键词/模板自动生成文章(默认草稿,可设直接发布)</li>';
                echo '<li><strong>内容增强:</strong>扫描旧文章 → SEO 评分 → 自动改写优化</li>';
                echo '<li><strong>内容索引:</strong>自动将文章索引到向量库(RAG)</li>';
                echo '<li><strong>评论回复:</strong>自动生成评论回复(待审核)</li>';
                echo '</ul>';
                echo '<p>支持:定时调度(10分钟/每小时/每天/每周)、并发限制、连续失败熔断、任务队列重试。</p></div>';
                // 内联 Agent 列表
                $ag_repo = new \Linked3\Classes\AutoGPT\AutoGPTTaskRepository();
                $tasks = $ag_repo->all(get_current_user_id());
                if (empty($tasks)) {
                    echo '<div style="text-align:center;padding:30px;background:#f9fafb;border:1px dashed #d1d5db;border-radius:6px;margin:10px 0;">';
                    echo '<p style="font-size:14px;color:#71717A;margin:0 0 12px 0;">🤖 暂无 Agent</p>';
                    echo '<p style="font-size:12px;color:#9ca3af;margin:0 0 16px 0;">创建自动Agent后, 可定时执行内容采集/生成/发布等任务。</p>';
                    echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-autogpt')) . '" class="button button-primary">+ 创建第一个 Agent</a>';
                    echo '</div>';
                } else {
                    // v11.5.2: 增加关联状态列 (P1) — 显示模版/发布目标关联情况
                    echo '<table class="widefat striped"><thead><tr><th>名称</th><th>类型</th><th>状态</th><th>关联模版</th><th>发布目标</th><th>计划</th><th>运行次数</th></tr></thead><tbody>';
                    foreach ($tasks as $t) {
                        $cfg = is_string($t['config'] ?? '') ? json_decode($t['config'], true) : ($t['config'] ?? []);
                        $tpl_id = (int)($cfg['template_id'] ?? 0);
                        $pub_id = (int)($cfg['publish_target_id'] ?? 0);
                        // v11.5.2: 关联状态徽章
                        $tpl_badge = $tpl_id > 0 ? '<span style="color:#16a34a;">✓ #'.$tpl_id.'</span>' : '<span style="color:#9ca3af;">— 未关联</span>';
                        $pub_badge = $pub_id > 0 ? '<span style="color:#16a34a;">✓ #'.$pub_id.'</span>' : '<span style="color:#DC2626;">✗ 未关联</span>';
                        echo '<tr><td>' . esc_html($t['name']) . '</td><td><code>' . esc_html($t['task_type']) . '</code></td><td>' . esc_html($t['status']) . '</td><td style="font-size:11px;">' . $tpl_badge . '</td><td style="font-size:11px;">' . $pub_badge . '</td><td>' . esc_html($t['next_run_time'] ?? '—') . '</td><td>' . (int)$t['run_count'] . '</td></tr>';
                    }
                    echo '</tbody></table>';
                    // v11.5.2: 闭环引导 — 未关联发布目标的Agent无法自动发布
                    $need_pub = 0;
                    foreach ($tasks as $t) {
                        $c = is_string($t['config'] ?? '') ? json_decode($t['config'], true) : ($t['config'] ?? []);
                        if (empty($c['publish_target_id'])) $need_pub++;
                    }
                    if ($need_pub > 0) {
                        echo '<div class="notice notice-warning inline"><p>⚠️ ' . $need_pub . ' 个 Agent 未关联发布目标 — 生成的内容无法自动发布。前往 <a href="' . esc_url(admin_url('admin.php?page=linked3-autogpt')) . '">Agent管理</a> 配置发布目标，或先在 <a href="' . esc_url(admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish')) . '">分发中心</a> 创建发布目标。</p></div>';
                    }
                }
                echo '<p><a href="' . esc_url(admin_url('admin.php?page=linked3-autogpt')) . '" class="button button-primary">管理 Agent</a></p>';
