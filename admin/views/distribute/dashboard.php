<?php
if (!defined('ABSPATH')) exit;
/** @var array $configs */
/** @var array $auto */
$nonce = wp_create_nonce('linked3_distribute');
$ajax_url = admin_url('admin-ajax.php');
$platforms = [
    'xiaohongshu' => ['label' => __('小红书', 'linked3'), 'fields' => ['api_url' => __('API 地址(MCP中转)', 'linked3'), 'access_token' => 'Access Token']],
    'weibo'        => ['label' => __('微博', 'linked3'), 'fields' => ['access_token' => 'Access Token']],
    // v3.2.0: 恢复知乎/SMZDM (MCP 中转模式,需自备中转服务)
    'zhihu'        => ['label' => __('知乎 (MCP 中转)', 'linked3'), 'fields' => ['api_url' => __('MCP API 地址', 'linked3'), 'access_token' => __('知乎 Cookie 或 MCP Token', 'linked3'), 'column_id' => __('专栏 ID(可选)', 'linked3')]],
    'smzdm'        => ['label' => __('什么值得买 (MCP 中转)', 'linked3'), 'fields' => ['api_url' => __('MCP API 地址', 'linked3'), 'access_token' => __('SMZDM Cookie 或 MCP Token', 'linked3')]],
    'juejin'       => ['label' => __('掘金', 'linked3'), 'fields' => ['access_token' => 'Access Token', 'category_id' => __('分类ID', 'linked3')]],
    'csdn'         => ['label' => 'CSDN', 'fields' => ['access_token' => __('Cookie(登录后复制)', 'linked3')]],
    'wechat'       => ['label' => __('微信公众号', 'linked3'), 'fields' => ['app_id' => 'App ID', 'app_secret' => 'App Secret', 'default_thumb_media_id' => __('缩略图Media ID', 'linked3')]],
    'blogger'      => ['label' => 'Blogger (Google)', 'fields' => ['access_token' => 'Access Token', 'blog_id' => 'Blog ID']],
    'medium'       => ['label' => 'Medium', 'fields' => ['access_token' => 'Access Token']],
    'reddit'       => ['label' => 'Reddit', 'fields' => ['access_token' => 'Access Token', 'subreddit' => 'Subreddit']],
    // v3.0.0: Twitter 改用 OAuth 1.0a (Bearer 无法发推)
    'twitter'      => ['label' => 'Twitter / X', 'fields' => ['consumer_key' => 'Consumer Key', 'consumer_secret' => 'Consumer Secret', 'access_token' => 'Access Token', 'access_token_secret' => 'Access Token Secret']],
    'telegram'     => ['label' => 'Telegram', 'fields' => ['bot_token' => 'Bot Token', 'chat_id' => 'Chat ID']],
    'discord'      => ['label' => 'Discord', 'fields' => ['webhook_url' => 'Webhook URL', 'bot_name' => __('Bot 名称', 'linked3')]],
    // v3.0.0: B2B 平台 (工厂出海核心渠道)
    'alibaba'      => ['label' => __('阿里国际站', 'linked3'), 'fields' => ['app_key' => 'App Key', 'app_secret' => 'App Secret', 'access_token' => 'Access Token', 'company_id' => 'Company ID']],
    'alibaba1688'  => ['label' => __('1688 开放平台', 'linked3'), 'fields' => ['app_key' => 'App Key', 'app_secret' => 'App Secret', 'access_token' => 'Access Token', 'member_id' => 'Member ID']],
];
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;"><?php echo esc_html__('社交分发', 'linked3'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard')); ?>" class="button">← 返回总览</a>
    </div>
    <div class="notice notice-info inline"><p><strong><?php echo esc_html__('功能说明:', 'linked3'); ?></strong><?php echo esc_html__('文章发布时自动同步到 13 个社交平台。所有凭证加密存储。分发结果可在发布日志查看。', 'linked3'); ?></p></div>

    <h2><?php echo esc_html__('自动分发文章类型', 'linked3'); ?></h2>
    <p>
        <?php foreach (['post' => __('文章', 'linked3'), 'page' => __('页面', 'linked3'), 'product' => __('商品', 'linked3')] as $pt => $label) : ?>
            <label style="margin-right:15px;"><input type="checkbox" class="linked3-dist-auto" data-pt="<?php echo esc_attr($pt); ?>" <?php checked(!empty($auto[$pt])); ?> /> <?php echo esc_html($label); ?></label>
        <?php endforeach; ?>
    </p>

    <?php foreach ($platforms as $slug => $info) :
        $cfg = $configs[$slug] ?? [];
    ?>
    <div class="card" style="max-width:680px;padding:15px;margin:15px 0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;">
        <h3 style="margin-top:0;">
            <input type="checkbox" class="linked3-dist-enabled" data-platform="<?php echo esc_attr($slug); ?>" <?php checked(!empty($cfg['enabled'])); ?> />
            <?php echo esc_html($info['label']); ?>
        </h3>
        <table class="form-table" style="margin:0;">
            <?php foreach ($info['fields'] as $f) : ?>
            <tr>
                <th style="width:180px;"><label><?php echo esc_html(ucfirst(str_replace('_', ' ', $f))); ?></label></th>
                <td><input type="<?php echo strpos($f, 'secret') !== false || strpos($f, 'token') !== false ? 'password' : 'text'; ?>" class="regular-text linked3-dist-field" data-platform="<?php echo esc_attr($slug); ?>" data-field="<?php echo esc_attr($f); ?>" value="<?php echo esc_attr($cfg[$f] ?? ''); ?>" /></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p>
            <button class="button linked3-dist-save" data-platform="<?php echo esc_attr($slug); ?>"><?php echo esc_html__('保存', 'linked3'); ?></button>
            <button class="button linked3-dist-test" data-platform="<?php echo esc_attr($slug); ?>"><?php echo esc_html__('测试', 'linked3'); ?></button>
            <span class="linked3-dist-status" data-platform="<?php echo esc_attr($slug); ?>"></span>
        </p>
    </div>
    <?php endforeach; ?>

    <h2><?php echo esc_html__('手动分发', 'linked3'); ?></h2>
    <p><label><?php echo esc_html__('文章 ID', 'linked3'); ?> <input type="number" id="linked3-dist-pid" /></label>
       <button class="button button-primary" id="linked3-dist-now"><?php echo esc_html__('立即分发', 'linked3'); ?></button></p>
    <div id="linked3-dist-results"></div>

    <?php // v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-distribute-dashboard.js ?>
</div>
