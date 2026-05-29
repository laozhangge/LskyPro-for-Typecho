/**
 * LskyPro 粘贴图片自动上传
 * 在 Typecho 编辑器中粘贴图片时自动上传到兰空图床
 */
(function () {
    'use strict';

    if (window.__lskypro_paste_loaded) return;
    window.__lskypro_paste_loaded = true;

    var FORMAT = window.__lskypro_format || 'markdown';
    var AJAX_URL = window.__lskypro_ajax || '';
    var MAX_SIZE = 10 * 1024 * 1024; // 10MB

    function getEditor() {
        return (
            document.querySelector('.cm-content[contenteditable]') ||  // CodeMirror
            document.querySelector('textarea[name="text"]') ||          // Typecho
            (function () {                                               // 最大textarea兜底
                var tas = document.querySelectorAll('textarea');
                if (!tas.length) return null;
                var max = tas[0];
                for (var i = 1; i < tas.length; i++) {
                    if (tas[i].offsetHeight > max.offsetHeight) max = tas[i];
                }
                return max;
            })()
        );
    }

    function isImage(file) {
        if (!file) return false;
        if (file.type && file.type.indexOf('image/') === 0) return true;
        return /\.(jpg|jpeg|png|gif|webp|bmp|tiff|svg|ico|psd)$/i.test(file.name || '');
    }

    function showToast(msg, type) {
        var d = document.createElement('div');
        d.textContent = msg;
        d.style.cssText = 'position:fixed;top:50px;right:20px;z-index:999999;padding:12px 20px;'
            + 'border-radius:4px;font-size:14px;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.15);'
            + 'font-family:-apple-system,BlinkMacSystemFont,sans-serif;'
            + 'background:' + (type === 'error' ? '#f5222d' : '#52c41a');
        document.body.appendChild(d);
        setTimeout(function () { if (d.parentNode) d.parentNode.removeChild(d); }, 3000);
    }

    function insertText(editor, text, savedRange) {
        if (editor.tagName === 'TEXTAREA') {
            var pos = editor.selectionStart;
            editor.value = editor.value.slice(0, pos) + text + editor.value.slice(editor.selectionEnd);
            editor.focus();
            editor.setSelectionRange(pos + text.length, pos + text.length);
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }
        // ContentEditable
        editor.focus();
        var range = null;
        if (savedRange) {
            try {
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedRange);
                range = savedRange;
            } catch (e) { /* ignore */ }
        }
        if (!range) {
            var sel2 = window.getSelection();
            range = sel2.rangeCount > 0 ? sel2.getRangeAt(0) : null;
        }
        if (range) {
            range.deleteContents();
            var node = document.createTextNode(text);
            range.insertNode(node);
            range.setStartAfter(node);
            range.collapse(true);
            var sel3 = window.getSelection();
            sel3.removeAllRanges();
            sel3.addRange(range);
        } else {
            editor.textContent += text;
        }
        editor.dispatchEvent(new Event('input', { bubbles: true }));
        editor.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function uploadAndInsert(file, editor, savedRange) {
        var cursorPos = editor.tagName === 'TEXTAREA' ? editor.selectionStart : 0;

        var fd = new FormData();
        fd.append('__lskypro_action', 'paste_upload');
        fd.append('file', file);
        fd.append('format', FORMAT);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', AJAX_URL, true);
        xhr.timeout = 30000;

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                var pct = Math.round(e.loaded / e.total * 100);
                showToast('上传中... ' + pct + '%');
            }
        });

        xhr.onload = function () {
            if (xhr.status !== 200) {
                showToast('上传失败: HTTP ' + xhr.status, 'error');
                return;
            }
            var resp;
            try { resp = JSON.parse(xhr.responseText); } catch (e) {
                showToast('响应格式错误', 'error');
                return;
            }
            if (!resp.status) {
                showToast(resp.message || '上传失败', 'error');
                return;
            }
            var content = resp.data.content;
            if (editor.tagName === 'TEXTAREA') {
                var text = editor.value;
                editor.value = text.slice(0, cursorPos) + content + text.slice(cursorPos);
                editor.focus();
                editor.setSelectionRange(cursorPos + content.length, cursorPos + content.length);
                editor.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                insertText(editor, content, savedRange);
            }
            showToast('图片上传成功');
        };

        xhr.onerror = function () { showToast('网络错误', 'error'); };
        xhr.ontimeout = function () { showToast('上传超时', 'error'); };
        xhr.send(fd);
    }

    function handlePaste(e) {
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;

        for (var i = 0; i < items.length; i++) {
            if (items[i].kind !== 'file') continue;
            var file = items[i].getAsFile();
            if (!file || !isImage(file)) continue;

            e.preventDefault();

            if (file.size > MAX_SIZE) {
                showToast('图片大小不能超过 10MB', 'error');
                return;
            }

            var editor = getEditor();
            if (!editor) {
                showToast('找不到编辑器', 'error');
                return;
            }

            // 保存光标位置
            var savedRange = null;
            try {
                var sel = window.getSelection();
                if (sel.rangeCount > 0) savedRange = sel.getRangeAt(0).cloneRange();
            } catch (e2) { /* ignore */ }

            uploadAndInsert(file, editor, savedRange);
            return;
        }
    }

    function init() {
        var editor = getEditor();
        if (!editor) {
            setTimeout(init, 1000);
            return;
        }
        editor.addEventListener('paste', handlePaste, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
