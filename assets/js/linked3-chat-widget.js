/**
 * Linked3 Chat Widget — Frontend JS
 * Extracted from: src/Classes/Chat/Shortcode/ChatShortcode.php
 * v29.1.0 Step 4: Inline JS extracted to assets/js/linked3-chat-widget.js
 *
 * Auto-initializes all .linked3-chat elements on the page.
 * Data attributes on the widget container provide per-instance config:
 *   data-nonce, data-ajax-url, data-guest, data-session-id, data-bot-id
 * Localized via wp_localize_script('linked3-chat-widget', 'linked3_chat_i18n', {...})
 */
(function() {
    'use strict';

    function initWidget(w) {
        if (!w || w.dataset.init) return;
        w.dataset.init = '1';

        var nonce = w.dataset.nonce;
        var ajaxUrl = w.dataset.ajaxUrl;
        var guest = w.dataset.guest === '1';
        var sid = w.dataset.sessionId;
        var botId = w.dataset.botId;
        var msgs = w.querySelector('.linked3-chat-messages');
        var src = w.querySelector('.linked3-chat-sources');
        var toggle = w.querySelector('.linked3-chat-toggle');
        var win = w.querySelector('.linked3-chat-window');
        var close = w.querySelector('.linked3-chat-close');
        var txt = w.querySelector('.linked3-chat-text');
        var send = w.querySelector('.linked3-chat-send');

        if (toggle) toggle.addEventListener('click', function() { win.style.display = ''; });
        if (close) close.addEventListener('click', function() { win.style.display = 'none'; });

        function scroll() { msgs.scrollTop = msgs.scrollHeight; }

        function addMsg(role, text) {
            var d = document.createElement('div');
            d.className = 'linked3-chat-msg linked3-chat-' + role;
            d.textContent = text;
            msgs.appendChild(d);
            scroll();
        }

        function sendMsg() {
            var m = txt.value.trim();
            if (!m) return;
            addMsg('user', m);
            txt.value = '';
            var fd = new FormData();
            fd.append('action', 'linked3_chat_send');
            fd.append('nonce', nonce);
            fd.append('session_id', sid);
            fd.append('message', m);
            fd.append('bot_id', botId);
            if (guest) fd.append('guest', '1');
            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        addMsg('bot', res.data.reply);
                        if (res.data.sources && res.data.sources.length) {
                            var label = (window.linked3_chat_i18n && window.linked3_chat_i18n.sources_label) || 'Sources:';
                            src.innerHTML = '<strong>' + label + '</strong> ' + res.data.sources.map(function(s) {
                                return '<a href="' + s.url + '" target="_blank">' + s.title + '</a>';
                            }).join(', ');
                            src.style.display = '';
                        } else {
                            src.style.display = 'none';
                        }
                    } else {
                        addMsg('bot', res.data && res.data.message ? res.data.message : 'Error');
                    }
                })
                .catch(function(e) { addMsg('bot', 'Network error'); });
        }

        send.addEventListener('click', sendMsg);
        txt.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMsg();
            }
        });
    }

    function initAll() {
        document.querySelectorAll('.linked3-chat').forEach(initWidget);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
