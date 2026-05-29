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
 * @version 1.0.0
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

    public static function config(Form $form)
    {
        $api = new Text('api', NULL, '', 'API网址：', '填写兰空图床域名，包含 http(s)://，不带 / 结尾。示例：<code>https://pic.laozhang.org</code>');
        $form->addInput($api);

        $token = new Text('token', NULL, '', 'API Token：', '在兰空图床后台获取的API令牌。示例：<code>1|UYsgSjmtTkPjS8qPaLl98dJwdVtU492vQbDFI6pg</code>');
        $form->addInput($token);

        $apiVersion = new Text('api_version', NULL, 'v2', 'API版本：', '填写 <code>v1</code> 或 <code>v2</code>，默认 v2');
        $form->addInput($apiVersion);

        $permission = new Text('permission', NULL, '1', '图片权限：', '<code>1</code> 公开，<code>0</code> 私有');
        $form->addInput($permission);

        $strategyId = new Text('strategy_id', NULL, '', '存储策略ID：', '留空使用默认策略。可在兰空图床后台查看策略ID');
        $form->addInput($strategyId);

        $albumId = new Text('album_id', NULL, '', '相册ID：', '留空不指定相册。可在兰空图床后台查看相册ID');
        $form->addInput($albumId);

        $maxSize = new Text('max_size', NULL, '10', '最大上传大小(MB)：', '单位MB，默认10');
        $form->addInput($maxSize);

        echo '<p style="color:#999;font-size:12px;">兰空图床上传 v1.0.0 &nbsp;|&nbsp; 作者：<a href="https://laozhang.org" target="_blank">老张博客</a> &nbsp;|&nbsp; <a href="https://github.com/laozhangge/LskyPro-for-Typecho" target="_blank">GitHub</a></p>';
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function uploadHandle($file)
    {
        if (empty($file['name'])) {
            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        if (empty($ext)) {
            return false;
        }

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

        $mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';

        $params = ['file' => new \CURLFile($tmpFile, $mime, $file['name'])];
        $params['permission'] = $options->permission ?? '1';

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
            'type' => $ext,
            'ext' => $ext,
        ];
    }
}
