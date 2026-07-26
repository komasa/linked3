/**
 * linked3-dashboard-tabs.js
 * Extracted from: admin/views/dashboard/tabs.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-dashboard-tabs.js
 */


    (function(){
        var commands = window.linked3_dashboard_tabs && window.linked3_dashboard_tabs.cmdk_commands || [];
        var overlay = document.getElementById('lk3-cmdk-overlay');
        var input = document.getElementById('lk3-cmdk-input');
        var list = document.getElementById('lk3-cmdk-list');
        var trigger = document.getElementById('lk3-cmdk-trigger');
        var selectedIdx = 0;

        var RECENT_KEY = 'lk3_cmdk_recent';
        var recent = [];
        try { recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch(e) { recent = []; }

        function saveRecent(url, label){
            recent = recent.filter(function(r){ return r.url !== url; });
            recent.unshift({url: url, label: label, ts: Date.now()});
            recent = recent.slice(0, 5);
            try { localStorage.setItem(RECENT_KEY, JSON.stringify(recent)); } catch(e) {}
        }

        function render(filter){
            filter = (filter || '').toLowerCase();
            var html = '';

            if (!filter && recent.length > 0) {
                html += '<div style="padding:6px 16px;background:#f9fafb;font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.5px;">🕐 最近访问</div>';
                recent.forEach(function(r, i){
                    html += '<div class="lk3-cmdk-item" data-url="'+r.url+'" data-idx="'+i+'" style="padding:10px 16px;cursor:pointer;border-bottom:1px solid #f3f4f6;'+(i===0?'background:#eff6ff;':'')+'">'
                        + '<div style="font-size:13px;font-weight:500;">'+r.label+'</div>'
                        + '<div style="font-size:10px;color:#9ca3af;">'+new Date(r.ts).toLocaleString()+'</div>'
                        + '</div>';
                });
                html += '<div style="padding:6px 16px;background:#f9fafb;font-size:10px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.5px;">📋 全部命令</div>';
            }

            var matched = commands.filter(function(c){
                return c.label.toLowerCase().indexOf(filter) > -1 || c.desc.toLowerCase().indexOf(filter) > -1;
            });
            if (matched.length === 0 && !recent.length) {
                list.innerHTML = '<div style="padding:24px;text-align:center;color:#9ca3af;">无匹配项</div>';
                return;
            }
            var offsetBase = (!filter && recent.length > 0) ? recent.length : 0;
            html += matched.map(function(c, i){
                var idx = i + offsetBase;
                var is_first = idx === 0;
                return '<div class="lk3-cmdk-item" data-url="'+c.url+'" data-idx="'+idx+'" style="padding:10px 16px;cursor:pointer;border-bottom:1px solid #f3f4f6;'+(is_first?'background:#eff6ff;':'')+'">'
                    + '<div style="font-size:13px;font-weight:500;">'+c.label+'</div>'
                    + '<div style="font-size:11px;color:#6b7280;">'+c.desc+'</div>'
                    + '</div>';
            }).join('');
            list.innerHTML = html;
            selectedIdx = 0;
            list.querySelectorAll('.lk3-cmdk-item').forEach(function(el){
                el.addEventListener('mouseenter', function(){
                    list.querySelectorAll('.lk3-cmdk-item').forEach(function(x){x.style.background='';});
                    this.style.background = '#eff6ff';
                    selectedIdx = parseInt(this.getAttribute('data-idx'));
                });
                el.addEventListener('click', function(){
                    var url = this.getAttribute('data-url');
                    var labelEl = this.querySelector('div');
                    saveRecent(url, labelEl ? labelEl.textContent : url);
                    window.location.href = url;
                });
            });
        }
        function open() { overlay.style.display='flex'; input.value=''; render(''); setTimeout(function(){input.focus();},50); }
        function close() { overlay.style.display='none'; }

        if (trigger) trigger.addEventListener('click', open);
        input.addEventListener('input', function(){ render(this.value); });
        input.addEventListener('keydown', function(e){
            var items = list.querySelectorAll('.lk3-cmdk-item');
            if (e.key === 'ArrowDown') { e.preventDefault(); if (selectedIdx < items.length-1) { selectedIdx++; items[selectedIdx].scrollIntoView({block:'nearest'}); } }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (selectedIdx > 0) { selectedIdx--; items[selectedIdx].scrollIntoView({block:'nearest'}); } }
            else if (e.key === 'Enter') {
                e.preventDefault();
                var it = items[selectedIdx];
                if (it) {
                    var url = it.getAttribute('data-url');
                    var labelEl = it.querySelector('div');
                    saveRecent(url, labelEl ? labelEl.textContent : url);
                    window.location.href = url;
                }
            }
            else if (e.key === 'Escape') { close(); }
            if (items.length) { items.forEach(function(x){x.style.background='';}); items[selectedIdx] && (items[selectedIdx].style.background='#eff6ff'); }
        });
        overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });

        document.addEventListener('keydown', function(e){
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); overlay.style.display === 'flex' ? close() : open(); }
        });
    })();
    