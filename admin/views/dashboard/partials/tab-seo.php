<?php
/**
 * Dashboard partial: seo tab.
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

                echo '<h2>SEO 优化</h2>';
                echo '<div class="notice notice-info inline"><p><strong>功能说明:</strong>关键词提取(中文 TF-IDF + TextRank)、智能内链(3种策略)、Schema Markup(Article/FAQ/Product/HowTo)、多搜索引擎推送(百度/Bing/Google/头条/Indexnow)、SEO 评分卡(9维度)。</p></div>';
                // v3.1.0: 直接 include SEO dashboard view (消除重复渲染)
                $seo_view = LINKED3_DIR . 'admin/views/seo/dashboard.php';
                if (file_exists($seo_view)) {
                    // view 内部需要 $configs (推送配置) 和 $auto (自动检测)
                    $configs = [];
                    foreach (['baidu','bing','google','toutiao','indexnow'] as $engine) {
                        $configs[$engine] = get_option(LINKED3_OPTION_PREFIX . 'push_' . $engine, []);
                    }
                    $auto = ['yoast' => class_exists('WPSEO_Options'), 'rank_math' => class_exists('RankMath'), 'aioseo' => class_exists('AIOSEO_Plugin_Utility')];
                    include $seo_view;
                } else {
                    // v11.3.6: SEO模块未加载时给出排查引导
                    echo '<div class="notice notice-error inline"><p>⚠️ SEO 模块视图文件缺失: <code>admin/views/seo/dashboard.php</code></p></div>';
                    echo '<p>可能原因:1) 插件文件不完整(重新安装) 2) 文件权限问题。请前往 <a href="' . esc_url(admin_url('admin.php?page=linked3-dashboard&tab=system&sy_sub=security')) . '">安全审计</a> 检查文件完整性。</p>';
                }
                // 推送日志链接 + 配置推送引擎按钮
                echo '<p style="margin-top:15px;">';
                echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-seo-settings')) . '" class="button button-primary">配置推送引擎</a> ';
                echo '<a href="' . esc_url(admin_url('admin.php?page=linked3-seo-push-logs')) . '" class="button">查看推送日志</a>';
                echo '</p>';

                // GEO 增强
                echo '<hr style="margin:20px 0;border:none;border-top:2px solid #ddd;">';
                echo '<h3>GEO 增强 (AI 搜索引擎优化)</h3>';
                echo '<p>针对 ChatGPT/Perplexity/Gemini/Claude 优化,让 AI 引用你的内容。</p>';
                echo '<table class="form-table">';
                echo '<tr><th>llms.txt</th><td><a href="' . esc_url(home_url('/llms.txt')) . '" target="_blank">' . esc_html(home_url('/llms.txt')) . '</a></td></tr>';
                echo '<tr><th>AI 友好 meta</th><td>已自动注入 (ai-content-optimizable / citation_optimized)</td></tr>';
                echo '<tr><th>FAQ Schema</th><td>已自动增强 (AI 搜索引擎优先引用 FAQ)</td></tr>';
                echo '</table>';
                echo '<p class="description">GEO 功能自动启用,无需配置。Perplexity/Bing API 请在「API 设置」tab 配置。</p>';
