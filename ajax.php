<?php
/**
 * LskyPro AJAX 处理
 * 单独文件，避免干扰 config() 方法
 */
require __DIR__ . '/../../../config.inc.php';

\Typecho\Common::init();
\Typecho\Widget::alloc('Widget_Options');

// 必须登录且有权限
\Typecho\Widget::widget('Widget_User')->pass('administrator');

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
    curl_close($ch);
    return $error ? false : $response;
}

switch ($action) {
    case 'test_connection':
        $url = ($apiVersion === 'v2')
            ? $api . '/api/v2/user/profile'
            : $api . '/api/v1/profile';

        $response = httpGet($url, $token);
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
        $response = httpGet($url, $token);
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
        $response = httpGet($url, $token);
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
