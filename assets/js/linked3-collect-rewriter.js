/**
 * linked3-collect-rewriter.js
 * Extracted from: admin/views/collect/rewriter.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-collect-rewriter.js
 * Localized via wp_localize_script('linked3-rewriter', 'linked3_rewriter', {...})
 *   Keys: nonce, ajax_url
 */

(function(){
    var nonce = window.linked3_rewriter && window.linked3_rewriter.nonce || '';
    var ajax_url = window.linked3_rewriter && window.linked3_rewriter.ajax_url || '';


    (function(){
        var nonce=linked3_rewriter.nonce, ajaxUrl=linked3_rewriter.ajax_url;
        function post(action,data,cb){
            var fd=new FormData();fd.append('action',action);fd.append('nonce',nonce);
            Object.keys(data).forEach(function(k){fd.append(k,data[k]);});
            fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb);
        }
        document.getElementById('linked3-col-scrape').addEventListener('click',function(){
            var url=document.getElementById('linked3-col-url').value;
            post('linked3_collect_scrape',{url:url},function(res){
                if(res.success){
                    document.getElementById('linked3-col-original').value=res.data.title+'\n\n'+res.data.content;
                }else{alert(res.data.message||'错误');}
            });
        });
        // 自定义提示词显示/隐藏
        document.getElementById('linked3-col-tone').addEventListener('change',function(){
            document.getElementById('linked3-col-custom-prompt-box').style.display = this.value === 'custom' ? 'block' : 'none';
        });

        document.getElementById('linked3-col-rewrite').addEventListener('click',function(){
            var content=document.getElementById('linked3-col-original').value;
            post('linked3_collect_rewrite',{
                content:content,
                tone:document.getElementById('linked3-col-tone').value,
                complexity:document.getElementById('linked3-col-complexity').value,
                seo_focus:document.getElementById('linked3-col-seo').checked?1:0,
                simplify:document.getElementById('linked3-col-simplify').checked?1:0,
                custom_prompt:document.getElementById('linked3-col-custom-prompt').value,
                post_status:document.getElementById('linked3-col-rewrite-status').value
            },function(res){
                if(res.success){
                    var pre=document.createElement('pre');pre.style.whiteSpace='pre-wrap';pre.textContent=res.data.content;
                    document.getElementById('linked3-col-output').innerHTML='';document.getElementById('linked3-col-output').appendChild(pre);
                }else{alert(res.data.message||'错误');}
            });
        });
        document.getElementById('linked3-col-bulk').addEventListener('click',function(){
            var urls=document.getElementById('linked3-col-bulk-urls').value.split('\n').map(function(s){return s.trim();}).filter(Boolean);
            if(!urls.length)return;
            var fd=new FormData();fd.append('action','linked3_collect_bulk_rewrite');fd.append('nonce',nonce);
            urls.forEach(function(u){fd.append('urls[]',u);});
            fd.append('post_status',document.getElementById('linked3-col-status').value);
            fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){
                var reader=r.body.getReader();var dec=new TextDecoder();var buf='';
                function pump() {reader.read().then(function(x){
                    if(x.done)return;
                    buf+=dec.decode(x.value,{stream:true});
                    var parts=buf.split('\n\n');buf=parts.pop();
                    parts.forEach(function(p){
                        var m=p.match(/event:\s*(\w+)\ndata:\s*(.*)/s);
                        if(m){try{var d=JSON.parse(m[2]);log(d,m[1]);}catch(e){}}
                    });
                    pump();
                });}
                pump();
            });
            var logEl=document.getElementById('linked3-col-bulk-log');
            function log(d,ev) {
                var div=document.createElement('div');
                div.textContent='['+ev+'] '+(d.url||'')+': '+(d.ok?'✓':'✗')+' '+(d.message||'');
                div.style.color=d.ok?'#080':'#800';
                logEl.appendChild(div);logEl.scrollTop=logEl.scrollHeight;
            }
        });
    })();
    
})();
