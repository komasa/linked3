<?php
if (!defined('ABSPATH')) exit;
/** @var array $configs */
/** @var array $auto */
$nonce = wp_create_nonce('linked3_distribute');
$ajax_url = admin_url('admin-ajax.php');
$platforms = [
    'xiaohongshu' => ['label' => '小红书', 'fields' => ['api_url' => 'API 地址(MCP中转)', 'access_token' => 'Access Token']],
    'weibo'        => ['label' => '微博', 'fields' => ['access_token' => 'Access Token']],
    // v3.2.0: 恢复知乎/SMZDM (MCP 中转模式,需自备中转服务)
    'zhihu'        => ['label' => '知乎 (MCP 中转)', 'fields' => ['api_url' => 'MCP API 地址', 'access_token' => '知乎 Cookie 或 MCP Token', 'column_id' => '专栏 ID(可选)']],
    'smzdm'        => ['label' => '什么值得买 (MCP 中转)', 'fields' => ['api_url' => 'MCP API 地址', 'access_token' => 'SMZDM Cookie 或 MCP Token']],
    'juejin'       => ['label' => '掘金', 'fields' => ['access_token' => 'Access Token', 'category_id' => '分类ID']],
    'csdn'         => ['label' => 'CSDN', 'fields' => ['access_token' => 'Cookie(登录后复制)']],
    'wechat'       => ['label' => '微信公众号', 'fields' => ['app_id' => 'App ID', 'app_secret' => 'App Secret', 'default_thumb_media_id' => '缩略图Media ID']],
    'blogger'      => ['label' => 'Blogger (Google)', 'fields' => ['access_token' => 'Access Token', 'blog_id' => 'Blog ID']],
    'medium'       => ['label' => 'Medium', 'fields' => ['access_token' => 'Access Token']],
    'reddit'       => ['label' => 'Reddit', 'fields' => ['access_token' => 'Access Token', 'subreddit' => 'Subreddit']],
    // v3.0.0: Twitter 改用 OAuth 1.0a (Bearer 无法发推)
    'twitter'      => ['label' => 'Twitter / X', 'fields' => ['consumer_key' => 'Consumer Key', 'consumer_secret' => 'Consumer Secret', 'access_token' => 'Access Token', 'access_token_secret' => 'Access Token Secret']],
    'telegram'     => ['label' => 'Telegram', 'fields' => ['bot_token' => 'Bot Token', 'chat_id' => 'Chat ID']],
    'discord'      => ['label' => 'Discord', 'fields' => ['webhook_url' => 'Webhook URL', 'bot_name' => 'Bot 名称']],
    // v3.0.0: B2B 平台 (工厂出海核心渠道)
    'alibaba'      => ['label' => '阿里国际站', 'fields' => ['app_key' => 'App Key', 'app_secret' => 'App Secret', 'access_token' => 'Access Token', 'company_id' => 'Company ID']],
    'alibaba1688'  => ['label' => '1688 开放平台', 'fields' => ['app_key' => 'App Key', 'app_secret' => 'App Secret', 'access_token' => 'Access Token', 'member_id' => 'Member ID']],
];
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">社交分发</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=linked3-dashboard')); ?>" class="button">← 返回总览</a>
    </div>
    <div class="notice notice-info inline"><p><strong>功能说明:</strong>文章发布时自动同步到 13 个社交平台。所有凭证加密存储。分发结果可在发布日志查看。</p></div>

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

    <script>
    (function(){var n=<?php echo wp_json_encode($nonce);?>,u=<?php echo wp_json_encode($ajax_url);?>;
    function post(a,d,cb){var fd=new FormData();fd.append('action',a);fd.append('nonce',n);Object.keys(d).forEach(function(k){fd.append(k,d[k]);});fetch(u,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb);}
    function config(platform){
        var d={platform:platform};
        d.enabled=document.querySelector('.linked3-dist-enabled[data-platform="'+platform+'"]').checked?'1':'';
        document.querySelectorAll('.linked3-dist-field[data-platform="'+platform+'"]').forEach(function(el){d[el.dataset.field]=el.value;});
        var auto=[];document.querySelectorAll('.linked3-dist-auto:checked').forEach(function(el){auto.push(el.dataset.pt);});d['auto_types[]']=auto;
        return d;
    }
    document.querySelectorAll('.linked3-dist-save').forEach(function(b){b.addEventListener('click',function(){
        var p=b.dataset.platform;var d=config(p);
        // Flatten auto_types for FormData.
        var fd=new FormData();fd.append('action','linked3_distribute_save');fd.append('nonce',n);
        Object.keys(d).forEach(function(k){if(k==='auto_types[]'){d[k].forEach(function(v){fd.append('auto_types[]',v);});}else{fd.append(k,d[k]);}});
        fetch(u,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){var s=document.querySelector('.linked3-dist-status[data-platform="'+p+'"]');s.textContent=r.success?'<?php echo esc_js(__('Saved','linked3'));?>':'Error';s.style.color=r.success?'#080':'#800';});
    });});
    document.querySelectorAll('.linked3-dist-test').forEach(function(b){b.addEventListener('click',function(){
        var p=b.dataset.platform;var d=config(p);
        var fd=new FormData();fd.append('action','linked3_distribute_test');fd.append('nonce',n);
        Object.keys(d).forEach(function(k){if(k!=='auto_types[]')fd.append(k,d[k]);});
        fetch(u,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){var s=document.querySelector('.linked3-dist-status[data-platform="'+p+'"]');s.textContent=r.success?(r.data.message||'OK'):(r.data.message||'Fail');s.style.color=r.success?'#080':'#800';});
    });});
    document.getElementById('linked3-dist-now').addEventListener('click',function(){
        post('linked3_distribute_now',{post_id:document.getElementById('linked3-dist-pid').value},function(r){
            var el=document.getElementById('linked3-dist-results');
            if(r.success&&r.data.results){el.innerHTML=r.data.results.map(function(x){return '<p>'+x.platform+': '+(x.ok?'<span style="color:#080">OK</span>':'<span style="color:#800">'+x.message+'</span>')+'</p>';}).join('');}
            else{el.textContent=JSON.stringify(r.data);}
        });
    });
    })();
    </script>
</div>
