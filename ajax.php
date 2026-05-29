<?php
/**
 * LskyPro AJAX 处理 - 独立文件，不加载Typecho框架
 * 直接代理请求到兰空图床API
 */
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['__lskypro_action'] ?? '';
$api = rtrim(trim($_POST['api'] ?? ''), '/');
$token = trim($_POST['token'] ?? '');
$apiVersion = trim($_POST['api_version'] ?? 'v2');

if (empty($api) || empty($token)) {
    echo json_encode(['success' => false, 'message' => '请填写API网址和Token']);
    exit;
}

function httpGet($url, $token) {
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($error) return ['error' => $error];
    if ($httpCode >= 400) return ['error' => 'HTTP ' . $httpCode];
    return ['data' => $response];
}

$result = null;

switch ($action) {
    case 'test_connection':
        $url = ($apiVersion === 'v2')
            ? $api . '/api/v2/user/profile'
            : $api . '/api/v1/profile';

        $resp = httpGet($url, $token);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'message' => '请求失败: ' . $resp['error']]);
            exit;
        }

        $data = json_decode($resp['data'], true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'API返回无效JSON']);
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
        $resp = httpGet($url, $token);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'message' => '请求失败: ' . $resp['error']]);
            exit;
        }

        $data = json_decode($resp['data'], true);
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
        $resp = httpGet($url, $token);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'message' => '请求失败: ' . $resp['error']]);
            exit;
        }

        $data = json_decode($resp['data'], true);
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

    case 'paste_upload':
        // 粘贴上传：接收文件，上传到兰空图床，返回格式化内容
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => false, 'message' => '未接收到文件或上传出错']);
            exit;
        }

        $file = $_FILES['file'];
        $format = trim($_POST['format'] ?? 'markdown');
        $customName = !empty($_POST['name']) ? trim($_POST['name']) : '';

        // 扩展名校验
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $allowed = ['jpg','jpeg','png','gif','webp','bmp','svg','tiff','ico','psd'];
        if (!in_array($ext, $allowed)) {
            echo json_encode(['status' => false, 'message' => '不支持的图片格式']);
            exit;
        }

        // 上传到兰空图床
        $uploadUrl = $api . '/api/' . $apiVersion . '/upload';
        $mimes = [
            'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
            'gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp',
            'svg'=>'image/svg+xml','tiff'=>'image/tiff','ico'=>'image/x-icon',
            'psd'=>'image/vnd.adobe.photoshop',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';
        $params = ['file' => new CURLFile($file['tmp_name'], $mime, $file['name'])];
        $params['permission'] = intval($_POST['permission'] ?? 1);
        $params['is_public'] = intval($_POST['permission'] ?? 1);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || !$response) {
            echo json_encode(['status' => false, 'message' => '上传请求失败: ' . $error]);
            exit;
        }

        $json = json_decode($response, true);
        if (!$json || empty($json['status'])) {
            echo json_encode(['status' => false, 'message' => $json['message'] ?? '上传失败']);
            exit;
        }

        // 解析图片URL
        if ($apiVersion === 'v2') {
            $imageUrl = $json['data']['public_url'] ?? $json['data']['url'] ?? '';
        } else {
            $imageUrl = $json['data']['links']['url'] ?? $json['data']['url'] ?? '';
        }
        if (empty($imageUrl)) {
            echo json_encode(['status' => false, 'message' => '未获取到图片URL']);
            exit;
        }
        if (!preg_match('#^https?://#i', $imageUrl)) {
            $imageUrl = rtrim($api, '/') . '/' . ltrim($imageUrl, '/');
        }

        $imageName = $customName ? pathinfo($customName, PATHINFO_FILENAME) : pathinfo($json['data']['origin_name'] ?? $file['name'], PATHINFO_FILENAME);

        // 格式化内容
        switch ($format) {
            case 'url':
                $content = $imageUrl;
                break;
            case 'html':
                $content = '<img src="' . htmlspecialchars($imageUrl, ENT_QUOTES) . '" alt="' . htmlspecialchars($imageName, ENT_QUOTES) . '" />';
                break;
            case 'bbcode':
                $content = '[img]' . $imageUrl . '[/img]';
                break;
            case 'markdown':
            default:
                $content = '![' . $imageName . '](' . $imageUrl . ')';
                break;
        }

        echo json_encode([
            'status'  => true,
            'message' => '上传成功',
            'data'    => ['content' => $content, 'url' => $imageUrl, 'name' => $imageName],
        ], JSON_UNESCAPED_UNICODE);
        exit;
}

echo json_encode(['success' => false, 'message' => '未知操作: ' . $action]);
