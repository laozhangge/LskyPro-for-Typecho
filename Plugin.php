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
 * @version 2.0.5
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

    public static function deactivate() {}

    public static function config(Form $form)
    {
        $form->addInput(new Text('api', NULL, '', 'API网址：', '包含 http(s)://，不带/结尾。示例：<code>https://pic.laozhang.org</code>'));
        $form->addInput(new Text('token', NULL, '', 'API Token：', '示例：<code>1|xxx</code>'));
        $form->addInput(new Text('api_version', NULL, 'v2', 'API版本：', '<code>v1</code> 或 <code>v2</code>，默认 v2'));
        $form->addInput(new Text('permission', NULL, '1', '图片权限：', '<code>1</code> 公开，<code>0</code> 私有'));
        $form->addInput(new Text('strategy_id', NULL, '', '存储策略ID：', '<span id="lskypro-str-hint">留空默认，测试后自动显示</span>'));
        $form->addInput(new Text('album_id', NULL, '', '相册ID：', '<span id="lskypro-alb-hint">留空不指定，测试后自动显示</span>'));
        $form->addInput(new Text('max_size', NULL, '10', '最大上传(MB)：', '默认10'));
        $form->addInput(new Text('format', NULL, 'markdown', '插入格式：', '<code>markdown</code> / <code>url</code> / <code>html</code> / <code>bbcode</code>'));

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
        window.onload=function(){
            var AJ="' . htmlspecialchars($ajaxUrl, ENT_QUOTES) . '";
            function v(n){var e=document.querySelector("[name=\\"config["+n+"]\\"]");return e?e.value.trim():""}
            function el(n){return document.querySelector("[name=\\"config["+n+"]\\"]")}
            function msg(ok,t){var m=document.getElementById("lskypro-test-msg");m.style.display="block";m.style.borderColor=ok?"#46b450":"#dc3232";m.style.background=ok?"#f0f8f0":"#fdf0f0";m.innerHTML=t}
            function rl(vid,lid,items,fld){
                var c=document.getElementById(vid),inp=el(fld);c.innerHTML="";
                items.forEach(function(i){
                    var s=document.createElement("span");s.style.cssText="display:inline-block;margin:3px;padding:4px 10px;background:#f0f0f0;border:1px solid #ddd;border-radius:3px;font-size:12px;cursor:pointer";
                    if(inp&&String(i.id)===String(inp.value)){s.style.background="#0073aa";s.style.color="#fff"}
                    s.textContent=i.name+" (ID:"+i.id+")";
                    s.onclick=function(){if(inp)inp.value=i.id;var a=c.querySelectorAll("span");for(var j=0;j<a.length;j++){a[j].style.background="#f0f0f0";a[j].style.color=""}s.style.background="#0073aa";s.style.color="#fff"};
                    c.appendChild(s);
                });
                document.getElementById(lid).textContent="已加载 "+items.length+" 个，点击选择";
            }
            function xp(fd,cb){var x=new XMLHttpRequest();x.open("POST",AJ,true);x.timeout=15000;x.onload=function(){try{cb(null,JSON.parse(x.responseText))}catch(e){cb(e)}};x.onerror=function(){cb(1)};x.ontimeout=function(){cb(1)};x.send(fd)}
            document.getElementById("lskypro-test-btn").onclick=function(){
                var api=v("api"),tok=v("token"),ver=v("api_version")||"v2",btn=this;
                if(!api||!tok){msg(false,"请先填写API网址和Token");return}
                btn.disabled=true;document.getElementById("lskypro-test-load").style.display="inline";
                var fd=new FormData();fd.append("__lskypro_action","test_connection");fd.append("api",api);fd.append("token",tok);fd.append("api_version",ver);
                xp(fd,function(e,r){
                    document.getElementById("lskypro-test-load").style.display="none";btn.disabled=false;
                    if(e){msg(false,"❌ 响应格式错误");return}
                    if(r.success){
                        msg(true,"✅ 连接成功！欢迎 "+r.name);
                        var f2=new FormData();f2.append("__lskypro_action","get_strategies");f2.append("api",api);f2.append("token",tok);
                        xp(f2,function(e2,r2){if(!e2&&r2.success&&r2.strategies)rl("lskypro-str-list","lskypro-str-hint",r2.strategies,"strategy_id")});
                        var f3=new FormData();f3.append("__lskypro_action","get_albums");f3.append("api",api);f3.append("token",tok);
                        xp(f3,function(e3,r3){if(!e3&&r3.success&&r3.albums)rl("lskypro-alb-list","lskypro-alb-hint",r3.albums,"album_id")});
                    }else{msg(false,"❌ "+(r.message||"连接失败"))}
                });
            };
        };
        </script>';
    }

    public static function injectScript()
    {
        try { $opts = Options::alloc()->plugin('LskyPro'); } catch (\Exception $e) { $opts = null; }
        echo '<script>window.__lskyFormat="' . htmlspecialchars($opts->format ?? 'markdown', ENT_QUOTES) . '";</script>' . "\n";
    }

    public static function personalConfig(Form $form) {}

    public static function uploadHandle($file)
    {
        if (empty($file['name'])) return false;
        $ext = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)));
        if (empty($ext)) return false;

        $options = Options::alloc()->plugin('LskyPro');
        $api = rtrim($options->api ?? '', '/');
        $token = $options->token ?? '';
        $apiVersion = $options->api_version ?: 'v2';
        if (empty($api) || empty($token)) return false;

        $tmpFile = $file['tmp_name'] ?? ($file['bytes'] ?? ($file['bits'] ?? ''));
        if (empty($tmpFile) || !is_readable($tmpFile)) return false;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api . '/api/' . $apiVersion . '/upload');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Accept: application/json']);

        $mimes = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp','svg'=>'image/svg+xml'];
        $params = ['file' => new \CURLFile($tmpFile, $mimes[$ext] ?? 'application/octet-stream', $file['name'])];
        $params['permission'] = $options->permission ?? '1';
        $sid = $options->strategy_id ?? '';
        if (!empty($sid)) $params[$apiVersion === 'v2' ? 'storage_id' : 'strategy_id'] = intval($sid);
        $aid = $options->album_id ?? '';
        if (!empty($aid)) $params['album_id'] = intval($aid);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error || !$response) return false;

        $json = json_decode($response, true);
        if (!$json) return false;

        $imageUrl = '';
        if ($apiVersion === 'v2') {
            if (($json['status'] ?? '') === 'success') $imageUrl = $json['data']['public_url'] ?? $json['data']['url'] ?? '';
        } else {
            if (!empty($json['status'])) $imageUrl = $json['data']['links']['url'] ?? $json['data']['url'] ?? '';
        }
        if (empty($imageUrl)) return false;
        if (!preg_match('#^https?://#i', $imageUrl)) $imageUrl = rtrim($api, '/') . '/' . ltrim($imageUrl, '/');

        return ['name' => $file['name'], 'path' => $imageUrl, 'size' => $file['size'] ?? 0, 'type' => $ext, 'ext' => $ext];
    }
}
