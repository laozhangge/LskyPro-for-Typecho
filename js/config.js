// LskyPro 测试连接 + 策略/相册列表
window.addEventListener('load', function() {
    // 查找Typecho表单字段
    function getField(name) {
        return document.querySelector('[name="config[' + name + ']"]');
    }
    function getVal(name) {
        var el = getField(name);
        return el ? el.value.trim() : '';
    }

    // 创建测试按钮区域
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = '测试连接';
    btn.style.cssText = 'padding:6px 16px;background:#0073aa;color:#fff;border:none;border-radius:3px;cursor:pointer;margin:15px 0';

    var loadSpan = document.createElement('span');
    loadSpan.style.cssText = 'display:none;color:#666;font-size:12px;margin-left:8px';
    loadSpan.textContent = '正在测试...';

    var msgDiv = document.createElement('div');
    msgDiv.style.cssText = 'display:none;padding:8px 12px;margin:8px 0;border-left:4px solid;font-size:13px';

    var strList = document.createElement('div');
    strList.style.margin = '5px 0';
    var albList = document.createElement('div');
    albList.style.margin = '5px 0';

    // 找到表单末尾的提交按钮，在它前面插入
    var submitBtn = document.querySelector('input[type="submit"], button[type="submit"]');
    if (submitBtn && submitBtn.parentNode) {
        var wrap = document.createElement('div');
        wrap.style.cssText = 'margin:15px 0;padding:15px 20px;background:#fff;border:1px solid #ddd;border-radius:4px';
        var h4 = document.createElement('h4');
        h4.style.cssText = 'margin:0 0 10px';
        h4.textContent = '测试连接';
        wrap.appendChild(h4);
        wrap.appendChild(btn);
        wrap.appendChild(loadSpan);
        wrap.appendChild(msgDiv);
        wrap.appendChild(strList);
        wrap.appendChild(albList);
        submitBtn.parentNode.insertBefore(wrap, submitBtn);
    }

    function showMsg(ok, text) {
        msgDiv.style.display = 'block';
        msgDiv.style.borderColor = ok ? '#46b450' : '#dc3232';
        msgDiv.style.background = ok ? '#f0f8f0' : '#fdf0f0';
        msgDiv.textContent = text;
    }

    function showItems(container, items, fieldName, hintEl) {
        container.innerHTML = '';
        var inp = getField(fieldName);
        items.forEach(function(item) {
            var span = document.createElement('span');
            span.style.cssText = 'display:inline-block;margin:3px;padding:4px 10px;background:#f0f0f0;border:1px solid #ddd;border-radius:3px;font-size:12px;cursor:pointer';
            if (inp && String(item.id) === String(inp.value)) {
                span.style.background = '#0073aa';
                span.style.color = '#fff';
            }
            span.textContent = item.name + ' (ID:' + item.id + ')';
            span.addEventListener('click', function() {
                if (inp) inp.value = item.id;
                var all = container.querySelectorAll('span');
                for (var i = 0; i < all.length; i++) {
                    all[i].style.background = '#f0f0f0';
                    all[i].style.color = '';
                }
                span.style.background = '#0073aa';
                span.style.color = '#fff';
            });
            container.appendChild(span);
        });
    }

    function ajaxPost(data, callback) {
        var fd = new FormData();
        for (var k in data) fd.append(k, data[k]);
        var x = new XMLHttpRequest();
        x.open('POST', window.__lskyAjaxUrl, true);
        x.timeout = 15000;
        x.onload = function() {
            try { callback(null, JSON.parse(x.responseText)); }
            catch(e) { callback(e); }
        };
        x.onerror = function() { callback(new Error('网络错误')); };
        x.ontimeout = function() { callback(new Error('超时')); };
        x.send(fd);
    }

    btn.addEventListener('click', function() {
        var api = getVal('api');
        var token = getVal('token');
        var ver = getVal('api_version') || 'v2';

        if (!api || !token) {
            showMsg(false, '请先填写API网址和Token');
            return;
        }

        btn.disabled = true;
        loadSpan.style.display = 'inline';

        ajaxPost({__lskypro_action: 'test_connection', api: api, token: token, api_version: ver}, function(err, res) {
            loadSpan.style.display = 'none';
            btn.disabled = false;
            if (err) { showMsg(false, '响应格式错误'); return; }
            if (res.success) {
                showMsg(true, '连接成功！欢迎 ' + res.name);
                // 加载策略
                ajaxPost({__lskypro_action: 'get_strategies', api: api, token: token}, function(e2, r2) {
                    if (!e2 && r2.success && r2.strategies) showItems(strList, r2.strategies, 'strategy_id');
                });
                // 加载相册
                ajaxPost({__lskypro_action: 'get_albums', api: api, token: token}, function(e3, r3) {
                    if (!e3 && r3.success && r3.albums) showItems(albList, r3.albums, 'album_id');
                });
            } else {
                showMsg(false, res.message || '连接失败');
            }
        });
    });
});
