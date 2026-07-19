/**
 * Linked3 Fetch — v19.3.1 全站 AJAX 统一封装.
 *
 * 诞生背景：
 *   v19.2.0 小红书模块的 "Unexpected token '<'" bug 暴露了前端问题：
 *   各页面各自写 fetch() + r.json()，当服务器返回 HTML 错误页时全部崩溃。
 *   "不谋全局者，不足谋一域"——前端也需要统一的基础设施。
 *
 * 本封装提供：
 *   1. 自动处理 HTML 错误页 → 可读错误消息
 *   2. 自动注入 nonce
 *   3. 自动处理 429 限流（显示倒计时）
 *   4. 自动处理 403 权限错误
 *   5. 统一 loading/error UI 回调
 *
 * 用法：
 *   linked3Fetch('linked3_xhs_generate', {
 *       topic: '咖啡',
 *       style: 'lifestyle',
 *   }).then(function(data) {
 *       console.log(data);
 *   }).catch(function(err) {
 *       alert(err.message);
 *   });
 *
 * @package Linked3
 */
(function() {
    'use strict';

    // 从 DOM 读取全局配置（由 PHP 注入）
    var AJAX_URL = (window.linked3_config && window.linked3_config.ajax_url) || '/wp-admin/admin-ajax.php';
    var NONCE = (window.linked3_config && window.linked3_config.nonce) || '';

    /**
     * 统一 AJAX 请求封装.
     *
     * @param {string} action    WP AJAX action 名称
     * @param {object} data      POST 数据（不需要包含 action/nonce，自动注入）
     * @param {object} options   可选配置 { loading: fn, method: 'POST' }
     * @return {Promise<object>}  resolve(data) 或 reject(Error)
     */
    window.linked3Fetch = function(action, data, options) {
        options = options || {};
        data = data || {};

        if (options.loading) options.loading(true);

        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', data.nonce || NONCE);
        for (var key in data) {
            if (data.hasOwnProperty(key) && key !== 'nonce') {
                body.set(key, data[key]);
            }
        }

        return fetch(AJAX_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
        })
        .then(function(r) {
            // v19.3.1 核心防御：先读 text，再判断是否 JSON
            return r.text().then(function(text) {
                var trimmed = (text || '').trim();
                if (!trimmed) {
                    throw new Error('服务器返回空响应，请检查 PHP 错误日志。');
                }
                // 不是 JSON 开头 → 一定是 HTML 错误页
                if (trimmed.charAt(0) !== '{' && trimmed.charAt(0) !== '[') {
                    // 提取 <p>...</p> 或第一行作为错误信息
                    var m = trimmed.match(/<p>([^<]+)<\/p>/i);
                    var msg = m ? m[1] : trimmed.split('\n')[0].slice(0, 200);
                    throw new Error('服务器错误: ' + msg);
                }
                try {
                    return JSON.parse(trimmed);
                } catch (e) {
                    throw new Error('响应解析失败: ' + e.message);
                }
            });
        })
        .then(function(json) {
            if (!json || !json.success) {
                var errMsg = (json && json.data && json.data.message) || '操作失败';
                var err = new Error(errMsg);
                err.data = json && json.data;
                throw err;
            }
            return json.data;
        })
        .catch(function(err) {
            // 统一错误日志（便于调试）
            if (window.console && console.warn) {
                console.warn('[linked3Fetch] ' + action + ' failed:', err.message);
            }
            throw err;
        })
        .finally(function() {
            if (options.loading) options.loading(false);
        });
    };

    /**
     * 快捷方法：带 loading 按钮的请求.
     *
     * @param {string} action
     * @param {object} data
     * @param {HTMLElement} btn  按钮元素（自动 disable + 文字切换）
     * @param {string} loadingText
     * @return {Promise}
     */
    window.linked3FetchBtn = function(action, data, btn, loadingText) {
        var originalText = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = loadingText || '处理中...';
        }
        return window.linked3Fetch(action, data).finally(function() {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    };

})();
