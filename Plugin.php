<?php
namespace TypechoPlugin\LskyPro;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Hidden;
use Typecho\Common;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 兰空图床上传 - 将编辑器上传的图片存至兰空图床
 *
 * @package LskyPro
 * @author 老张博客
 * @version 1.3.6
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

        $strategyId = new Text('strategy_id', NULL, '', '存储策略ID',
            '留空使用默认策略。<span id="lskypro-strategy-hint"></span>'
        );
        $form->addInput($strategyId);

        $albumId = new Text('album_id', NULL, '', '相册ID',
            '留空不指定相册。<span id="lskypro-album-hint"></span>'
        );
        $form->addInput($albumId);

        // 高级设置
        $maxSize = new Text('max_size', NULL, '10', '最大上传大小(MB)');
        $form->addInput($maxSize);

        // 自定义面板（测试连接+列表加载）
        $panel = new Hidden('panel');
        $panel->value('lskypro');
        $form->addItem($panel);

        // 输出自定义HTML（测试连接+策略/相册列表+关于）
        $options = Options::alloc()->plugin('LskyPro');
        $currentStrategyId = $options->strategy_id ?? '';
        $currentAlbumId = $options->album_id ?? '';
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
            .lskypro-list { margin: 5px 0; }
            .lskypro-list-item { display: inline-block; margin: 2px 5px 2px 0; padding: 3px 8px; background: #f0f0f0; border-radius: 3px; font-size: 12px; }
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
            <p style="font-size:12px;color:#999;">请先填写API网址和Token，再点击测试。测试成功后自动加载存储策略和相册列表。</p>
        </div>

        <div class="lskypro-section" id="lskypro-strategies-section" style="display:none;">
            <h4>可用存储策略</h4>
            <div id="lskypro-strategies-list" class="lskypro-list"></div>
            <p style="font-size:12px;color:#999;">将上方显示的策略ID填入"存储策略ID"输入框</p>
        </div>

        <div class="lskypro-section" id="lskypro-albums-section" style="display:none;">
            <h4>可用相册</h4>
            <div id="lskypro-albums-list" class="lskypro-list"></div>
            <p style="font-size:12px;color:#999;">将上方显示的相册ID填入"相册ID"输入框</p>
        </div>

        <div class="lskypro-section">
            <h4>关于</h4>
            <div class="lskypro-about">
                <p>兰空图床上传 v1.3.6</p>
                <p>作者：<a href="https://laozhang.org" target="_blank">老张博客</a></p>
                <p>插件主页：<a href="https://github.com/laozhangge/LskyPro-for-Typecho" target="_blank">GitHub</a></p>
                <p>兰空图床官网：<a href="https://www.lsky.pro/" target="_blank">https://www.lsky.pro/</a></p>
            </div>
        </div>

        <script>
        (function() {
            function getVal(name) {
                var el = document.querySelector('[name="' + name + '"]');
                return el ? el.value.trim() : '';
            }

            function showResult(id, ok, msg) {
                var el = document.getElementById(id);
                el.style.display = 'block';
                el.className = 'lskypro-msg ' + (ok ? 'lskypro-msg-ok' : 'lskypro-msg-err');
                el.innerHTML = msg;
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
                xhr.onload = function() {
                    document.getElementById('lskypro-test-loading').style.display = 'none';
                    btn.disabled = false;
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success) {
                            showResult('lskypro-test-result', true, '✅ 连接成功！欢迎 ' + r.name);
                            loadStrategies();
                            loadAlbums();
                        } else {
                            showResult('lskypro-test-result', false, '❌ ' + (r.message || '连接失败'));
                        }
                    } catch(e) {
                        showResult('lskypro-test-result', false, '❌ 响应格式错误');
                    }
                };
                xhr.onerror = function() {
                    document.getElementById('lskypro-test-loading').style.display = 'none';
                    btn.disabled = false;
                    showResult('lskypro-test-result', false, '❌ 网络错误');
                };
                xhr.send(fd);
            };

            // 加载策略列表
            function loadStrategies() {
                var api = getVal('api');
                var token = getVal('token');
                if (!api || !token) return;

                var fd = new FormData();
                fd.append('__lskypro_action', 'get_strategies');
                fd.append('api', api);
                fd.append('token', token);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.onload = function() {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success && r.strategies) {
                            var html = '';
                            r.strategies.forEach(function(s) {
                                html += '<span class="lskypro-list-item">ID:' + s.id + ' ' + s.name + '</span>';
                            });
                            document.getElementById('lskypro-strategies-list').innerHTML = html;
                            document.getElementById('lskypro-strategies-section').style.display = 'block';
                        }
                    } catch(e) {}
                };
                xhr.send(fd);
            }

            // 加载相册列表
            function loadAlbums() {
                var api = getVal('api');
                var token = getVal('token');
                if (!api || !token) return;

                var fd = new FormData();
                fd.append('__lskypro_action', 'get_albums');
                fd.append('api', api);
                fd.append('token', token);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.onload = function() {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.success && r.albums) {
                            var html = '';
                            r.albums.forEach(function(a) {
                                html += '<span class="lskypro-list-item">ID:' + a.id + ' ' + a.name + '</span>';
                            });
                            document.getElementById('lskypro-albums-list').innerHTML = html;
                            document.getElementById('lskypro-albums-section').style.display = 'block';
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
        $form->addInput(new Hidden('personal'));
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
            $params = ['file' => new \CURLFile($tmpFile, mime_content_type($tmpFile), $file['name'])];

            if ($apiVersion === 'v2') {
                if (!empty($options->strategy_id)) $params['strategy_id'] = $options->strategy_id;
                if (!empty($options->album_id)) $params['album_id'] = $options->album_id;
                $params['is_public'] = ($options->permission === '0') ? 0 : 1;
            } else {
                if (!empty($options->permission)) $params['permission'] = $options->permission;
                if (!empty($options->album_id)) $params['album_id'] = $options->album_id;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
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
                    $imageUrl = $json['data']['links']['url'] ?? '';
                }
            }

            if (empty($imageUrl)) return false;

            return [
                'name' => $file['name'],
                'path' => $imageUrl,
                'size' => $file['size'] ?? filesize($tmpFile),
                'type' => $ext,
                'mime' => mime_content_type($tmpFile),
            ];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 本地上传（回退）
     */
    private static function localUpload($file, $ext)
    {
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/' . date('Y') . '/' . date('m');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = sprintf('%u.%s', crc32(uniqid()), $ext);
        $path = $uploadDir . '/' . $filename;

        $tmpFile = $file['tmp_name'] ?? ($file['bytes'] ?? ($file['bits'] ?? ''));
        if (empty($tmpFile)) return false;

        if (!@move_uploaded_file($tmpFile, $path) && !@copy($tmpFile, $path)) {
            return false;
        }

        return [
            'name' => $file['name'],
            'path' => '/usr/uploads/' . date('Y') . '/' . date('m') . '/' . $filename,
            'size' => $file['size'] ?? filesize($path),
            'type' => $ext,
            'mime' => mime_content_type($path),
        ];
    }

    /**
     * 处理自定义AJAX请求（测试连接/策略/相册）
     * 在config页面输出前被调用
     */
    public static function handleAjax()
    {
        if (empty($_POST['__lskypro_action'])) return;

        $action = $_POST['__lskypro_action'];
        $api = rtrim(trim($_POST['api'] ?? ''), '/');
        $token = trim($_POST['token'] ?? '');
        $apiVersion = trim($_POST['api_version'] ?? 'v2');

        header('Content-Type: application/json');

        if (empty($api) || empty($token)) {
            echo json_encode(['success' => false, 'message' => '请填写API网址和Token']);
            exit;
        }

        switch ($action) {
            case 'test_connection':
                $url = ($apiVersion === 'v2')
                    ? $api . '/api/v2/user/profile'
                    : $api . '/api/' . $apiVersion . '/profile';

                $response = self::httpGet($url, $token);
                $data = json_decode($response, true);

                if (!$data) {
                    echo json_encode(['success' => false, 'message' => '连接失败']);
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
        curl_close($ch);
        return $response;
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
        return in_array($ext, ['gif', 'jpg', 'jpeg', 'png', 'tiff', 'bmp', 'ico', 'psd', 'webp']);
    }
}

// 拦截自定义AJAX请求（在config页面渲染前处理）
Plugin::handleAjax();
