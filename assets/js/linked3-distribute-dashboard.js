/**
 * linked3-distribute-dashboard.js
 * Extracted from: admin/views/distribute/dashboard.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-distribute-dashboard.js
 * Localized via wp_localize_script('linked3-distribute', 'linked3_distribute', {...})
 *   Keys: nonce, ajax_url, val
 */

(function(){
    var nonce = window.linked3_distribute && window.linked3_distribute.nonce || '';
    var ajax_url = window.linked3_distribute && window.linked3_distribute.ajax_url || '';
    var val = window.linked3_distribute && window.linked3_distribute.val || '';


    (function(){var n=linked3_distribute.nonce,u=linked3_distribute.ajax_url;
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
        fetch(u,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){var s=document.querySelector('.linked3-dist-status[data-platform="'+p+'"]');s.textContent=r.success?'linked3_distribute.val':'Error';s.style.color=r.success?'#080':'#800';});
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
    
})();
