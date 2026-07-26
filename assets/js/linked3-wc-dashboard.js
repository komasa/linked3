/**
 * linked3-wc-dashboard.js
 * Extracted from: admin/views/wc/dashboard.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-wc-dashboard.js
 * Localized via wp_localize_script('linked3-wc', 'linked3_wc', {...})
 *   Keys: nonce, ajax_url, val
 */

(function(){
    var nonce = window.linked3_wc && window.linked3_wc.nonce || '';
    var ajax_url = window.linked3_wc && window.linked3_wc.ajax_url || '';
    var val = window.linked3_wc && window.linked3_wc.val || '';


    (function(){var n=linked3_wc.nonce,u=linked3_wc.ajax_url;
    function post(a,d,cb){var fd=new FormData();fd.append('action',a);fd.append('nonce',n);Object.keys(d).forEach(function(k){fd.append(k,d[k]);});fetch(u,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb);}
    document.getElementById('linked3-wc-gen').addEventListener('click',function(){
        post('linked3_wc_generate_desc',{product_ids:document.getElementById('linked3-wc-ids').value,tone:document.getElementById('linked3-wc-tone').value},function(r){document.getElementById('linked3-wc-result').textContent=JSON.stringify(r.data);});
    });
    document.getElementById('linked3-wc-rev').addEventListener('click',function(){
        post('linked3_wc_generate_reviews',{product_id:document.getElementById('linked3-wc-pid').value,count:document.getElementById('linked3-wc-count').value},function(r){document.getElementById('linked3-wc-result').textContent=JSON.stringify(r.data);});
    });
    document.getElementById('linked3-wc-img').addEventListener('click',function(){
        document.getElementById('linked3-wc-result').textContent='linked3_wc.val';
        post('linked3_wc_generate_image',{
            product_id:document.getElementById('linked3-wc-img-pid').value,
            size:document.getElementById('linked3-wc-img-size').value,
            quality:document.getElementById('linked3-wc-img-quality').value
        },function(r){document.getElementById('linked3-wc-result').textContent=JSON.stringify(r.data);});
    });
    })();
    
})();
