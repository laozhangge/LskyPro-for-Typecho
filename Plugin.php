<?php
namespace TypechoPlugin\LskyPro;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Select;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 兰空图床上传 - 将编辑器上传的图片存至兰空图床
 *
 * @package LskyPro
 * @author 老张博客
 * @version 1.4.0
 * @link https://github.com/laozhangge/LskyPro-for-Typecho
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件 - 替换Typecho默认上传处理
     */
    public static function activate()
    {
        \Typecho\Plugin::factory('Widget_Upload')->uploadHandle = __CLASS__ . '::uploadHandle';
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        $noop = true;
    }

    /**
     * 插件配置面板
     */
    public static function config(Form $form)
    {
        // 拦截AJAX请求（测试连接/策略/相册列表）
        if (!empty($_POST['__lskypro_action'])) {
            self::handleAjax();
        }

        // 基本设置
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

        $apiVersion = new Select('api_version', [
            'v1' => 'V1 (旧版本)',
            'v2' => 'V2 (新版本)',
        ], 'v2', 'API版本', '根据您的兰空图床版本选择');
        $form->addInput($apiVersion);

        $permission = new Select('permission', [
            '1' => '公开',
            '0' => '私有',
        ], '1', '图片权限');
        $form->addInput($permission);

        // 存储策略 - Select下拉，连接成功后自动填充
        $strategyId = new Select('strategy_id', ['' => '请先测试连接'], '', '存储策略',
            '<span id="lskypro-strategy-hint">测试连接成功后自动加载可选策略</span>'
        );
        $form->addInput($strategyId);

        // 相册 - Select下拉，连接成功后自动填充
        $albumId = new Select('album_id', ['' => '请先测试连接'], '', '相册',
            '<span id="lskypro-album-hint">测试连接成功后自动加载可选相册</span>'
        );
        $form->addInput($albumId);

        // 高级设置
        $maxSize = new Text('max_size', NULL, '10', '最大上传大小(MB)');
        $form->addInput($maxSize);

        // 获取当前保存的策略和相册值
        $curStrategy = Options::alloc()->plugin('LskyPro')->strategy_id ?? '';
        $curAlbum = Options::alloc()->plugin('LskyPro')->album_id ?? '';

        // 输出自定义HTML（测试连接+关于）
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
        </style>

        <div class="lskypro-section">
            <h4>测试连接</h4>
            <p>
                <button type="button" id="lskypro-test-btn" class="lskypro-btn">测试连接</button>
                <span id="lskypro-test-loading" class="lskypro-loading">正在测试...</span>
            </p>
            <div id="lskypro-test-result" class="lskypro-msg"></div>
            <p style="font-size:12px;color:#999;">请先填写API网址和Token，再点击测试。测试成功后自动加载存储策略和相册供选择。</p>
        </div>

        <div class="lskypro-section">
            <h4>关于</h4>
            <div class="lskypro-about">
                <p>兰空图床上传 v1.4.0</p>
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

            function showResult(id, ok, msg) {
                var el = document.getElementById(id);
                el.style.display = 'block';
                el.className = 'lskypro-msg ' + (ok ? 'lskypro-msg-ok' : 'lskypro-msg-err');
                el.innerHTML = msg;
            }

            // 填充Select下拉
            function populateSelect(selectEl, items, currentValue, emptyLabel) {
                selectEl.innerHTML = '';
                var opt0 = document.createElement('option');
                opt0.value = '';
                opt0.textContent = '-- ' + emptyLabel + ' --';
                selectEl.appendChild(opt0);
                items.forEach(function(item) {
                    var opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name + ' (ID:' + item.id + ')';
                    if (String(item.id) === String(currentValue)) {
                        opt.selected = true;
                    }
                    selectEl.appendChild(opt);
                });
            }

            // 测试连接
            document.getElementById('lskypro-test-btn').onclick = function() {
                var api = getVal('api');
                var token = getVal('token');
                var apiVersion = getVal('api_version');
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

            // 加载策略列表 → 填充Select
            function loadStrategies(api, token) {
                var selEl = document.querySelector('[name="config[strategy_id]"]');
                if (!selEl) selEl = document.querySelector('[name="strategy_id"]');
                if (!selEl) return;
                selEl.innerHTML = '<option>正在加载策略...</option>';

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
                            populateSelect(selEl, r.strategies, currentStrategy, '使用默认策略');
                            document.getElementById('lskypro-strategy-hint').textContent = '已加载 ' + r.strategies.length + ' 个策略，请选择';
                        } else {
                            selEl.innerHTML = '<option value="">暂无可用策略</option>';
                            document.getElementById('lskypro-strategy-hint').textContent = '未获取到策略列表';
                        }
                    } catch(e) {
                        selEl.innerHTML = '<option value="">加载失败</option>';
                    }
                };
                xhr.onerror = function() {
                    selEl.innerHTML = '<option value="">加载失败</option>';
                };
                xhr.send(fd);
            }

            // 加载相册列表 → 填充Select
            function loadAlbums(api, token) {
                var selEl = document.querySelector('[name="config[album_id]"]');
                if (!selEl) selEl = document.querySelector('[name="album_id"]');
                if (!selEl) return;
                selEl.innerHTML = '<option>正在加载相册...</option>';

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
                            populateSelect(selEl, r.albums, currentAlbum, '不指定相册');
                            document.getElementById('lskypro-album-hint').textContent = '已加载 ' + r.albums.length + ' 个相册，请选择';
                        } else {
                            selEl.innerHTML = '<option value="">暂无相册</option>';
                            document.getElementById('lskypro-album-hint').textContent = '未获取到相册列表';
                        }
                    } catch(e) {
                        selEl.innerHTML = '<option value="">加载失败</option>';
                    }
                };
                xhr.onerror = function() {
                    selEl.innerHTML = '<option value="">加载失败</option>';
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
        // 留空（Typecho要求必须有此方法）
    }

    /**
     * 处理AJAX请求（测试连接/策略/相册）
     */
    public static function handleAjax()
    {
        if (empty($_POST['__lskypro_action'])) return;

        $action = $_POST['__lskypro_action'];
        $api = rtrim(trim($_POST['api'] ?? ''), '/');
        $token = trim($_POST['token'] ?? '');
        $apiVersion = trim($_POST['api_version'] ?? 'v2');

        // 关键修复：清空Typecho框架已产生的所有输出缓冲
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
                // 策略列表始终用V1
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
                // 相册列表始终用V1
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
     * 替换Typecho默认上传 - 图片上传到兰空图床
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

        // 检查文件大小
        $maxSize = intval(Options::alloc()->plugin('LskyPro')->max_size ?? 10);
        $fileSize = $file['size'] ?? 0;
        if ($maxSize > 0 && $fileSize > $maxSize * 1024 * 1024) {
            return false;
        }

        // 图片上传到兰空图床
        if (self::isImage($ext)) {
            $result = self::lskyUpload($file, $ext);
            if ($result) return $result;
        }

        // 非图片或上传失败，回退本地
        return self::localUpload($file, $ext);
    }

    /**
     * 上传图片到兰空图床
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

            // 存储策略
            $strategyId = $options->strategy_id ?? '';
            if (!empty($strategyId)) {
                if ($apiVersion === 'v2') {
                    $params['storage_id'] = intval($strategyId);
                } else {
                    $params['strategy_id'] = intval($strategyId);
                }
            }

            // 相册
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
     * 回退：本地上传
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

    /**
     * HTTP GET请求
     */
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

    /**
     * 获取文件扩展名
     */
    private static function getExt($filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return preg_replace('/[^a-z0-9]/', '', $ext);
    }

    /**
     * 判断是否为图片
     */
    private static function isImage($ext): bool
    {
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
    }

    /**
     * 获取MIME类型
     */
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
