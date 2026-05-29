<?php
header('Content-Type: application/json; charset=utf-8');
$action = $_POST['__lskypro_action'] ?? '';
$api = rtrim(trim($_POST['api'] ?? ''), '/');
$token = trim($_POST['token'] ?? '');
$ver = trim($_POST['api_version'] ?? 'v2');
if (empty($api) || empty($token)) { echo '{"success":false,"message":"请填写API网址和Token"}'; exit; }

function lsky_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return $err ? null : $res;
}

switch ($action) {
    case 'test_connection':
        $url = ($ver === 'v2') ? $api.'/api/v2/user/profile' : $api.'/api/v1/profile';
        $res = lsky_get($url, $token);
        $data = $res ? json_decode($res, true) : null;
        if (!$data) { echo '{"success":false,"message":"连接失败"}'; exit; }
        $ok = ($ver === 'v2') ? (($data['status'] ?? '') === 'success') : !empty($data['status']);
        if ($ok) { echo json_encode(['success' => true, 'name' => $data['data']['name'] ?? '用户']); exit; }
        echo json_encode(['success' => false, 'message' => $data['message'] ?? '连接失败']);
        exit;

    case 'get_strategies':
        $res = lsky_get($api.'/api/v1/strategies', $token);
        $data = $res ? json_decode($res, true) : null;
        if ($data && !empty($data['status'])) {
            $list = [];
            foreach (($data['data']['strategies'] ?? []) as $s) $list[] = ['id'=>$s['id'],'name'=>$s['name']];
            echo json_encode(['success'=>true,'strategies'=>$list]); exit;
        }
        echo '{"success":false,"message":"获取失败"}'; exit;

    case 'get_albums':
        $res = lsky_get($api.'/api/v1/albums', $token);
        $data = $res ? json_decode($res, true) : null;
        if ($data && !empty($data['status'])) {
            $list = [];
            foreach (($data['data']['data'] ?? []) as $a) $list[] = ['id'=>$a['id'],'name'=>$a['name']];
            echo json_encode(['success'=>true,'albums'=>$list]); exit;
        }
        echo '{"success":false,"message":"获取失败"}'; exit;
}
echo '{"success":false,"message":"未知操作"}';
