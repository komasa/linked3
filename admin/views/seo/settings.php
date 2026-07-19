<?php
/**
 * Display: SEO Settings.
 *
 * @package Linked3
 * @subpackage Admin\Views\SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

// Linked3_Crypto is loaded by the dependency loader (v0.2.0+). The class
// exists check is defensive — settings.php can be included early.
$crypto_available = class_exists('\Linked3\Includes\Linked3_Crypto');

// Read current settings (default-seeded by migration 0.4.1). Sensitive
// fields are stored encrypted (v0.5.0 hardening) — decrypt for display.
$raw_baidu = (array) get_option(LINKED3_OPTION_PREFIX . 'push_baidu', ['site' => '', 'token' => '']);
$raw_toutiao = (array) get_option(LINKED3_OPTION_PREFIX . 'push_toutiao', ['site' => '', 'user_name' => '', 'resource_name' => '']);
$raw_google = (array) get_option(LINKED3_OPTION_PREFIX . 'push_google', ['client_email' => '', 'private_key' => '']);
$indexnow_key = (string) get_option(LINKED3_OPTION_PREFIX . 'push_indexnow_key', '');

$baidu = [
    'site'  => (string) ($raw_baidu['site'] ?? ''),
    'token' => $crypto_available && !empty($raw_baidu['token'])
        ? \Linked3\Includes\Linked3_Crypto::decrypt((string) $raw_baidu['token'])
        : (string) ($raw_baidu['token'] ?? ''),
];
$toutiao = [
    'site'          => (string) ($raw_toutiao['site'] ?? ''),
    'user_name'     => $crypto_available && !empty($raw_toutiao['user_name'])
        ? \Linked3\Includes\Linked3_Crypto::decrypt((string) $raw_toutiao['user_name'])
        : (string) ($raw_toutiao['user_name'] ?? ''),
    'resource_name' => $crypto_available && !empty($raw_toutiao['resource_name'])
        ? \Linked3\Includes\Linked3_Crypto::decrypt((string) $raw_toutiao['resource_name'])
        : (string) ($raw_toutiao['resource_name'] ?? ''),
];
$google = [
    'client_email' => $crypto_available && !empty($raw_google['client_email'])
        ? \Linked3\Includes\Linked3_Crypto::decrypt((string) $raw_google['client_email'])
        : (string) ($raw_google['client_email'] ?? ''),
    'private_key'  => $crypto_available && !empty($raw_google['private_key'])
        ? \Linked3\Includes\Linked3_Crypto::decrypt((string) $raw_google['private_key'])
        : (string) ($raw_google['private_key'] ?? ''),
];
$cfg = [];
if (class_exists('\Linked3\Classes\SEO\SEOConfig')) {
    $cfg = \Linked3\Classes\SEO\SEOConfig::all();
}

$adapter = null;
if (class_exists('\Linked3\Classes\SEO\Adapter\SEOAdapterDetector')) {
    $adapter = \Linked3\Classes\SEO\Adapter\SEOAdapterDetector::resolve();
}
$available_adapters = \Linked3\Classes\SEO\Adapter\SEOAdapterDetector::available();

if (isset($_POST['linked3_seo_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['linked3_seo_settings_nonce'])), 'linked3_seo_settings')) {
    // Encrypt sensitive fields before persisting (v0.5.0 hardening, §5).
    // Linked3_Crypto::encrypt is idempotent (encrypts plaintext, leaves
    // "enc::" payloads unchanged) and fails-open (returns plaintext if
    // OpenSSL is unavailable) so the plugin still works on legacy hosts.
    $baidu_token_raw = sanitize_text_field($_POST['baidu_token'] ?? '');
    $baidu = [
        'site'  => sanitize_text_field($_POST['baidu_site'] ?? ''),
        'token' => $baidu_token_raw !== '' && $crypto_available
            ? \Linked3\Includes\Linked3_Crypto::encrypt($baidu_token_raw)
            : $baidu_token_raw,
    ];
    update_option(LINKED3_OPTION_PREFIX . 'push_baidu', $baidu);
    // Re-decrypt for form re-display.
    $baidu['token'] = $baidu_token_raw;

    $toutiao_user_raw     = sanitize_text_field($_POST['toutiao_user_name'] ?? '');
    $toutiao_resource_raw = sanitize_text_field($_POST['toutiao_resource_name'] ?? '');
    $toutiao = [
        'site'          => sanitize_text_field($_POST['toutiao_site'] ?? ''),
        'user_name'     => $toutiao_user_raw !== '' && $crypto_available
            ? \Linked3\Includes\Linked3_Crypto::encrypt($toutiao_user_raw)
            : $toutiao_user_raw,
        'resource_name' => $toutiao_resource_raw !== '' && $crypto_available
            ? \Linked3\Includes\Linked3_Crypto::encrypt($toutiao_resource_raw)
            : $toutiao_resource_raw,
    ];
    update_option(LINKED3_OPTION_PREFIX . 'push_toutiao', $toutiao);
    $toutiao['user_name']     = $toutiao_user_raw;
    $toutiao['resource_name'] = $toutiao_resource_raw;

    $google_email_raw = sanitize_email($_POST['google_client_email'] ?? '');
    $google_key_raw   = sanitize_textarea_field($_POST['google_private_key'] ?? '');
    $google = [
        'client_email' => $google_email_raw !== '' && $crypto_available
            ? \Linked3\Includes\Linked3_Crypto::encrypt($google_email_raw)
            : $google_email_raw,
        'private_key'  => $google_key_raw !== '' && $crypto_available
            ? \Linked3\Includes\Linked3_Crypto::encrypt($google_key_raw)
            : $google_key_raw,
    ];
    update_option(LINKED3_OPTION_PREFIX . 'push_google', $google);
    $google['client_email'] = $google_email_raw;
    $google['private_key']  = $google_key_raw;

    if (!empty($_POST['indexnow_key'])) {
        update_option(LINKED3_OPTION_PREFIX . 'push_indexnow_key', sanitize_text_field($_POST['indexnow_key']));
        $indexnow_key = sanitize_text_field($_POST['indexnow_key']);
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'linked3') . '</p></div>';
}
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Settings</h1>
        <a href="admin.php?page=linked3-dashboard" class="button">← 返回总览</a>
    </div>
    <h1><?php echo esc_html__('Linked3 SEO 设置', 'linked3'); ?></h1>

    <h2><?php echo esc_html__('SEO Adapter Detection', 'linked3'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('适配器', 'linked3'); ?></th>
                <th><?php echo esc_html__('默认?', 'linked3'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($available_adapters as $row) :
            list($slug, $label, $active) = explode('|', $row);
            $is_current = $adapter && $adapter->slug() === $slug;
        ?>
            <tr<?php echo $is_current ? ' style="background:#e7f7e7;"' : ''; ?>>
                <td><code><?php echo esc_html($slug); ?></code> — <?php echo esc_html($label); ?></td>
                <td><?php echo $active === '1' ? esc_html__('是', 'linked3') : esc_html__('否', 'linked3'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" action="">
        <?php wp_nonce_field('linked3_seo_settings', 'linked3_seo_settings_nonce'); ?>
        <h2><?php echo esc_html__('百度推送', 'linked3'); ?></h2>
        <p>
            <label><?php echo esc_html__('Site', 'linked3'); ?>
                <input type="text" name="baidu_site" value="<?php echo esc_attr($baidu['site'] ?? ''); ?>" class="regular-text" />
            </label>
        </p>
        <p>
            <label><?php echo esc_html__('Token', 'linked3'); ?>
                <input type="text" name="baidu_token" value="<?php echo esc_attr($baidu['token'] ?? ''); ?>" class="regular-text" />
            </label>
        </p>

        <h2><?php echo esc_html__('神马/头条推送', 'linked3'); ?></h2>
        <p>
            <label><?php echo esc_html__('Site', 'linked3'); ?>
                <input type="text" name="toutiao_site" value="<?php echo esc_attr($toutiao['site'] ?? ''); ?>" class="regular-text" />
            </label>
        </p>
        <p>
            <label><?php echo esc_html__('User Name', 'linked3'); ?>
                <input type="text" name="toutiao_user_name" value="<?php echo esc_attr($toutiao['user_name'] ?? ''); ?>" class="regular-text" />
            </label>
        </p>
        <p>
            <label><?php echo esc_html__('Resource Name', 'linked3'); ?>
                <input type="text" name="toutiao_resource_name" value="<?php echo esc_attr($toutiao['resource_name'] ?? ''); ?>" class="regular-text" />
            </label>
        </p>

        <h2><?php echo esc_html__('Google 索引 API(JWT)', 'linked3'); ?></h2>
        <p>
            <label><?php echo esc_html__('服务账号邮箱', 'linked3'); ?>
                <input type="email" name="google_client_email" value="<?php echo esc_attr($google['client_email'] ?? ''); ?>" class="regular-text" />
            </label>
        </p>
        <p>
            <label><?php echo esc_html__('私钥(PEM)', 'linked3'); ?>
                <textarea name="google_private_key" rows="6" class="large-text code"><?php echo esc_textarea($google['private_key'] ?? ''); ?></textarea>
            </label>
        </p>

        <h2><?php echo esc_html__('Indexnow(Bing / Yandex / Naver)', 'linked3'); ?></h2>
        <p>
            <label><?php echo esc_html__('Verification Key', 'linked3'); ?>
                <input type="text" name="indexnow_key" value="<?php echo esc_attr($indexnow_key); ?>" class="regular-text" placeholder="<?php esc_attr_e('留空则在首次推送时自动生成', 'linked3'); ?>" />
            </label>
        </p>

        <p><button type="submit" class="button button-primary"><?php echo esc_html__('保存设置', 'linked3'); ?></button></p>
    </form>

    <!-- v3.1.0: SEO 增强 UI (内链/Schema/外链) -->
    <hr style="margin:30px 0;border:none;border-top:2px solid #ddd;">
    <h2>SEO 增强 (v3.1.0)</h2>
    <p>智能内链 / Schema Markup / 外链处理。这些功能后端类已实现,这里是配置开关。</p>

    <?php
    $seo_enhance = wp_parse_args((array) get_option(LINKED3_OPTION_PREFIX . 'seo_enhance', []), [
        'interlink_enabled' => 1,
        'interlink_strategy' => 'popular',  // popular / recent / frequent
        'interlink_max_per_post' => 5,
        'schema_article' => 1,
        'schema_faq' => 1,
        'schema_howto' => 0,
        'schema_product' => 0,
        'external_link_nofollow' => 1,
        'external_link_target_blank' => 1,
        'external_link_whitelist' => '',
    ]);
    $nonce_enhance = wp_create_nonce('linked3_settings');
    ?>
    <form id="linked3-seo-enhance-form">
        <h3>智能内链</h3>
        <p>自动给文章内容中的关键词加内链,指向站内其他相关文章。</p>
        <table class="form-table">
            <tr>
                <th>启用</th>
                <td><label><input type="checkbox" id="se_interlink_enabled" <?php checked($seo_enhance['interlink_enabled']); ?> /> 自动添加内链</label></td>
            </tr>
            <tr>
                <th>策略</th>
                <td>
                    <select id="se_interlink_strategy">
                        <option value="popular" <?php selected($seo_enhance['interlink_strategy'], 'popular'); ?>>热门文章 (访问量高优先)</option>
                        <option value="recent" <?php selected($seo_enhance['interlink_strategy'], 'recent'); ?>>最近文章 (新文优先)</option>
                        <option value="frequent" <?php selected($seo_enhance['interlink_strategy'], 'frequent'); ?>>高频文章 (内链多优先)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>每篇最大内链数</th>
                <td><input type="number" id="se_interlink_max" value="<?php echo esc_attr($seo_enhance['interlink_max_per_post']); ?>" min="1" max="20" /></td>
            </tr>
        </table>

        <h3>Schema Markup (结构化数据)</h3>
        <p>自动输出 JSON-LD 结构化数据到文章页 head,提升搜索引擎富结果展示。</p>
        <table class="form-table">
            <tr>
                <th>Article</th>
                <td><label><input type="checkbox" id="se_schema_article" <?php checked($seo_enhance['schema_article']); ?> /> 文章类型 (默认开)</label></td>
            </tr>
            <tr>
                <th>FAQ</th>
                <td><label><input type="checkbox" id="se_schema_faq" <?php checked($seo_enhance['schema_faq']); ?> /> 常见问题 (文章含 FAQ 段落时自动生成)</label></td>
            </tr>
            <tr>
                <th>HowTo</th>
                <td><label><input type="checkbox" id="se_schema_howto" <?php checked($seo_enhance['schema_howto']); ?> /> 教程 (文章含步骤段落时自动生成)</label></td>
            </tr>
            <tr>
                <th>Product</th>
                <td><label><input type="checkbox" id="se_schema_product" <?php checked($seo_enhance['schema_product']); ?> /> 产品 (WooCommerce 产品页)</label></td>
            </tr>
        </table>

        <h3>外链处理</h3>
        <p>对外部链接自动添加 nofollow + target=_blank,保留权重。白名单内的域名不加 nofollow。</p>
        <table class="form-table">
            <tr>
                <th>添加 nofollow</th>
                <td><label><input type="checkbox" id="se_ext_nofollow" <?php checked($seo_enhance['external_link_nofollow']); ?> /> 自动给外链加 rel="nofollow"</label></td>
            </tr>
            <tr>
                <th>新窗口打开</th>
                <td><label><input type="checkbox" id="se_ext_target" <?php checked($seo_enhance['external_link_target_blank']); ?> /> 自动给外链加 target="_blank"</label></td>
            </tr>
            <tr>
                <th>白名单域名</th>
                <td>
                    <textarea id="se_ext_whitelist" rows="3" class="large-text" placeholder="每行一个域名,如:&#10;example.com&#10;trusted-site.org"><?php echo esc_textarea($seo_enhance['external_link_whitelist']); ?></textarea>
                    <p class="description">白名单内的域名不加 nofollow (友好站点)。</p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" class="button button-primary" id="linked3-save-seo-enhance">保存 SEO 增强</button>
            <span id="linked3-seo-enhance-status" style="margin-left:10px;"></span>
        </p>
    </form>
    <script>
    document.getElementById('linked3-save-seo-enhance').addEventListener('click', function(){
        var btn = this;
        var s = document.getElementById('linked3-seo-enhance-status');
        btn.disabled = true;
        s.textContent = '保存中...';
        s.style.color = '#666';
        var fd = new FormData();
        fd.append('action', 'linked3_save_seo_enhance');
        fd.append('nonce', <?php echo wp_json_encode($nonce_enhance); ?>);
        fd.append('interlink_enabled', document.getElementById('se_interlink_enabled').checked ? 1 : 0);
        fd.append('interlink_strategy', document.getElementById('se_interlink_strategy').value);
        fd.append('interlink_max_per_post', document.getElementById('se_interlink_max').value);
        fd.append('schema_article', document.getElementById('se_schema_article').checked ? 1 : 0);
        fd.append('schema_faq', document.getElementById('se_schema_faq').checked ? 1 : 0);
        fd.append('schema_howto', document.getElementById('se_schema_howto').checked ? 1 : 0);
        fd.append('schema_product', document.getElementById('se_schema_product').checked ? 1 : 0);
        fd.append('external_link_nofollow', document.getElementById('se_ext_nofollow').checked ? 1 : 0);
        fd.append('external_link_target_blank', document.getElementById('se_ext_target').checked ? 1 : 0);
        fd.append('external_link_whitelist', document.getElementById('se_ext_whitelist').value);
        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(res){
                btn.disabled = false;
                s.textContent = res.success ? '✓ ' + (res.data.message || '已保存') : '✗ ' + (res.data.message || '保存失败');
                s.style.color = res.success ? '#080' : '#800';
                setTimeout(function(){ s.textContent = ''; }, 3000);
            })
            .catch(function(e){
                btn.disabled = false;
                s.textContent = '✗ 网络错误: ' + e.message;
                s.style.color = '#800';
            });
    });
    </script>
</div>
