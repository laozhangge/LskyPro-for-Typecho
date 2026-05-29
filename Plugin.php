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
 * @version 1.0.1
 * @link https://github.com/laozhangge/LskyPro-for-Typecho
 */

class Plugin implements PluginInterface
{
    public static function activate()
    {
        \Typecho\Plugin::factory('Widget_Upload')->uploadHandle = __CLASS__ . '::uploadHandle';
        \Typecho\Plugin::factory('Widget_Upload')->attachmentHandle = __CLASS__ . '::attachmentHandle';
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

        $token = new Text('token', NULL, '', 'API Token：', '在兰空图床后台获取的API令牌。示例：<code>1|UYsgSjmtTkPjS8qPaLl98dJwdVtU492vQbDFI6pg</code>');
        $form->addInput($token);

        $apiVersion = new Text('api_version', NULL, 'v2', 'API版本：', '填写 <code>v1</code> 或 <code>v2</code>，默认 v2');
        $form->addInput($apiVersion);

        $permission = new Text('permission', NULL, '1', '图片权限：', '<code>1</code> 公开，<code>0</code> 私有');
        $form->addInput($permission);

        $strategyId = new Text('strategy_id', NULL, '', '存储策略ID：', '<span id="lskypro-str-hint">留空使用默认策略</span>');
        $form->addInput($strategyId);

        $albumId = new Text('album_id', NULL, '', '相册ID：', '<span id="lskypro-alb-hint">留空不指定相册</span>');
        $form->addInput($albumId);

        $format = new Text('format', NULL, 'markdown', '插入格式：', '粘贴上传的插入格式：<code>markdown</code>（默认）、<code>url</code>、<code>html</code>、<code>bbcode</code>');
        $form->addInput($format);

        $maxSize = new Text('max_size', NULL, '10', '最大上传大小(MB)：', '单位MB，默认10');
        $form->addInput($maxSize);

        echo <<<HTML
<style>
.lskypro-box{background:#fff;padding:15px 20px;margin:15px 0;border:1px solid #ddd;border-radius:4px}
.lskypro-box h4{margin:0 0 10px;padding-bottom:8px;border-bottom:1px solid #eee}
.lskypro-btn{display:inline-block;padding:6px 16px;background:#0073aa;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:13px}
.lskypro-btn:hover{background:#005a87}
.lskypro-btn:disabled{background:#ccc;cursor:not-allowed}
.lskypro-load{display:none;margin-left:8px;color:#666;font-size:12px}
.lskypro-msg{padding:8px 12px;margin:8px 0;border-left:4px solid;font-size:13px;display:none}
.lskypro-msg-ok{border-color:#46b450;background:#f0f8f0}
.lskypro-msg-err{border-color:#dc3232;background:#fdf0f0}
.lskypro-item{display:inline-block;margin:3px;padding:4px 10px;background:#f0f0f0;border:1px solid #ddd;border-radius:3px;font-size:12px;cursor:pointer}
.lskypro-item:hover{background:#0073aa;color:#fff;border-color:#0073aa}
.lskypro-item-active{background:#0073aa;color:#fff;border-color:#005a87}
</style>
<div class="lskypro-box">
<h4>测试连接</h4>
<p><button type="button" id="lskypro-test-btn" class="lskypro-btn">测试连接</button><span id="lskypro-test-load" class="lskypro-load">正在测试...</span></p>
<div id="lskypro-test-msg" class="lskypro-msg"></div>
<p style="font-size:12px;color:#999;">填写API网址和Token后点击测试，成功后自动加载策略和相册列表，点击即可填入ID。</p>
</div>
<div class="lskypro-box" id="lskypro-str-box" style="display:none"><h4>可用存储策略 <small style="color:#999;">点击填入上方输入框</small></h4><div id="lskypro-str-list"></div></div>
<div class="lskypro-box" id="lskypro-alb-box" style="display:none"><h4>可用相册 <small style="color:#999;">点击填入上方输入框</small></h4><div id="lskypro-alb-list"></div></div>
<p style="color:#999;font-size:12px;">兰空图床上传 v1.0.1 | 作者：<a href="https://laozhang.org" target="_blank">老张博客</a> | <a href="https://github.com/laozhangge/LskyPro-for-Typecho" target="_blank">GitHub</a></p>
<script>
(function(){
var AJ=(function(){var a=document.createElement('a');a.href=window.location.href;return a.protocol+'//'+a.host+'/usr/plugins/LskyPro/ajax.php'})();
function v(n){var e=document.querySelector('[name="config['+n+']"]');if(!e)e=document.querySelector('[name="'+n+'"]');return e?e.value.trim():''}
function el(n){var e=document.querySelector('[name="config['+n+']"]');return e||document.querySelector('[name="'+n+'"]')}
function msg(ok,t){var m=document.getElementById('lskypro-test-msg');m.style.display='block';m.className='lskypro-msg '+(ok?'lskypro-msg-ok':'lskypro-msg-err');m.innerHTML=t}
function rl(cid,bid,items,fld,hid){
var b=document.getElementById(bid),c=document.getElementById(cid),inp=el(fld);
b.style.display='block';c.innerHTML='';
items.forEach(function(i){
var s=document.createElement('span');s.className='lskypro-item';
if(inp&&String(i.id)===String(inp.value))s.className+=' lskypro-item-active';
s.textContent=i.name+' (ID:'+i.id+')';
s.onclick=function(){if(inp)inp.value=i.id;var a=c.querySelectorAll('.lskypro-item');for(var j=0;j<a.length;j++)a[j].className='lskypro-item';s.className='lskypro-item lskypro-item-active'};
c.appendChild(s)});
document.getElementById(hid).textContent='已加载 '+items.length+' 个，点击选择';
}
function xpost(fd,cb){
var x=new XMLHttpRequest();x.open('POST',AJ,true);x.timeout=15000;
x.onload=function(){try{cb(null,JSON.parse(x.responseText))}catch(e){cb(e)}};
x.onerror=function(){cb(new Error('网络错误'))};
x.ontimeout=function(){cb(new Error('超时'))};x.send(fd);
}
document.getElementById('lskypro-test-btn').onclick=function(){
var api=v('api'),tok=v('token'),ver=v('api_version')||'v2',btn=this;
if(!api||!tok){msg(false,'请先填写API网址和Token');return}
btn.disabled=true;document.getElementById('lskypro-test-load').style.display='inline';
var fd=new FormData();fd.append('__lskypro_action','test_connection');fd.append('api',api);fd.append('token',tok);fd.append('api_version',ver);
xpost(fd,function(err,r){
document.getElementById('lskypro-test-load').style.display='none';btn.disabled=false;
if(err){msg(false,'❌ 响应格式错误');return}
if(r.success){msg(true,'✅ 连接成功！欢迎 '+r.name);
var fd2=new FormData();fd2.append('__lskypro_action','get_strategies');fd2.append('api',api);fd2.append('token',tok);
xpost(fd2,function(e2,r2){if(!e2&&r2.success&&r2.strategies)rl('lskypro-str-list','lskypro-str-box',r2.strategies,'strategy_id','lskypro-str-hint')});
var fd3=new FormData();fd3.append('__lskypro_action','get_albums');fd3.append('api',api);fd3.append('token',tok);
xpost(fd3,function(e3,r3){if(!e3&&r3.success&&r3.albums)rl('lskypro-alb-list','lskypro-alb-box',r3.albums,'album_id','lskypro-alb-hint')});
}else{msg(false,'❌ '+(r.message||'连接失败'))}
});
};
})();
</script>
HTML;
    }

    public static function personalConfig(Form $form)
    {
    }

    /**
     * 附件URL处理 - 直接返回存储的完整URL，绕过Typecho的Common::url拼接
     */
    public static function attachmentHandle(array $content): string
    {
        $path = $content['attachment']->path ?? '';
        if (empty($path)) return '';

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return Common::url($path, Options::alloc()->siteUrl);
    }

    /**
     * 注入粘贴上传脚本到编辑器页面
     */
    public static function injectScript()
    {
        echo '<script src="/usr/plugins/LskyPro/assets/paste-upload.js?v=1.0.1"></script>' . "\n";
    }

    /**
     * 处理前端粘贴上传的 AJAX 请求
     * 参考 yeyinghai/Lsky-Upload-pro 的架构：
     * - 不走独立 ajax.php，直接在 Plugin.php 内处理
     * - 从 Options 读取 api/token，无需前端传递
     */
    public static function pasteUploadHandle()
    {
        // 配置检查
        $options = Options::alloc()->plugin('LskyPro');
        if (empty($options->api)) {
            self::_jsonResponse(false, '请先在插件设置中填写 API 地址');
        }
        if (empty($options->token)) {
            self::_jsonResponse(false, '请先在插件设置中填写 Token');
        }

        // 文件检查
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            self::_jsonResponse(false, '未接收到文件或上传出错');
        }

        $file = $_FILES['file'];

        // 扩展名校验
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext);
        $allowed = ['jpg','jpeg','png','gif','webp','bmp','svg','tiff','ico','psd'];
        if (!in_array($ext, $allowed)) {
            self::_jsonResponse(false, '不支持的图片格式');
        }

        // 上传到兰空图床（复用 uploadHandle 的逻辑）
        $api = rtrim($options->api, '/');
        $token = $options->token;
        $apiVersion = $options->api_version ?: 'v2';
        $uploadUrl = $api . '/api/' . $apiVersion . '/upload';

        $mimes = [
            'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
            'gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp',
            'svg'=>'image/svg+xml','tiff'=>'image/tiff','ico'=>'image/x-icon',
            'psd'=>'image/vnd.adobe.photoshop',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';

        $params = ['file' => new \CURLFile($file['tmp_name'], $mime, $file['name'])];
        $params['permission'] = intval($options->permission ?? 1);
        $params['is_public'] = intval($options->permission ?? 1);

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
            self::_jsonResponse(false, '上传请求失败: ' . $error);
        }

        $json = json_decode($response, true);
        if (!$json || empty($json['status'])) {
            self::_jsonResponse(false, $json['message'] ?? '上传失败');
        }

        // 解析图片URL
        if ($apiVersion === 'v2') {
            $imageUrl = $json['data']['public_url'] ?? $json['data']['url'] ?? '';
        } else {
            $imageUrl = $json['data']['links']['url'] ?? $json['data']['url'] ?? '';
        }
        if (empty($imageUrl)) {
            self::_jsonResponse(false, '未获取到图片URL');
        }
        if (!preg_match('#^https?://#i', $imageUrl)) {
            $imageUrl = $api . '/' . ltrim($imageUrl, '/');
        }

        $imageName = pathinfo($json['data']['origin_name'] ?? $file['name'], PATHINFO_FILENAME);

        // 根据配置格式化内容
        $content = self::_formatContent($imageName, $imageUrl);

        self::_jsonResponse(true, '上传成功', [
            'content' => $content,
            'url' => $imageUrl,
            'name' => $imageName,
        ]);
    }

    /**
     * 输出JSON响应并终止
     */
    private static function _jsonResponse(bool $status, string $message, array $data = [])
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 根据配置格式化图片插入内容
     */
    private static function _formatContent(string $name, string $url): string
    {
        $format = Options::alloc()->plugin('LskyPro')->format ?? 'markdown';
        switch ($format) {
            case 'url':
                return $url;
            case 'html':
                return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($name, ENT_QUOTES) . '" />';
            case 'bbcode':
                return '[img]' . $url . '[/img]';
            case 'markdown':
            default:
                return '![' . $name . '](' . $url . ')';
        }
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
        $params['permission'] = intval($options->permission ?? 1);
        $params['is_public'] = intval($options->permission ?? 1);

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

// 粘贴上传 AJAX 入口（参考 yeyinghai/Lsky-Upload-pro）
// Plugin.php 被 Typecho 加载时，如果 URL 带 ?action=lskypro_paste_upload 且是 POST，
// 直接调用 pasteUploadHandle()，在 Typecho 框架内处理上传（可直接读 Options 配置）
if (
    isset($_GET['action'])
    && $_GET['action'] === 'lskypro_paste_upload'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    Plugin::pasteUploadHandle();
}
