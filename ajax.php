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
}

echo json_encode(['success' => false, 'message' => '未知操作: ' . $action]);
