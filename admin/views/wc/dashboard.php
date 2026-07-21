<?php
if (!defined('ABSPATH')) exit;
$nonce = wp_create_nonce('linked3_wc');
$ajax_url = admin_url('admin-ajax.php');
?>
<div class="wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <h1 style="margin:0;">Dashboard</h1>
        <a href="admin.php?page=linked3-dashboard" class="button">← 返回总览</a>
    </div>
    <h1><?php echo esc_html__('WooCommerce AI Generator', 'linked3'); ?></h1>
    <p><?php echo esc_html__('批量生成商品描述和 AI 标注评价。AI 评价按消费者保护法明确标注。', 'linked3'); ?></p>
    <h2><?php echo esc_html__('生成描述', 'linked3'); ?></h2>
    <p><textarea id="linked3-wc-ids" rows="3" class="large-text" placeholder="<?php echo esc_attr__('商品 ID,逗号分隔(如 12,34,56)', 'linked3'); ?>"></textarea></p>
    <p>
        <label><?php echo esc_html__('语气', 'linked3'); ?>
            <select id="linked3-wc-tone"><option value="persuasive"><?php echo esc_html__('说服性', 'linked3'); ?></option><option value="informative"><?php echo esc_html__('信息性', 'linked3'); ?></option></select>
        </label>
        <button class="button button-primary" id="linked3-wc-gen"><?php echo esc_html__('生成', 'linked3'); ?></button>
    </p>
    <h2><?php echo esc_html__('生成 AI 评价(已标注)', 'linked3'); ?></h2>
    <p><label><?php echo esc_html__('商品 ID', 'linked3'); ?> <input type="number" id="linked3-wc-pid" /></label>
       <label><?php echo esc_html__('数量', 'linked3'); ?> <input type="number" id="linked3-wc-count" value="3" min="1" max="10" /></label>
       <button class="button" id="linked3-wc-rev"><?php echo esc_html__('生成评价', 'linked3'); ?></button></p>
    <p class="description"><?php echo esc_html__('注意:AI 评价需在设置中显式启用,将以待审核状态提交并标注 [AI 生成评价]。', 'linked3'); ?></p>

    <h2><?php echo esc_html__('生成 AI 商品图片(DALL-E 3,Pro+)', 'linked3'); ?></h2>
    <p><label><?php echo esc_html__('商品 ID', 'linked3'); ?> <input type="number" id="linked3-wc-img-pid" /></label>
       <label><?php echo esc_html__('Size', 'linked3'); ?>
           <select id="linked3-wc-img-size">
               <option value="1024x1024" selected>Square 1024×1024</option>
               <option value="1792x1024">Landscape 1792×1024</option>
               <option value="1024x1792">Portrait 1024×1792</option>
           </select>
       </label>
       <label><?php echo esc_html__('Quality', 'linked3'); ?>
           <select id="linked3-wc-img-quality">
               <option value="standard" selected>Standard</option>
               <option value="hd">HD</option>
           </select>
       </label>
       <button class="button" id="linked3-wc-img"><?php echo esc_html__('生成图片', 'linked3'); ?></button></p>
    <p class="description"><?php echo esc_html__('根据商品名称+简短描述用 DALL-E 3 生成图片,上传到媒体库并设为商品主图。', 'linked3'); ?></p>

    <div id="linked3-wc-result"></div>
    <script>
    (function(){var n=<?php echo wp_json_encode($nonce);?>,u=<?php echo wp_json_encode($ajax_url);?>;
    function post(a,d,cb){var fd=new FormData();fd.append('action',a);fd.append('nonce',n);Object.keys(d).forEach(function(k){fd.append(k,d[k]);});fetch(u,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb);}
    document.getElementById('linked3-wc-gen').addEventListener('click',function(){
        post('linked3_wc_generate_desc',{product_ids:document.getElementById('linked3-wc-ids').value,tone:document.getElementById('linked3-wc-tone').value},function(r){document.getElementById('linked3-wc-result').textContent=JSON.stringify(r.data);});
    });
    document.getElementById('linked3-wc-rev').addEventListener('click',function(){
        post('linked3_wc_generate_reviews',{product_id:document.getElementById('linked3-wc-pid').value,count:document.getElementById('linked3-wc-count').value},function(r){document.getElementById('linked3-wc-result').textContent=JSON.stringify(r.data);});
    });
    document.getElementById('linked3-wc-img').addEventListener('click',function(){
        document.getElementById('linked3-wc-result').textContent='<?php echo esc_js(__('Generating image (DALL-E 3, may take ~15s)...','linked3'));?>';
        post('linked3_wc_generate_image',{
            product_id:document.getElementById('linked3-wc-img-pid').value,
            size:document.getElementById('linked3-wc-img-size').value,
            quality:document.getElementById('linked3-wc-img-quality').value
        },function(r){document.getElementById('linked3-wc-result').textContent=JSON.stringify(r.data);});
    });
    })();
    </script>
</div>
