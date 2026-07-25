/**
 * Linked3 Fetch v2 — v28 PR-07
 *
 * v1 (v19.3.1) 解决了 HTML 错误页解析问题。
 * v2 (v28.0) 增加超时控制、自动重试、FormData 支持、playground 适配。
 *
 * 新增特性:
 *   1. 可配置 timeout (默认 45s, 适配 playground 60s 限制)
 *   2. 自动重试 (默认 1 次, 仅对网络错误重试)
 *   3. FormData 支持 (文件上传场景)
 *   4. 结构化错误类型 (NetworkError / TimeoutError / ServerError / AuthError)
 *   5. 全局错误总线 (window.linked3FetchError 事件)
 *
 * 用法:
 *   // 基础
 *   linked3Fetch('linked3_cos_evolve_gen', { problem: '...' })
 *     .then(data => console.log(data))
 *     .catch(err => console.error(err));
 *
 *   // 带超时和重试
 *   linked3Fetch('linked3_book_factory_start', { project_id: '...' }, {
 *     timeout: 45000,
 *     retry: 2,
 *   });
 *
 *   // FormData
 *   var fd = new FormData();
 *   fd.append('file', fileInput.files[0]);
 *   linked3Fetch('linked3_upload', fd, { formData: true });
 *
 * @package Linked3
 */
(function() {
    'use strict';

    var AJAX_URL = (window.linked3_config && window.linked3_config.ajax_url) || '/wp-admin/admin-ajax.php';
    var NONCE = (window.linked3_config && window.linked3_config.nonce) || '';

    // 错误类型
    function Linked3Error(type, message, data) {
        this.name = 'Linked3' + type + 'Error';
        this.type = type;
        this.message = message;
        this.data = data;
        this.stack = (new Error()).stack;
    }
    Linked3Error.prototype = Object.create(Error.prototype);
    Linked3Error.prototype.constructor = Linked3Error;

    /**
     * 统一 AJAX 请求封装 v2.
     *
     * @param {string} action       WP AJAX action 名称
     * @param {object|FormData} data POST 数据
     * @param {object} options      { timeout, retry, loading, formData }
     * @return {Promise<object>}
     */
    window.linked3Fetch = function(action, data, options) {
        options = options || {};
        data = data || {};

        var timeout = options.timeout || 45000;
        var maxRetry = options.retry !== undefined ? options.retry : 1;
        var attempt = 0;

        function attemptRequest() {
            if (options.loading) options.loading(true);

            var body;
            var headers = {};

            if (options.formData || data instanceof FormData) {
                body = data instanceof FormData ? data : new FormData();
                if (data instanceof FormData) {
                    if (!body.has('action')) body.append('action', action);
                    if (!body.has('nonce')) body.append('nonce', NONCE);
                } else {
                    body.append('action', action);
                    body.append('nonce', NONCE);
                    for (var key in data) {
                        if (data.hasOwnProperty(key) && key !== 'nonce') {
                            body.append(key, data[key]);
                        }
                    }
                }
            } else {
                body = new URLSearchParams();
                body.set('action', action);
                body.set('nonce', data.nonce || NONCE);
                for (var key in data) {
                    if (data.hasOwnProperty(key) && key !== 'nonce') {
                        body.set(key, data[key]);
                    }
                }
                headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }

            var controller = new AbortController();
            var timeoutId = setTimeout(function() { controller.abort(); }, timeout);

            return fetch(AJAX_URL, {
                method: 'POST',
                headers: headers,
                body: body,
                credentials: 'same-origin',
                signal: controller.signal,
            })
            .then(function(r) {
                clearTimeout(timeoutId);
                return r.text().then(function(text) {
                    var trimmed = (text || '').trim();
                    if (!trimmed) {
                        throw new Linked3Error('Server', '服务器返回空响应', { action: action, status: r.status });
                    }
                    if (trimmed.charAt(0) !== '{' && trimmed.charAt(0) !== '[') {
                        var m = trimmed.match(/<p>([^<]+)<\/p>/i);
                        var msg = m ? m[1] : trimmed.split('\n')[0].slice(0, 200);
                        throw new Linked3Error('Server', '服务器错误: ' + msg, { action: action, status: r.status, body: trimmed.slice(0, 500) });
                    }
                    try {
                        return JSON.parse(trimmed);
                    } catch (e) {
                        throw new Linked3Error('Server', '响应解析失败: ' + e.message, { action: action });
                    }
                });
            })
            .then(function(json) {
                clearTimeout(timeoutId);
                if (!json || !json.success) {
                    var errMsg = (json && json.data && json.data.message) || '操作失败';
                    var err = new Linked3Error('Server', errMsg, json && json.data);
                    // 403 权限错误
                    if (json && json.data && json.data.message && json.data.message.indexOf('无权限') > -1) {
                        err = new Linked3Error('Auth', errMsg, json && json.data);
                    }
                    throw err;
                }
                return json.data;
            })
            .catch(function(err) {
                clearTimeout(timeoutId);

                // AbortError → TimeoutError
                if (err.name === 'AbortError') {
                    err = new Linked3Error('Timeout',
                        '请求超时 (' + (timeout / 1000) + '秒)。建议: 1)检查网络 2)减少数据量 3)重试。',
                        { action: action, timeout: timeout });
                }

                // 网络错误 → 重试
                if (err.type === 'Network' || (err.message && err.message.indexOf('Failed to fetch') > -1)) {
                    if (attempt < maxRetry) {
                        attempt++;
                        if (window.console && console.warn) {
                            console.warn('[linked3Fetch] ' + action + ' retry ' + attempt + '/' + maxRetry);
                        }
                        return new Promise(function(resolve) {
                            setTimeout(resolve, 1000 * attempt);
                        }).then(attemptRequest);
                    }
                    err = new Linked3Error('Network', '网络连接失败,请检查网络后重试。', { action: action });
                }

                // 全局错误总线
                try {
                    window.dispatchEvent(new CustomEvent('linked3FetchError', {
                        detail: { action: action, error: err, attempt: attempt }
                    }));
                } catch(e) {}

                if (window.console && console.warn) {
                    console.warn('[linked3Fetch] ' + action + ' failed:', err.message);
                }
                throw err;
            })
            .finally(function() {
                if (options.loading) options.loading(false);
            });
        }

        return attemptRequest();
    };

    window.linked3FetchBtn = function(action, data, btn, loadingText, options) {
        var originalText = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = loadingText || '处理中...';
        }
        return window.linked3Fetch(action, data, options).finally(function() {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    };

    // 导出错误类型
    window.Linked3Error = Linked3Error;

})();
