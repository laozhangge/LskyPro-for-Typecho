<?php
namespace TypechoPlugin\LskyPro;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 兰空图床上传 - 将编辑器上传的图片存至兰空图床
 *
 * @package LskyPro
 * @author 老张博客
 * @version 1.4.1
 * @link https://github.com/laozhangge/LskyPro-for-Typecho
 */
class Plugin implements PluginInterface
{
    public static function activate()
    {
        \Typecho\Plugin::factory('Widget_Upload')->uploadHandle = __CLASS__ . '::uploadHandle';
    }

    public static function deactivate()
    {
    }

    /**
     * 插件配置面板
     */
    public static function config(Form $form)
    {
        // 拦截AJAX请求
        if (!empty($_POST['__lskypro_action'])) {
            self::handleAjax();
        }

        // === 全部使用 Text 输入框 ===

        $api = new Text('api', NULL, '', 'API网址',
            '填写兰空图床域名，包含 http(s)://，不带 / 结尾<br>'
            . '示例：<code>https://pic.laozhang.org</code>'
        );
        $form->addInput($api);

        $token = new Text('token', NULL, '', 'API Token',
            '在兰空图床后台获取的API令牌<br>'
            . '示例：<code>1|UYsgSjmtTkPjS8qPaLl98dJwdVtU492vQbDFI6pg</code>'
        );
        $form->addInput($token);

        $apiVersion = new Text('api_version', NULL, 'v2', 'API版本',
            '填写 <code>v1</code> 或 <code>v2</code>，默认 v2'
        );
        $form->addInput($apiVersion);

        $permission = new Text('permission', NULL, '1', '图片权限',
            '<code>1</code> 公开，<code>0</code> 私有'
        );
        $form->addInput($permission);

        $strategyId = new Text('strategy_id', NULL, '', '存储策略ID',
            '留空使用默认策略。<span id="lskypro-strategy-hint">测试连接后自动显示可选策略</span>'
        );
        $form->addInput($strategyId);

        $albumId = new Text('album_id', NULL, '', '相册ID',
            '留空不指定相册。<span id="lskypro-album-hint">测试连接后自动显示可选相册</span>'
        );
        $form->addInput($albumId);

        $maxSize = new Text('max_size', NULL, '10', '最大上传大小(MB)');
        $form->addInput($maxSize);

        // 获取当前保存的值
        $curStrategy = Options::alloc()->plugin('LskyPro')->strategy_id ?? '';
        $curAlbum = Options::alloc()->plugin('LskyPro')->album_id ?? '';

        // 输出自定义HTML
        ?>
        <style>
            .lskypro-section { background: #fff; padding: 15px 20px; margin: 15px 0; border: 1px solid #ddd; border-radius: 4px; }
            .lskypro-section h4 { margin: 0 0 10px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
            .lskypro-btn { display: inline-block; padding: 6px 16px; background: #0073aa; color: #fff; border: none; border-radius: 3px; cursor: pointer; font-size: 13px; }
            .lskypro-btn:hover { background: #005a87; }
            .lskypro-btn:disabled { background: #ccc; cursor: not-allowed; }
            .lskypro-loading { display: none; margin-left: 8px; color: #666; font-size: 12px; }
            .lskypro-msg { padding: 8px 12px; margin: 8px 0; border-left: 4px solid; font-size: 13px; display: none; }
            .lskypro-msg-ok { border-color: #46b450; background: #f0f8f0; }
            .lskypro-msg-err { border-color: #dc3232; background: #fdf0f0; }
            .lskypro-about { font-size: 13px; color: #666; }
            .lskypro-about a { color: #0073aa; }
            .lskypro-list { margin: 8px 0; }
            .lskypro-item { display: inline-block; margin: 3px; padding: 4px 10px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; cursor: pointer; }
            .lskypro-item:hover { background: #0073aa; color: #fff; border-color: #0073aa; }
            .lskypro-item.lskypro-item-active { background: #0073aa; color: #fff; border-color: #005a87; }
        </style>

        <div class="lskypro-section">
            <h4>测试连接</h4>
            <p>
                <button type="button" id="lskypro-test-btn" class="lskypro-btn">测试连接</button>
                <span id="lskypro-test-loading" class="lskypro-loading">正在测试...</span>
            </p>
            <div id="lskypro-test-result" class="lskypro-msg"></div>
            <p style="font-size:12px;color:#999;">请先填写API网址和Token，再点击测试。测试成功后自动加载存储策略和相册，点击即可填入ID。</p>
        </div>

        <div class="lskypro-section" id="lskypro-strategies-box" style="display:none;">
            <h4>可用存储策略 <small style="color:#999;">（点击填入上方输入框）</small></h4>
            <div id="lskypro-strategies-list" class="lskypro-list"></div>
        </div>

        <div class="lskypro-section" id="lskypro-albums-box" style="display:none;">
            <h4>可用相册 <small style="color:#999;">（点击填入上方输入框）</small></h4>
            <div id="lskypro-albums-list" class="lskypro-list"></div>
        </div>

        <div class="lskypro-section">
            <h4>关于</h4>
            <div class="lskypro-about">
                <p>兰空图床上传 v1.4.1</p>
                <p>作者：<a href="https://laozhang.org" target="_blank">老张博客</a></p>
                <p>插件主页：<a href="https://github.com/laozhangge/LskyPro-for-Typecho" target="_blank">GitHub</a></p>
                <p>兰空图床官网：<a href="https://www.lsky.pro/" target="_blank">https://www.lsky.pro/</a></p>
            </div>
        </div>

        <script>
        (function() {
            var currentStrategy = '<?php echo htmlspecialchars($curStrategy); ?>';
            var currentAlbum = '<?php echo htmlspecialchars($curAlbum); ?>';

            function getVal(name) {
                var el = document.querySelector('[name="config[' + name + ']"]');
                if (!el) el = document.querySelector('[name="' + name + '"]');
                return el ? el.value.trim() : '';
            }

            function getInputEl(name) {
                var el = document.querySelector('[name="config[' + name + ']"]');
                if (!el) el = document.querySelector('[name="' + name + '"]');
                return el;
            }

            function showResult(id, ok, msg) {
                var el = document.getElementById(id);
                el.style.display = 'block';
                el.className = 'lskypro-msg ' + (ok ? 'lskypro-msg-ok' : 'lskypro-msg-err');
                el.innerHTML = msg;
            }

            // 渲染可点击列表
            function renderList(containerId, boxId, items, targetField, hintId, hintPrefix) {
                var box = document.getElementById(boxId);
                var list = document.getElementById(containerId);
                var inputEl = getInputEl(targetField);
                box.style.display = 'block';
                list.innerHTML = '';
                items.forEach(function(item) {
                    var span = document.createElement('span');
                    span.className = 'lskypro-item';
                    if (inputEl && String(item.id) === String(inputEl.value)) {
                        span.className += ' lskypro-item-active';
                    }
                    span.textContent = item.name + ' (ID:' + item.id + ')';
                    span.onclick = function() {
                        if (inputEl) inputEl.value = item.id;
                        // 高亮当前选中
                        var all = list.querySelectorAll('.lskypro-item');
                        for (var i = 0; i < all.length; i++) all[i].className = 'lskypro-item';
                        span.className = 'lskypro-item lskypro-item-active';
                    };
                    list.appendChild(span);
                });
                document.getElementById(hintId).textContent = hintPrefix + '已加载 ' + items.length + ' 个，点击选择';
            }

            // 测试连接
            document.getElementById('lskypro-test-btn').onclick = function() {
                var api = getVal('api');
                var token = getVal('token');
                var apiVersion = getVal('api_version') || 'v2';
                var btn = this;

                if (!api || !token) {
                    showResult('lskypro-test-result', false, '请先填写API网址和Token');
                    return;
                }

                btn.disabled = true;
                document.getElementById('lskypro-test-loading').style.display = 'inline';

                var fd = new FormData();
                fd.append('__lskypro_action', 'test_connection');
                fd.append('api', api);
                fd.append('token', token);
                fd.append('api_version', apiVersion);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.timeout = 15000;
                xhr.onload = function() {
                    document.getElementById('lskypro-test-loading').style.display = 'none';
                    btn.disabled = false;
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            showResult('lskypro-test-result', true, '✅ 连接成功！欢迎 ' + r.name);
                            loadStrategies(api, token);
                            loadAlbums(api, token);
                        } else {
                            showResult('lskypro-test-result', false, '❌ ' + (r.message || '连接失败'));
                        }
                    } catch(e) {
                        showResult('lskypro-test-result', false, '❌ 响应格式错误');
                        console.error('Response:', xhr.responseText.substring(0, 500));
                    }
                };
                xhr.onerror = function() {
                    document.getElementById('lskypro-test-loading').style.display = 'none';
                    btn.disabled = false;
                    showResult('lskypro-test-result', false, '❌ 网络错误');
                };
                xhr.ontimeout = function() {
                    document.getElementById('lskypro-test-loading').style.display = 'none';
                    btn.disabled = false;
                    showResult('lskypro-test-result', false, '❌ 请求超时');
                };
                xhr.send(fd);
            };

            // 加载策略列表
            function loadStrategies(api, token) {
                var fd = new FormData();
                fd.append('__lskypro_action', 'get_strategies');
                fd.append('api', api);
                fd.append('token', token);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.timeout = 15000;
                xhr.onload = function() {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success && r.strategies && r.strategies.length > 0) {
                            renderList('lskypro-strategies-list', 'lskypro-strategies-box', r.strategies, 'strategy_id', 'lskypro-strategy-hint', '');
                        } else {
                            document.getElementById('lskypro-strategy-hint').textContent = '未获取到策略列表';
                        }
                    } catch(e) {}
                };
                xhr.send(fd);
            }

            // 加载相册列表
            function loadAlbums(api, token) {
                var fd = new FormData();
                fd.append('__lskypro_action', 'get_albums');
                fd.append('api', api);
                fd.append('token', token);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.timeout = 15000;
                xhr.onload = function() {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success && r.albums && r.albums.length > 0) {
                            renderList('lskypro-albums-list', 'lskypro-albums-box', r.albums, 'album_id', 'lskypro-album-hint', '');
                        } else {
                            document.getElementById('lskypro-album-hint').textContent = '未获取到相册列表';
                        }
                    } catch(e) {}
                };
                xhr.send(fd);
            }
        })();
        </script>
        <?php
    }

    /**
     * 个人配置
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 处理AJAX请求
     */
    public static function handleAjax()
    {
        if (empty($_POST['__lskypro_action'])) return;

        $action = $_POST['__lskypro_action'];
        $api = rtrim(trim($_POST['api'] ?? ''), '/');
        $token = trim($_POST['token'] ?? '');
        $apiVersion = trim($_POST['api_version'] ?? 'v2');

        // 清空Typecho输出缓冲
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');

        if (empty($api) || empty($token)) {
            echo json_encode(['success' => false, 'message' => '请填写API网址和Token']);
            exit;
        }

        switch ($action) {
            case 'test_connection':
                $url = ($apiVersion === 'v2')
                    ? $api . '/api/v2/user/profile'
                    : $api . '/api/v1/profile';

                $response = self::httpGet($url, $token);
                $data = json_decode($response, true);

                if (!$data) {
                    echo json_encode(['success' => false, 'message' => '连接失败，请检查网址和Token']);
                    exit;
                }

                if ($apiVersion === 'v2') {
                    if (isset($data['status']) && $data['status'] === 'success') {
                        echo json_encode(['success' => true, 'name' => $data['data']['name'] ?? '用户']);
                        exit;
                    }
                } else {
                    if (isset($data['status']) && $data['status']) {
                        echo json_encode(['success' => true, 'name' => $data['data']['name'] ?? '用户']);
                        exit;
                    }
                }

                echo json_encode(['success' => false, 'message' => $data['message'] ?? '连接失败']);
                exit;

            case 'get_strategies':
                $url = $api . '/api/v1/strategies';
                $response = self::httpGet($url, $token);
                $data = json_decode($response, true);

                if ($data && isset($data['status']) && $data['status']) {
                    $list = [];
                    foreach (($data['data']['strategies'] ?? []) as $s) {
                        $list[] = ['id' => $s['id'], 'name' => $s['name']];
                    }
                    echo json_encode(['success' => true, 'strategies' => $list]);
                    exit;
                }

                echo json_encode(['success' => false, 'message' => '获取失败']);
                exit;

            case 'get_albums':
                $url = $api . '/api/v1/albums';
                $response = self::httpGet($url, $token);
                $data = json_decode($response, true);

                if ($data && isset($data['status']) && $data['status']) {
                    $list = [];
                    foreach (($data['data']['data'] ?? []) as $a) {
                        $list[] = ['id' => $a['id'], 'name' => $a['name']];
                    }
                    echo json_encode(['success' => true, 'albums' => $list]);
                    exit;
                }

                echo json_encode(['success' => false, 'message' => '获取失败']);
                exit;
        }

        echo json_encode(['success' => false, 'message' => '未知操作']);
        exit;
    }

    /**
     * 上传处理
     */
    public static function uploadHandle($file)
    {
        if (empty($file['name'])) {
            return false;
        }

        $ext = self::getExt($file['name']);
        if (empty($ext)) {
            return false;
        }

        $maxSize = intval(Options::alloc()->plugin('LskyPro')->max_size ?? 10);
        $fileSize = $file['size'] ?? 0;
        if ($maxSize > 0 && $fileSize > $maxSize * 1024 * 1024) {
            return false;
        }

        if (self::isImage($ext)) {
            $result = self::lskyUpload($file, $ext);
            if ($result) return $result;
        }

        return self::localUpload($file, $ext);
    }

    /**
     * 上传到兰空图床
     */
    private static function lskyUpload($file, $ext)
    {
        try {
            $options = Options::alloc()->plugin('LskyPro');
            $api = rtrim($options->api ?? '', '/');
            $token = $options->token ?? '';
            $apiVersion = $options->api_version ?: 'v2';

            if (empty($api) || empty($token)) {
                return false;
            }

            $tmpFile = $file['tmp_name'] ?? ($file['bytes'] ?? ($file['bits'] ?? ''));
            if (empty($tmpFile) || !is_readable($tmpFile)) {
                return false;
            }

            $url = $api . '/api/' . $apiVersion . '/upload';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ]);

            $params = ['file' => new \CURLFile($tmpFile, self::getMime($ext), $file['name'])];

            $permission = $options->permission ?? '1';
            $params['permission'] = $permission;

            $strategyId = $options->strategy_id ?? '';
            if (!empty($strategyId)) {
                if ($apiVersion === 'v2') {
                    $params['storage_id'] = intval($strategyId);
                } else {
                    $params['strategy_id'] = intval($strategyId);
                }
            }

            $albumId = $options->album_id ?? '';
            if (!empty($albumId)) {
                $params['album_id'] = intval($albumId);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || !$response) {
                return false;
            }

            $json = json_decode($response, true);
            if (!$json) return false;

            $imageUrl = '';
            if ($apiVersion === 'v2') {
                if (isset($json['status']) && $json['status'] === 'success') {
                    $imageUrl = $json['data']['public_url'] ?? $json['data']['url'] ?? '';
                }
            } else {
                if (isset($json['status']) && $json['status']) {
                    $imageUrl = $json['data']['links']['url'] ?? $json['data']['url'] ?? '';
                }
            }

            if (empty($imageUrl)) {
                return false;
            }

            return [
                'name' => $file['name'],
                'path' => $imageUrl,
                'size' => $file['size'] ?? 0,
                'type' => self::getMime($ext),
                'ext' => $ext,
            ];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 本地回退上传
     */
    private static function localUpload($file, $ext)
    {
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/' . date('Y/m');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid() . '_' . time() . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;

        $src = $file['tmp_name'] ?? ($file['bytes'] ?? ($file['bits'] ?? ''));
        if (empty($src) || !is_readable($src)) {
            return false;
        }

        if (!move_uploaded_file($src, $dest) && !copy($src, $dest)) {
            return false;
        }

        $relativePath = str_replace(__TYPECHO_ROOT_DIR__, '', $dest);
        $relativePath = str_replace('\\', '/', $relativePath);

        return [
            'name' => $file['name'],
            'path' => $relativePath,
            'size' => $file['size'] ?? filesize($dest),
            'type' => self::getMime($ext),
            'ext' => $ext,
        ];
    }

    private static function httpGet($url, $token)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        return $error ? false : $response;
    }

    private static function getExt($filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return preg_replace('/[^a-z0-9]/', '', $ext);
    }

    private static function isImage($ext): bool
    {
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
    }

    private static function getMime($ext): string
    {
        $mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
        ];
        return $mimes[$ext] ?? 'application/octet-stream';
    }
}
