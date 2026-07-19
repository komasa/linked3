<?php
/**
 * Dashboard partial: license tab.
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

                if (!class_exists('\\Linked3\\Classes\\License\\Linked3_License_Service')) {
                    // v11.3.6: 模块未加载时给出引导并终止, 避免后续调用未定义类导致致命错误
                    echo '<div class="notice notice-error inline"><p>⚠️ License 模块未加载。可能原因:1) 插件文件不完整 2) PHP 自动加载失败。请重新安装插件或检查 <code>src/autoload.php</code>。</p></div>';
                    echo '<p><a href="' . esc_url(admin_url('plugins.php')) . '" class="button">检查插件状态</a></p>';
                    return;
                }
                $license = \Linked3\Classes\License\Linked3_License_Service::instance();
                $current_key = $license->license_key();
                $current_plan = $license->plan();
                ?>
                <h2>授权与套餐</h2>
                <p>当前套餐:<strong><?php echo esc_html(ucfirst($current_plan)); ?></strong></p>
                <form method="post" action="options.php">
                    <?php settings_fields('linked3_license_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th>License Key</th>
                            <td><input type="text" name="linked3_license_key_input" value="<?php echo esc_attr($current_key); ?>" class="regular-text" />
                                <p class="description">输入 Pro/Premium 授权码升级。留空为免费版。</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('保存授权'); ?>
                </form>
                <h3>套餐对比</h3>
                <table class="widefat striped">
                    <thead><tr><th>功能</th><th>免费版</th><th>Pro</th><th>Premium</th></tr></thead>
                    <tbody>
                        <tr><td>每日 Token</td><td>5万</td><td>300万</td><td>5000万</td></tr>
                        <tr><td>AI Provider</td><td>2 个</td><td>全部</td><td>全部+自定义</td></tr>
                        <tr><td>内容写作</td><td>受限</td><td>完整</td><td>完整</td></tr>
                        <tr><td>发布目标</td><td>1 个</td><td>5 个</td><td>无限</td></tr>
                        <tr><td>自动 Agent</td><td>1 个</td><td>5 个</td><td>无限</td></tr>
                        <tr><td>REST API</td><td>—</td><td>✓</td><td>✓</td></tr>
                        <tr><td>语音 TTS/STT</td><td>—</td><td>—</td><td>✓</td></tr>
                        <tr><td>SLA</td><td>尽力</td><td>24h</td><td>2h</td></tr>
                        <tr><td>月费</td><td>¥0</td><td>¥99</td><td>¥299</td></tr>
                    </tbody>
                </table>
                <?php
