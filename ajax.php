<?php
/**
 * LskyPro AJAX处理文件
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class LskyPro_Ajax
{
    /**
     * 处理AJAX请求
     */
    public static function handle()
    {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        switch ($action) {
            case 'test_connection':
                self::testConnection();
                break;
            case 'get_strategies':
                self::getStrategies();
                break;
            case 'get_albums':
                self::getAlbums();
                break;
            default:
                self::jsonError('未知操作');
        }
    }
    
    /**
     * 测试连接
     */
    private static function testConnection()
    {
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $tokens = isset($_POST['tokens']) ? trim($_POST['tokens']) : '';
        $apiVersion = isset($_POST['api_version']) ? trim($_POST['api_version']) : 'v2';
        
        if (empty($domain) || empty($tokens)) {
            self::jsonError('请先填写API网址和Tokens');
        }
        
        $url = ($apiVersion === 'v2') ? 
            $domain . '/api/v2/user/profile' : 
            $domain . '/api/' . $apiVersion . '/profile';
        
        $response = self::httpGet($url, array(
            'Authorization: Bearer ' . $tokens,
            'Accept: application/json'
        ));
        
        if ($response === false) {
            self::jsonError('连接失败，请检查网络');
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            self::jsonError('响应格式错误');
        }
        
        if ($apiVersion === 'v2') {
            if (isset($data['status']) && $data['status'] === 'success') {
                $name = isset($data['data']['name']) ? $data['data']['name'] : '未知用户';
                self::jsonSuccess('连接成功，欢迎 ' . $name);
            }
        } else {
            if (isset($data['status']) && $data['status']) {
                $name = isset($data['data']['name']) ? $data['data']['name'] : '未知用户';
                self::jsonSuccess('连接成功，欢迎 ' . $name);
            }
        }
        
        self::jsonError(isset($data['message']) ? $data['message'] : '连接失败');
    }
    
    /**
     * 获取策略列表
     */
    private static function getStrategies()
    {
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $tokens = isset($_POST['tokens']) ? trim($_POST['tokens']) : '';
        
        if (empty($domain) || empty($tokens)) {
            self::jsonError('请先填写API网址和Tokens');
        }
        
        $url = $domain . '/api/v1/strategies';
        
        $response = self::httpGet($url, array(
            'Authorization: Bearer ' . $tokens,
            'Accept: application/json'
        ));
        
        if ($response === false) {
            self::jsonError('获取策略列表失败');
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['status']) || !$data['status']) {
            self::jsonError(isset($data['message']) ? $data['message'] : '获取策略列表失败');
        }
        
        $strategies = array();
        $strategyList = isset($data['data']['strategies']) ? $data['data']['strategies'] : array();
        
        foreach ($strategyList as $item) {
            $strategies[] = array(
                'id' => $item['id'],
                'name' => $item['name']
            );
        }
        
        self::jsonSuccess('获取成功', array('strategies' => $strategies));
    }
    
    /**
     * 获取相册列表
     */
    private static function getAlbums()
    {
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $tokens = isset($_POST['tokens']) ? trim($_POST['tokens']) : '';
        
        if (empty($domain) || empty($tokens)) {
            self::jsonError('请先填写API网址和Tokens');
        }
        
        $url = $domain . '/api/v1/albums';
        
        $response = self::httpGet($url, array(
            'Authorization: Bearer ' . $tokens,
            'Accept: application/json'
        ));
        
        if ($response === false) {
            self::jsonError('获取相册列表失败');
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['status']) || !$data['status']) {
            self::jsonError(isset($data['message']) ? $data['message'] : '获取相册列表失败');
        }
        
        $albums = array();
        $albumList = isset($data['data']['data']) ? $data['data']['data'] : array();
        
        foreach ($albumList as $item) {
            $albums[] = array(
                'id' => $item['id'],
                'name' => $item['name']
            );
        }
        
        self::jsonSuccess('获取成功', array('albums' => $albums));
    }
    
    /**
     * HTTP GET请求
     */
    private static function httpGet($url, $headers = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return false;
        }
        
        return $response;
    }
    
    /**
     * 返回成功JSON
     */
    private static function jsonSuccess($message, $data = array())
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(array(
            'success' => true,
            'message' => $message
        ), $data));
        exit;
    }
    
    /**
     * 返回错误JSON
     */
    private static function jsonError($message)
    {
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => $message
        ));
        exit;
    }
}

if (isset($_POST['action'])) {
    LskyPro_Ajax::handle();
}
