<?php
namespace TypechoPlugin\LskyPro;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 兰空图床上传
 * @package LskyPro
 * @author 老张博客
 * @version 0.0.1
 * @link https://github.com/laozhangge/LskyPro-for-Typecho
 */
class Plugin implements PluginInterface
{
    public static function activate()
    {
        \Typecho\Plugin::factory('Widget_Upload')->uploadHandle = __CLASS__ . '::uploadHandle';
    }

    public static function deactivate() {}

    public static function config(Form $form)
    {
        $form->addInput(new Text('api', NULL, '', 'API网址：', '包含 http(s):// 示例：<code>https://pic.laozhang.org</code>'));
        $form->addInput(new Text('token', NULL, '', 'Token：', '示例：<code>1|xxx</code>'));
        $form->addInput(new Text('api_version', NULL, 'v2', 'API版本：', 'v1 或 v2'));
        $form->addInput(new Text('permission', NULL, '1', '权限：', '1=公开 0=私有'));
        $form->addInput(new Text('strategy_id', NULL, '', '策略ID：', '留空默认'));
        $form->addInput(new Text('album_id', NULL, '', '相册ID：', '留空不指定'));
        $form->addInput(new Text('max_size', NULL, '10', '上传限制MB：', '默认10'));
        $form->addInput(new Text('format', NULL, 'markdown', '插入格式：', 'markdown / url / html / bbcode'));

        // 注入测试连接的JS和AJAX地址
        $ajaxUrl = rtrim(\Typecho\Common::url('usr/plugins/LskyPro/ajax.php', Options::alloc()->siteUrl), '/');
        echo '<script>window.__lskyAjaxUrl="' . htmlspecialchars($ajaxUrl, ENT_QUOTES) . '";</script>';
        echo '<script src="' . \Typecho\Common::url('usr/plugins/LskyPro/js/config.js', Options::alloc()->siteUrl) . '"></script>';
    }

    public static function personalConfig(Form $form) {}

    public static function uploadHandle($file)
    {
        if (empty($file['name'])) return false;
        $ext = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)));
        if (empty($ext)) return false;

        $o = Options::alloc()->plugin('LskyPro');
        $api = rtrim($o->api ?? '', '/');
        $token = $o->token ?? '';
        $ver = $o->api_version ?: 'v2';
        if (empty($api) || empty($token)) return false;

        $tmp = $file['tmp_name'] ?? ($file['bytes'] ?? ($file['bits'] ?? ''));
        if (empty($tmp) || !is_readable($tmp)) return false;

        $mimes = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp','svg'=>'image/svg+xml'];
        $params = ['file' => new \CURLFile($tmp, $mimes[$ext] ?? 'application/octet-stream', $file['name'])];
        $params['permission'] = $o->permission ?? '1';
        $sid = $o->strategy_id ?? '';
        if ($sid) $params[$ver === 'v2' ? 'storage_id' : 'strategy_id'] = intval($sid);
        $aid = $o->album_id ?? '';
        if ($aid) $params['album_id'] = intval($aid);

        $ch = curl_init($api . '/api/' . $ver . '/upload');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $params,
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err || !$res) return false;

        $json = json_decode($res, true);
        if (!$json) return false;

        $url = '';
        if ($ver === 'v2') {
            if (($json['status'] ?? '') === 'success') $url = $json['data']['public_url'] ?? $json['data']['url'] ?? '';
        } else {
            if (!empty($json['status'])) $url = $json['data']['links']['url'] ?? $json['data']['url'] ?? '';
        }
        if (empty($url)) return false;
        if (!preg_match('#^https?://#i', $url)) $url = rtrim($api, '/') . '/' . ltrim($url, '/');

        return ['name' => $file['name'], 'path' => $url, 'size' => $file['size'] ?? 0, 'type' => $ext, 'ext' => $ext];
    }
}
