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
 * @version 2.0.3
 * @link https://github.com/laozhangge/LskyPro-for-Typecho
 */

class Plugin implements PluginInterface
{
    public static function activate()
    {
        \Typecho\Plugin::factory('Widget_Upload')->uploadHandle = __CLASS__ . '::uploadHandle';
        \Typecho\Plugin::factory('admin/write-post.php')->bottom = __CLASS__ . '::injectScript';
        \Typecho\Plugin::factory('admin/write-page.php')->bottom = __CLASS__ . '::injectScript';
    }

    public static function deactivate()
    {
    }

    public static function config(Form $form)
    {
        $api = new Text('api', NULL, '', 'API网址：', '填写兰空图床域名，包含 http(s)://，不带 / 结尾。示例：<code>https://pic.laozhang.org</code>');
        $form->addInput($api);

        $token = new Text('token', NULL, '', 'API Token：', '在兰空图床后台获取的API令牌。示例：<code>1|xxx</code>');
        $form->addInput($token);

        $apiVersion = new Text('api_version', NULL, 'v2', 'API版本：', '填写 <code>v1</code> 或 <code>v2</code>，默认 v2');
        $form->addInput($apiVersion);

        $permission = new Text('permission', NULL, '1', '图片权限：', '<code>1</code> 公开，<code>0</code> 私有');
        $form->addInput($permission);

        $strategyId = new Text('strategy_id', NULL, '', '存储策略ID：', '<span id="lskypro-str-hint">留空使用默认策略，测试连接后自动显示可选策略</span>');
        $form->addInput($strategyId);

        $albumId = new Text('album_id', NULL, '', '相册ID：', '<span id="lskypro-alb-hint">留空不指定相册，测试连接后自动显示可选相册</span>');
        $form->addInput($albumId);

        $maxSize = new Text('max_size', NULL, '10', '最大上传大小(MB)：', '单位MB，默认10');
        $form->addInput($maxSize);

        $format = new Text('format', NULL, 'markdown', '插入格式：', '可选：<code>markdown</code> / <code>url</code> / <code>html</code> / <code>bbcode</code>，默认 markdown');
        $form->addInput($format);

        // 测试连接按钮 + 策略/相册列表（在表单末尾输出）
        $ajaxUrl = rtrim(Options::alloc()->siteUrl, '/') . '/usr/plugins/LskyPro/ajax.php';

        echo '<div style="margin:15px 0;padding:15px 20px;background:#fff;border:1px solid #ddd;border-radius:4px">';
        echo '<h4 style="margin:0 0 10px">测试连接</h4>';
        echo '<p><button type="button" id="lskypro-test-btn" style="padding:6px 16px;background:#0073aa;color:#fff;border:none;border-radius:3px;cursor:pointer">测试连接</button>';
        echo ' <span id="lskypro-test-load" style="display:none;color:#666;font-size:12px">正在测试...</span></p>';
        echo '<div id="lskypro-test-msg" style="display:none;padding:8px 12px;margin:8px 0;border-left:4px solid;font-size:13px"></div>';
        echo '<div id="lskypro-str-list" style="margin:5px 0"></div>';
        echo '<div id="lskypro-alb-list" style="margin:5px 0"></div>';
        echo '</div>';

        echo '<script>
        window.addEventListener("load", function(){
        var AJ="' . htmlspecialchars($ajaxUrl, ENT_QUOTES) . '";
        function getVal(n){var e=document.querySelector("[name=\\"config["+n+"]\\"]");return e?e.value.trim():""}
        function getEl(n){return document.querySelector("[name=\\"config["+n+"]\\"]")}
        function msg(ok,t){var m=document.getElementById("lskypro-test-msg");m.style.display="block";m.style.borderColor=ok?"#46b450":"#dc3232";m.style.background=ok?"#f0f8f0":"#fdf0f0";m.innerHTML=t}
        function showList(listId,hintId,items,fieldName){
            var c=document.getElementById(listId),inp=getEl(fieldName);c.innerHTML="";
            items.forEach(function(i){
                var s=document.createElement("span");
                s.style.cssText="display:inline-block;margin:3px;padding:4px 10px;background:#f0f0f0;border:1px solid #ddd;border-radius:3px;font-size:12px;cursor:pointer";
                if(inp&&String(i.id)===String(inp.value)){s.style.background="#0073aa";s.style.color="#fff"}
                s.textContent=i.name+" (ID:"+i.id+")";
                s.onclick=function(){
                    if(inp)inp.value=i.id;
                    var a=c.querySelectorAll("span");for(var j=0;j<a.length;j++){a[j].style.background="#f0f0f0";a[j].style.color=""}
                    s.style.background="#0073aa";s.style.color="#fff";
                };c.appendChild(s);
            });
            document.getElementById(hintId).textContent="已加载 "+items.length+" 个，点击选择";
        }
        function xpost(fd,cb){
            var x=new XMLHttpRequest();x.open("POST",AJ,true);x.timeout=15000;
            x.onload=function(){try{cb(null,JSON.parse(x.responseText))}catch(e){cb(e)}};
            x.onerror=function(){cb(new Error("网络错误"))};x.ontimeout=function(){cb(new Error("超时"))};x.send(fd);
        }
        document.getElementById("lskypro-test-btn").onclick=function(){
            var api=getVal("api"),tok=getVal("token"),ver=getVal("api_version")||"v2",btn=this;
            if(!api||!tok){msg(false,"请先填写API网址和Token");return}
            btn.disabled=true;document.getElementById("lskypro-test-load").style.display="inline";
            var fd=new FormData();fd.append("__lskypro_action","test_connection");fd.append("api",api);fd.append("token",tok);fd.append("api_version",ver);
            xpost(fd,function(err,r){
                document.getElementById("lskypro-test-load").style.display="none";btn.disabled=false;
                if(err){msg(false,"❌ 响应格式错误");return}
                if(r.success){
                    msg(true,"✅ 连接成功！欢迎 "+r.name);
                    var fd2=new FormData();fd2.append("__lskypro_action","get_strategies");fd2.append("api",api);fd2.append("token",tok);
                    xpost(fd2,function(e2,r2){if(!e2&&r2.success&&r2.strategies)showList("lskypro-str-list","lskypro-str-hint",r2.strategies,"strategy_id")});
                    var fd3=new FormData();fd3.append("__lskypro_action","get_albums");fd3.append("api",api);fd3.append("token",tok);
                    xpost(fd3,function(e3,r3){if(!e3&&r3.success&&r3.albums)showList("lskypro-alb-list","lskypro-alb-hint",r3.albums,"album_id")});
                }else{msg(false,"❌ "+(r.message||"连接失败"))}
            });
        };
        });
        </script>';
    }

    public static function injectScript()
    {
        try { $opts = Options::alloc()->plugin('LskyPro'); } catch (\Exception $e) { $opts = null; }
        $format = $opts->format ?? 'markdown';
        echo '<script>window.__lskyFormat="' . htmlspecialchars($format, ENT_QUOTES) . '";</script>' . "\n";
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

        if (!preg_match('#^https?://#i', $imageUrl)) {
            $imageUrl = rtrim($api, '/') . '/' . ltrim($imageUrl, '/');
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
