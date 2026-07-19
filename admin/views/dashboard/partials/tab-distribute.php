<?php
/**
 * Dashboard partial: distribute tab.
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

                echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:15px;margin:15px 0;">';
                echo '<h3 style="margin-top:0;">使用场景</h3>';
                echo '<table class="widefat" style="font-size:13px;"><thead><tr><th>场景</th><th>操作</th><th>适用人群</th></tr></thead><tbody>';
                echo '<tr><td><strong>自媒体矩阵</strong></td><td>1文章同步到微博/小红书/掘金/CSDN/知乎</td><td>内容运营团队</td></tr>';
                echo '<tr><td><strong>跨境出海</strong></td><td>商品文同步到Reddit/Twitter/Medium</td><td>独立站卖家</td></tr>';
                echo '<tr><td><strong>B2B工厂</strong></td><td>产品页同步到1688/阿里国际站</td><td>制造业外贸</td></tr>';
                echo '<tr><td><strong>技术博客</strong></td><td>同步到掘金/CSDN/知乎</td><td>开发者</td></tr>';
                echo '</tbody></table>';
                echo '</div>';
                echo '<h2>社交分发</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong>文章发布时自动同步到 13 个社交/B2B 平台。所有凭证加密存储。</p></div>';
                // 内联平台状态
                $dist_configs = (array) get_option(LINKED3_OPTION_PREFIX . 'distribute_configs', []);
                $platform_labels = [
                    'wechat' => '微信公众号', 'weibo' => '微博',
                    'xiaohongshu' => '小红书', 'juejin' => '掘金', 'csdn' => 'CSDN',
                    'twitter' => 'Twitter', 'telegram' => 'Telegram', 'discord' => 'Discord',
                    'blogger' => 'Blogger', 'medium' => 'Medium', 'reddit' => 'Reddit',
                    // v3.2.0: 恢复知乎/SMZDM (MCP 中转模式)
                    'zhihu' => '知乎(MCP)', 'smzdm' => '什么值得买(MCP)',
                    // v3.0.0: B2B 平台 (工厂出海核心渠道)
                    'alibaba' => '阿里国际站', 'alibaba1688' => '1688 开放平台',
                ];
                echo '<table class="widefat striped"><thead><tr><th>平台</th><th>状态</th></tr></thead><tbody>';
                $any_enabled = false;
                foreach ($platform_labels as $slug => $label) {
                    $enabled = !empty($dist_configs[$slug]['enabled']);
                    if ($enabled) $any_enabled = true;
                    echo '<tr><td>' . esc_html($label) . '</td><td>' . ($enabled ? '✓ 已启用' : '— 未配置') . '</td></tr>';
                }
                echo '</tbody></table>';
                // v11.3.4: 全部未配置时显示引导
                if (!$any_enabled) {
                    echo '<div class="notice notice-info inline"><p>💡 还未配置任何分发平台。点击下方"分发设置"绑定账号后, 即可将文章一键同步到 15+ 平台。</p></div>';
                }
                echo '<p><a href="' . esc_url(admin_url('admin.php?page=linked3-distribute')) . '" class="button button-primary">分发设置</a></p>';
