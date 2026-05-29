/**
 * LskyPro 粘贴上传功能 for Typecho
 */
(function() {
    'use strict';

    if (typeof lskyproData === 'undefined') {
        console.log('[LskyPro] 配置未加载');
        return;
    }

    console.log('[LskyPro] 粘贴上传模块已加载');

    /**
     * 上传图片到兰空图床
     */
    function uploadImageToLsky(file, callback) {
        if (!lskyproData.domain || !lskyproData.tokens) {
            showNotification('请先配置兰空图床API网址和Tokens！', 'error');
            return;
        }

        var maxSize = parseInt(lskyproData.max_size) || 10;
        if (file.size > maxSize * 1024 * 1024) {
            showNotification('文件过大，最大支持 ' + maxSize + 'MB', 'error');
            return;
        }

        showNotification('正在上传图片到兰空图床...', 'info');

        var formData = new FormData();
        formData.append('file', file);

        var apiVersion = lskyproData.api_version || 'v1';
        if (apiVersion === 'v2') {
            if (lskyproData.storage_id) formData.append('storage_id', lskyproData.storage_id);
            if (lskyproData.album_id) formData.append('album_id', lskyproData.album_id);
            formData.append('is_public', lskyproData.permission === '1' ? 1 : 0);
        } else {
            formData.append('permission', lskyproData.permission || '1');
            if (lskyproData.album_id) formData.append('album_id', lskyproData.album_id);
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', lskyproData.domain + '/api/' + apiVersion + '/upload', true);
        xhr.setRequestHeader('Authorization', 'Bearer ' + lskyproData.tokens);
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    var url = '';

                    if (apiVersion === 'v2') {
                        if (data.status === 'success') url = data.data.public_url || data.data.url || '';
                    } else {
                        if (data.status) url = data.data.links.url;
                    }

                    if (url) {
                        showNotification('图片上传成功！', 'success');
                        callback(url);
                    } else {
                        showNotification('上传失败：' + (data.message || '未知错误'), 'error');
                    }
                } catch(e) {
                    showNotification('上传失败：响应格式错误', 'error');
                }
            } else {
                showNotification('上传失败：HTTP ' + xhr.status, 'error');
            }
        };

        xhr.onerror = function() {
            showNotification('网络错误', 'error');
        };

        xhr.send(formData);
    }

    /**
     * 插入图片到编辑器
     */
    function insertImageToEditor(url) {
        // Typecho使用textarea#text
        var textarea = document.getElementById('text');
        if (textarea) {
            var imgHtml = '<img src="' + url + '" alt="" />';
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            textarea.value = textarea.value.substring(0, start) + imgHtml + textarea.value.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + imgHtml.length;
            textarea.focus();
        }
    }

    /**
     * 检测并处理图片粘贴
     */
    function detectImagePaste(clipboardData, callback) {
        if (!clipboardData) return false;

        var items = clipboardData.items;
        if (!items) return false;

        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                var file = items[i].getAsFile();
                callback(file);
                return true;
            }
        }
        return false;
    }

    /**
     * 显示通知
     */
    function showNotification(message, type) {
        var existing = document.querySelector('.lskypro-notification');
        if (existing) existing.remove();

        var el = document.createElement('div');
        el.className = 'lskypro-notification lskypro-notification-' + type;
        el.textContent = message;

        document.body.appendChild(el);
        setTimeout(function() {
            el.style.opacity = '0';
            setTimeout(function() { if (el.parentNode) el.remove(); }, 300);
        }, 3000);
    }

    /**
     * 初始化
     */
    function init() {
        console.log('[LskyPro] 初始化粘贴上传');

        // 监听textarea粘贴事件
        var textarea = document.getElementById('text');
        if (textarea) {
            textarea.addEventListener('paste', function(e) {
                detectImagePaste(e.clipboardData || window.clipboardData, function(file) {
                    e.preventDefault();
                    uploadImageToLsky(file, function(url) {
                        insertImageToEditor(url);
                    });
                });
            });
            console.log('[LskyPro] 已绑定textarea粘贴事件');
        }

        // 监听整个文档的粘贴事件（处理其他编辑器）
        document.addEventListener('paste', function(e) {
            var target = e.target;
            
            // 如果已经在textarea中处理过，跳过
            if (target.id === 'text') return;
            
            // 检查是否是可编辑元素
            if (target.isContentEditable || target.contentEditable === 'true') {
                detectImagePaste(e.clipboardData || window.clipboardData, function(file) {
                    e.preventDefault();
                    uploadImageToLsky(file, function(url) {
                        insertImageToEditor(url);
                    });
                });
            }
        }, true);
    }

    // 启动
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
