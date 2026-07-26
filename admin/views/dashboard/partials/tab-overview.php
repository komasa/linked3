<?php
/**
 * Dashboard partial: overview tab.
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

                // 概览卡片
                ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0;">
                    <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
                        <h3 style="margin-top:0;"><?php echo esc_html__('当前套餐', 'linked3'); ?></h3>
                        <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html(ucfirst($overview['plan'])); ?></p>
                    </div>
                    <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
                        <h3 style="margin-top:0;"><?php echo esc_html__('今日 Token 用量', 'linked3'); ?></h3>
                        <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html(number_format($overview['tokens_today'])); ?> / <?php echo esc_html(number_format($overview['tokens_quota'])); ?></p>
                        <p style="color:#666;margin:5px 0 0;">剩余 <?php echo esc_html(number_format($overview['tokens_remaining'])); ?></p>
                    </div>
                    <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
                        <h3 style="margin-top:0;"><?php echo esc_html__('近 30 天 AI 调用', 'linked3'); ?></h3>
                        <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html(number_format($overview['ai_calls_30d'])); ?></p>
                    </div>
                    <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
                        <h3 style="margin-top:0;"><?php echo esc_html__('活跃 Agent', 'linked3'); ?></h3>
                        <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html((string)$overview['tasks_active']); ?></p>
                        <?php if (empty($overview['tasks_active'])) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt')); ?>" style="font-size:12px;">→ 创建第一个 Agent</a>
                        <?php endif; ?>
                    </div>
                    <div class="card" style="padding:15px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
                        <h3 style="margin-top:0;"><?php echo esc_html__('已配置 Provider', 'linked3'); ?></h3>
                        <p style="font-size:24px;font-weight:bold;margin:0;"><?php echo esc_html((string)$overview['providers_configured']); ?></p>
                        <?php if (empty($overview['providers_configured'])) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=api')); ?>" style="font-size:12px;">→ 配置 API Provider</a>
                        <?php endif; ?>
                    </div>
                </div>
                <h2><?php echo esc_html__('近 30 天用量趋势', 'linked3'); ?></h2>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:15px;border-radius:8px;">
                    <?php if (empty($chart)) : ?>
                        <div style="text-align:center;padding:30px;color:#71717A;">
                            <p style="font-size:14px;margin:0 0 8px 0;"><?php echo esc_html__('📊 暂无用量数据', 'linked3'); ?></p>
                            <p style="font-size:12px;color:#9ca3af;margin:0 0 12px 0;"><?php echo esc_html__('配置 API Key 后即可开始使用, 用量数据将在此显示。', 'linked3'); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=api')); ?>" class="button button-primary">→ 去配置 API Key</a>
                        </div>
                    <?php else : ?>
                        <div style="display:flex;align-items:flex-end;height:150px;gap:2px;">
                            <?php
                            $max_tokens = max(array_column($chart, 'tokens') ?: [1]);
                            foreach ($chart as $row) :
                                $height = $max_tokens > 0 ? max(2, (int)($row['tokens'] / $max_tokens * 140)) : 2;
                            ?>
                            <div title="<?php echo esc_attr($row['d'] . ': ' . number_format($row['calls']) . __(' 次调用, ', 'linked3') . number_format($row['tokens']) . ' tokens'); ?>"
                                 style="flex:1;background:#2563eb;height:<?php echo esc_attr($height); ?>px;border-radius:2px 2px 0 0;"></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <p style="margin-top:20px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=api')); ?>" class="button button-primary">配置 API Key</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem')); ?>" class="button">🚀 开始写作</a>
                </p>

                <?php
                // v16.0.6: Activation warning display (from v16.0.1 FIX 1)
                if (!empty($overview['activation_warning'])):
                    $w = $overview['activation_warning'];
                    $w_time = isset($w['time']) ? gmdate('Y-m-d H:i:s', (int)$w['time']) : '';
                    $w_msg = isset($w['message']) ? $w['message'] : '';
                ?>
                <div class="notice notice-warning" style="margin:20px 0;">
                    <p><strong>⚠️ 激活警告:</strong> <?php echo esc_html($w_msg); ?></p>
                    <p style="font-size:12px;color:#666;">时间: <?php echo esc_html($w_time); ?> — 插件已激活但部分功能可能受限。</p>
                </div>
                <?php endif; ?>

                <?php
                // v16.0.6: V18 subsystem health status card
                $v18_loaded = (int)($overview['v18_loaded'] ?? 0);
                $v18_total = (int)($overview['v18_total'] ?? 0);
                if ($v18_total > 0):
                    $v18_pct = $v18_total > 0 ? round($v18_loaded / $v18_total * 100) : 0;
                    $v18_color = $v18_pct >= 80 ? '#46b450' : ($v18_pct >= 50 ? '#ffb900' : '#dc3232');
                ?>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:15px;border-radius:8px;margin:20px 0;">
                    <h3 style="margin-top:0;"><?php echo esc_html__('🔮 V18 子系统状态', 'linked3'); ?></h3>
                    <p style="font-size:14px;">
                        模块加载: <strong style="color:<?php echo esc_attr($v18_color); ?>"><?php echo esc_html("{$v18_loaded}/{$v18_total}"); ?></strong>
                        (<?php echo esc_html($v18_pct); ?>%)
                    </p>
                    <div style="background:#f0f0f0;border-radius:4px;height:8px;overflow:hidden;margin:8px 0;">
                        <div style="background:<?php echo esc_attr($v18_color); ?>;height:100%;width:<?php echo esc_attr($v18_pct); ?>%;"></div>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=v18')); ?>" class="button button-small">→ 查看拆解OS</a>
                </div>
                <?php endif; ?>

                <?php
                // v11.4.3: 工作流联动卡片 (方案③) — 创作→分发→自动化 全链路状态
                $wf_cloud_count = 0;
                $wf_publish_count = 0;
                $wf_agent_count = (int)($overview['tasks_active'] ?? 0);
                if (class_exists('CloudTemplateFactory')) {
                    try { $wf_cloud_count = count((new CloudTemplateFactory())->get_categories()); } catch (\Throwable $e) {}
                }
                try {
                    $pub_repo = new \Linked3\Classes\Publish\PublishTargetRepository();
                    $wf_publish_count = count($pub_repo->all_for_user(get_current_user_id()));
                } catch (\Throwable $e) {}

                $workflow_steps = [
                    'creation' => [
                        'icon' => '✍️', 'label' => __('创作中心', 'linked3'),
                        'desc' => __('写作生态 + 视觉生态 + 云模版', 'linked3'),
                        'count' => $wf_cloud_count . ' 类母版',
                        'url' => admin_url('admin.php?page=linked3-dashboard&tab=creation'),
                        'btn' => __('去创作', 'linked3'),
                        'ok' => $wf_cloud_count > 0,
                    ],
                    'distribution' => [
                        'icon' => '📤', 'label' => __('分发中心', 'linked3'),
                        'desc' => __('发布目标 + 社交分发 + 电商', 'linked3'),
                        'count' => $wf_publish_count . ' 个发布目标',
                        'url' => admin_url('admin.php?page=linked3-dashboard&tab=distribution'),
                        'btn' => __('去分发', 'linked3'),
                        'ok' => $wf_publish_count > 0,
                    ],
                    'automation' => [
                        'icon' => '🤖', 'label' => __('自动化', 'linked3'),
                        'desc' => __('自动Agent + AI对话', 'linked3'),
                        'count' => $wf_agent_count . ' 个活跃Agent',
                        'url' => admin_url('admin.php?page=linked3-dashboard&tab=automation'),
                        'btn' => __('去配置', 'linked3'),
                        'ok' => $wf_agent_count > 0,
                    ],
                ];

                // v11.9.1: 长尾词库状态卡片 (解决"看不到长尾词库")
                $wf_tail_count = count((array) get_option(LINKED3_OPTION_PREFIX . 'tail_keywords', []));
                $wf_hot_count = count((array) get_option(LINKED3_OPTION_PREFIX . 'hot_keywords', []));
                ?>
                <!-- v11.9.1: 长尾词库快捷入口 -->
                <div style="margin:15px 0;padding:12px;background:#FAFAFA;border:1px solid #E4E4E7;border-radius:8px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                    <span style="font-size:20px;">📋</span>
                    <div>
                        <strong style="font-size:14px;color:#0F172A;"><?php echo esc_html__('长尾词库', 'linked3'); ?></strong>
                        <span style="font-size:12px;color:#0F172A;margin-left:8px;">热词: <?php echo (int)$wf_hot_count; ?> | 长尾词: <?php echo (int)$wf_tail_count; ?></span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=keywords')); ?>" class="button button-small button-primary">→ 管理长尾词库</a>
                    <?php if ($wf_tail_count > 0) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&eco_sub=content&cw_mode=csv')); ?>" class="button button-small">📊 用长尾词批量生成文章</a>
                    <?php endif; ?>
                </div>

                <h2 style="margin-top:30px;"><?php echo esc_html__('🔗 工作流联动', 'linked3'); ?><span style="font-size:12px;color:#71717A;font-weight:normal;"><?php echo esc_html__('创作 → 分发 → 自动化', 'linked3'); ?></span></h2>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:15px 0;">
                    <?php foreach ($workflow_steps as $step): ?>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:15px;position:relative;">
                        <div style="font-size:28px;margin-bottom:6px;"><?php echo esc_html($step['icon']); ?></div>
                        <h3 style="margin:0 0 4px 0;font-size:15px;"><?php echo esc_html($step['label']); ?></h3>
                        <p style="font-size:11px;color:#71717A;margin:0 0 8px 0;"><?php echo esc_html($step['desc']); ?></p>
                        <p style="font-size:13px;margin:0 0 10px 0;">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo $step['ok'] ? '#10B981' : '#d1d5db'; ?>;margin-right:4px;"></span>
                            <?php echo esc_html($step['count']); ?>
                        </p>
                        <a href="<?php echo esc_url($step['url']); ?>" class="button button-small"><?php echo esc_html($step['btn']); ?> →</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:11px;color:#9ca3af;margin:0 0 20px 0;"><?php echo esc_html__('💡 完整工作流: 在创作中心生成内容 → 一键推送到分发中心多平台发布 → 在自动化中心配置Agent定时执行全流程', 'linked3'); ?></p>

                <?php
                // v11.5.3: 工作流模板预设 (P4) — 一键配置场景化工作流
                $workflow_presets = [
                    'ecommerce' => [
                        'icon' => '🛒', 'name' => __('电商内容矩阵', 'linked3'),
                        'desc' => __('商品描述批量生成 → 多平台发布 → 定时上新', 'linked3'),
                        'steps' => ['创作中心选电商行业', 'CSV批量生成', '分发到社交+电商', 'Agent定时执行'],
                        'urls' => [
                            admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&cw_mode=csv'),
                            admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=distribute'),
                            admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt'),
                        ],
                    ],
                    'selfmedia' => [
                        'icon' => '📱', 'name' => __('自媒体日更', 'linked3'),
                        'desc' => __('热点采集 → AI改写 → 多平台同步', 'linked3'),
                        'steps' => ['采集URL', 'AI改写', '社交分发', 'Agent日更'],
                        'urls' => [
                            admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=publish'),
                            admin_url('admin.php?page=linked3-dashboard&tab=distribution&di_sub=distribute'),
                            admin_url('admin.php?page=linked3-dashboard&tab=automation&au_sub=autogpt'),
                        ],
                    ],
                    'knowledge' => [
                        'icon' => '📚', 'name' => __('知识库建设', 'linked3'),
                        'desc' => __('长文写作 → SEO优化 → 内链建设', 'linked3'),
                        'steps' => ['长文写作', 'SEO关键词', 'Schema标记', '推送收录'],
                        'urls' => [
                            admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=ecosystem&cw_mode=longform'),
                            admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=seo'),
                        ],
                    ],
                    'visual' => [
                        'icon' => '🎨', 'name' => __('视觉内容工厂', 'linked3'),
                        'desc' => __('图示/漫画/视频脚本 → 多形态输出', 'linked3'),
                        'steps' => ['云模版选母版', '图示脚本', '漫画脚本', '视频脚本'],
                        'urls' => [
                            admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=cloud'),
                            admin_url('admin.php?page=linked3-dashboard&tab=creation&cr_sub=visual'),
                        ],
                    ],
                ];
                ?>
                <h2 style="margin-top:20px;"><?php echo esc_html__('🎯 工作流模板预设', 'linked3'); ?><span style="font-size:12px;color:#71717A;font-weight:normal;"><?php echo esc_html__('一键配置场景化工作流', 'linked3'); ?></span></h2>
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:15px 0;">
                    <?php foreach ($workflow_presets as $preset): ?>
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="font-size:22px;"><?php echo esc_html($preset['icon']); ?></span>
                            <strong style="font-size:14px;"><?php echo esc_html($preset['name']); ?></strong>
                        </div>
                        <p style="font-size:12px;color:#71717A;margin:0 0 8px 0;"><?php echo esc_html($preset['desc']); ?></p>
                        <div style="font-size:11px;color:#3F3F46;margin-bottom:10px;">
                            <?php foreach ($preset['steps'] as $i => $step): ?>
                            <span style="display:inline-block;background:#f3f4f6;padding:2px 8px;border-radius:10px;margin:2px 2px;"><?php echo esc_html(($i+1) . '. ' . $step); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button button-small button-primary lk3-wizard-btn"
                            data-preset="<?php echo esc_attr($preset['name']); ?>"
                            data-icon="<?php echo esc_attr($preset['icon']); ?>"
                            data-desc="<?php echo esc_attr($preset['desc']); ?>"
                            data-steps="<?php echo esc_attr(json_encode($preset['steps'])); ?>"
                            data-urls="<?php echo esc_attr(json_encode($preset['urls'])); ?>">
                            🪜 向导式配置 →
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:11px;color:#9ca3af;margin:0 0 20px 0;"><?php echo esc_html__('💡 v11.6.2: 点击"向导式配置"打开4步引导浮层，逐步完成配置并跳转。', 'linked3'); ?></p>

                <!-- v11.6.2: 工作流向导浮层 (G5-P2) -->
                <div id="lk3-wizard-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99998;align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:10px;width:90%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.3);z-index:99999;overflow:hidden;">
                        <div id="lk3-wizard-header" style="padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:10px;">
                            <span id="lk3-wizard-icon" style="font-size:24px;"></span>
                            <div>
                                <div id="lk3-wizard-title" style="font-size:15px;font-weight:600;"></div>
                                <div id="lk3-wizard-desc" style="font-size:11px;color:#71717A;"></div>
                            </div>
                            <button type="button" id="lk3-wizard-close" style="margin-left:auto;background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af;">×</button>
                        </div>
                        <div style="padding:20px;">
                            <!-- 进度条 -->
                            <div style="display:flex;gap:4px;margin-bottom:20px;">
                                <div class="lk3-wizard-dot" data-step="0" style="flex:1;height:4px;background:#2563eb;border-radius:2px;"></div>
                                <div class="lk3-wizard-dot" data-step="1" style="flex:1;height:4px;background:#e5e7eb;border-radius:2px;"></div>
                                <div class="lk3-wizard-dot" data-step="2" style="flex:1;height:4px;background:#e5e7eb;border-radius:2px;"></div>
                                <div class="lk3-wizard-dot" data-step="3" style="flex:1;height:4px;background:#e5e7eb;border-radius:2px;"></div>
                            </div>
                            <div id="lk3-wizard-stepnum" style="font-size:11px;color:#9ca3af;margin-bottom:8px;"><?php echo esc_html__('步骤 1/4', 'linked3'); ?></div>
                            <div id="lk3-wizard-body" style="font-size:14px;line-height:1.6;min-height:60px;"></div>
                        </div>
                        <div style="padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;">
                            <button type="button" id="lk3-wizard-prev" class="button" disabled><?php echo esc_html__('← 上一步', 'linked3'); ?></button>
                            <span id="lk3-wizard-hint" style="font-size:11px;color:#9ca3af;align-self:center;"></span>
                            <button type="button" id="lk3-wizard-next" class="button button-primary"><?php echo esc_html__('下一步 →', 'linked3'); ?></button>
                        </div>
                    </div>
                </div>
                <?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-tab-overview.js ?>
                <?php
