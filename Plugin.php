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
 * @version 2.0.0
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
        // 注册字段（UI由自定义HTML接管）
        $api = new Text('api', NULL, '', '', '');
        $form->addInput($api);
        $token = new Text('token', NULL, '', '', '');
        $form->addInput($token);
        $apiVersion = new Text('api_version', NULL, 'v2', '', '');
        $form->addInput($apiVersion);
        $permission = new Text('permission', NULL, '1', '', '');
        $form->addInput($permission);
        $strategyId = new Text('strategy_id', NULL, '', '', '');
        $form->addInput($strategyId);
        $albumId = new Text('album_id', NULL, '', '', '');
        $form->addInput($albumId);
        $maxSize = new Text('max_size', NULL, '10', '', '');
        $form->addInput($maxSize);
        $format = new Text('format', NULL, 'markdown', '', '');
        $form->addInput($format);

        // 读取已保存值（首次启用时opts可能为null）
        try { $opts = Options::alloc()->plugin('LskyPro'); } catch (\Exception $e) { $opts = null; }
        $vApi   = htmlspecialchars($opts->api ?? '', ENT_QUOTES);
        $vToken = htmlspecialchars($opts->token ?? '', ENT_QUOTES);
        $vVer   = htmlspecialchars($opts->api_version ?? 'v2', ENT_QUOTES);
        $vPerm  = htmlspecialchars($opts->permission ?? '1', ENT_QUOTES);
        $vStr   = htmlspecialchars($opts->strategy_id ?? '', ENT_QUOTES);
        $vAlb   = htmlspecialchars($opts->album_id ?? '', ENT_QUOTES);
        $vSize  = htmlspecialchars($opts->max_size ?? '10', ENT_QUOTES);
        $vFmt   = $opts->format ?? 'markdown';
        $vFmtMd  = ($vFmt === 'markdown') ? 'checked' : '';
        $vFmtUrl = ($vFmt === 'url') ? 'checked' : '';
        $vFmtHtm = ($vFmt === 'html') ? 'checked' : '';
        $vFmtBbc = ($vFmt === 'bbcode') ? 'checked' : '';

        echo <<<HTML
<style>
#lsky-panel *{box-sizing:border-box}
#lsky-panel{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;color:#1e293b;max-width:760px;margin:0}
/* 隐藏Typecho自动生成的表单字段由下方JS处理 */
.lsky-banner{display:flex;align-items:center;gap:16px;padding:20px 24px;background:linear-gradient(135deg,#1e3a8a 0%,#1d4ed8 50%,#3b82f6 100%);border-radius:14px;margin-bottom:20px;position:relative;overflow:hidden}
.lsky-banner::after{content:'';position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.06)}
.lsky-banner-icon{width:52px;height:52px;background:rgba(255,255,255,.18);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;position:relative;z-index:1}
.lsky-banner-info{position:relative;z-index:1}
.lsky-banner-title{font-size:18px;font-weight:700;color:#fff;margin:0 0 4px}
.lsky-banner-desc{font-size:12px;color:rgba(255,255,255,.75);margin:0;line-height:1.5}
.lsky-banner-ver{margin-left:auto;font-size:11px;font-weight:600;color:rgba(255,255,255,.6);background:rgba(255,255,255,.12);padding:4px 10px;border-radius:20px;position:relative;z-index:1;white-space:nowrap}
.lsky-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.lsky-card-head{display:flex;align-items:center;gap:10px;padding:14px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
.lsky-card-head-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.lsky-card-head-text h3{font-size:13px;font-weight:600;color:#0f172a;margin:0 0 2px}
.lsky-card-head-text p{font-size:11px;color:#94a3b8;margin:0}
.lsky-card-body{padding:20px}
.lsky-field{margin-bottom:16px}.lsky-field:last-child{margin-bottom:0}
.lsky-field label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px}
.lsky-field label .lsky-req{color:#ef4444;margin-left:2px}
.lsky-field label .lsky-opt{font-size:10px;font-weight:400;color:#94a3b8;margin-left:6px;background:#f1f5f9;padding:1px 6px;border-radius:4px}
.lsky-input{width:100%;padding:10px 14px;font-size:13px;color:#1e293b;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;outline:none;transition:border-color .15s,background .15s,box-shadow .15s}
.lsky-input:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.12)}
.lsky-input::placeholder{color:#cbd5e1}
.lsky-hint{font-size:11px;color:#94a3b8;margin-top:5px;line-height:1.5}
.lsky-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 24px;font-size:13px;font-weight:600;color:#fff;background:linear-gradient(135deg,#2563eb,#3b82f6);border:none;border-radius:8px;cursor:pointer;transition:all .15s}
.lsky-btn:hover{box-shadow:0 4px 14px rgba(37,99,235,.35);transform:translateY(-1px)}
.lsky-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none}
.lsky-btn-sm{padding:6px 14px;font-size:12px}
.lsky-msg{padding:10px 14px;margin:12px 0;border-radius:8px;font-size:13px;display:none;line-height:1.5}
.lsky-msg-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.lsky-msg-err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.lsky-fmt-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.lsky-fmt-item{position:relative}
.lsky-fmt-item input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.lsky-fmt-item label{display:flex;flex-direction:column;align-items:center;gap:8px;padding:16px 10px 14px;border:2px solid #e2e8f0;border-radius:10px;cursor:pointer;transition:all .15s;background:#fafafa;text-align:center;user-select:none}
.lsky-fmt-item label:hover{border-color:#93c5fd;background:#f0f9ff;transform:translateY(-1px)}
.lsky-fmt-item input:checked+label{border-color:#3b82f6;background:#eff6ff;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.lsky-fmt-badge{font-size:11px;font-weight:700;letter-spacing:.6px;padding:3px 10px;border-radius:6px;text-transform:uppercase}
.lsky-fmt-badge-md{background:#dbeafe;color:#1d4ed8}
.lsky-fmt-badge-url{background:#dcfce7;color:#15803d}
.lsky-fmt-badge-htm{background:#fef9c3;color:#854d0e}
.lsky-fmt-badge-bbc{background:#fce7f3;color:#be185d}
.lsky-fmt-sub{font-size:11px;color:#94a3b8;line-height:1.5}
.lsky-fmt-item input:checked+label .lsky-fmt-sub{color:#3b82f6}
.lsky-prev-box{border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#0f172a;margin-top:12px}
.lsky-prev-head{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:#1e293b;border-bottom:1px solid #334155}
.lsky-prev-dots{display:flex;gap:6px}
.lsky-prev-dots span{width:10px;height:10px;border-radius:50%}
.lsky-prev-dots .d1{background:#ef4444}.lsky-prev-dots .d2{background:#f59e0b}.lsky-prev-dots .d3{background:#22c55e}
.lsky-prev-label{font-size:11px;font-weight:600;color:#64748b;letter-spacing:.5px;text-transform:uppercase}
.lsky-prev-code{padding:16px 20px;font-family:Consolas,"Liberation Mono",Menlo,monospace;font-size:13px;line-height:2;word-break:break-all;min-height:56px;color:#e2e8f0}
.lsky-item{display:inline-block;margin:3px;padding:4px 10px;background:#f0f0f0;border:1px solid #ddd;border-radius:3px;font-size:12px;cursor:pointer;transition:all .15s}
.lsky-item:hover{background:#3b82f6;color:#fff;border-color:#3b82f6}
.lsky-item-active{background:#3b82f6;color:#fff;border-color:#2563eb}
</style>

<div id="lsky-panel">
<div class="lsky-banner">
<div class="lsky-banner-icon">🏔️</div>
<div class="lsky-banner-info">
<p class="lsky-banner-title">兰空图床上传</p>
<p class="lsky-banner-desc">将编辑器上传的图片自动存至兰空图床，支持多种链接格式</p>
</div>
<span class="lsky-banner-ver">v2.0.0</span>
</div>

<div class="lsky-card">
<div class="lsky-card-head">
<div class="lsky-card-head-icon" style="background:#eff6ff;color:#2563eb">🔗</div>
<div class="lsky-card-head-text"><h3>连接设置</h3><p>配置兰空图床API连接信息</p></div>
</div>
<div class="lsky-card-body">
<div class="lsky-field">
<label>API网址 <span class="lsky-req">*</span></label>
<div><input class="lsky-input" type="text" name="config[api]" value="{$vApi}" placeholder="https://pic.laozhang.org"></div>
<div class="lsky-hint">填写兰空图床域名，包含 http(s)://，不带 / 结尾</div>
</div>
<div class="lsky-field">
<label>API Token <span class="lsky-req">*</span></label>
<div><input class="lsky-input" type="text" name="config[token]" value="{$vToken}" placeholder="1|UYsgSjmtTkPjS8qPaLl98dJwdVtU492vQbDFI6pg"></div>
<div class="lsky-hint">在兰空图床后台获取的API令牌</div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<div class="lsky-field">
<label>API版本</label>
<div><input class="lsky-input" type="text" name="config[api_version]" value="{$vVer}" placeholder="v2"></div>
<div class="lsky-hint">填写 v1 或 v2，默认 v2</div>
</div>
<div class="lsky-field">
<label>图片权限</label>
<div><input class="lsky-input" type="text" name="config[permission]" value="{$vPerm}" placeholder="1"></div>
<div class="lsky-hint">1 公开，0 私有</div>
</div>
</div>
<div style="margin-top:16px">
<button type="button" id="lsky-test-btn" class="lsky-btn">🔗 测试连接</button>
<span id="lsky-test-load" style="display:none;margin-left:10px;color:#666;font-size:12px">正在测试...</span>
</div>
<div id="lsky-test-msg" class="lsky-msg"></div>
</div>
</div>

<div class="lsky-card">
<div class="lsky-card-head">
<div class="lsky-card-head-icon" style="background:#fef3c7;color:#92400e">📦</div>
<div class="lsky-card-head-text"><h3>存储设置</h3><p>选择存储策略和相册，测试连接后自动加载</p></div>
</div>
<div class="lsky-card-body">
<div class="lsky-field">
<label>存储策略ID <span class="lsky-opt">可选</span></label>
<div><input class="lsky-input" type="text" name="config[strategy_id]" value="{$vStr}" placeholder="留空使用默认策略"></div>
<div class="lsky-hint" id="lsky-str-hint">测试连接后自动显示可选策略，点击填入ID</div>
<div id="lsky-str-list" style="margin-top:8px"></div>
</div>
<div class="lsky-field">
<label>相册ID <span class="lsky-opt">可选</span></label>
<div><input class="lsky-input" type="text" name="config[album_id]" value="{$vAlb}" placeholder="留空不指定相册"></div>
<div class="lsky-hint" id="lsky-alb-hint">测试连接后自动显示可选相册，点击填入ID</div>
<div id="lsky-alb-list" style="margin-top:8px"></div>
</div>
<div class="lsky-field">
<label>最大上传大小(MB)</label>
<div><input class="lsky-input" type="text" name="config[max_size]" value="{$vSize}" placeholder="10"></div>
<div class="lsky-hint">单位MB，默认10</div>
</div>
</div>
</div>

<div class="lsky-card">
<div class="lsky-card-head">
<div class="lsky-card-head-icon" style="background:#f0fdf4;color:#166534">📝</div>
<div class="lsky-card-head-text"><h3>插入格式</h3><p>选择上传成功后插入编辑器的链接格式</p></div>
</div>
<div class="lsky-card-body">
<div class="lsky-fmt-grid">
<div class="lsky-fmt-item"><input type="radio" name="config[format]" value="markdown" id="fmt-md" {$vFmtMd}><label for="fmt-md"><span class="lsky-fmt-badge lsky-fmt-badge-md">MD</span><span class="lsky-fmt-sub">Markdown 格式</span></label></div>
<div class="lsky-fmt-item"><input type="radio" name="config[format]" value="url" id="fmt-url" {$vFmtUrl}><label for="fmt-url"><span class="lsky-fmt-badge lsky-fmt-badge-url">URL</span><span class="lsky-fmt-sub">纯链接地址</span></label></div>
<div class="lsky-fmt-item"><input type="radio" name="config[format]" value="html" id="fmt-htm" {$vFmtHtm}><label for="fmt-htm"><span class="lsky-fmt-badge lsky-fmt-badge-htm">HTML</span><span class="lsky-fmt-sub">HTML img 标签</span></label></div>
<div class="lsky-fmt-item"><input type="radio" name="config[format]" value="bbcode" id="fmt-bbc" {$vFmtBbc}><label for="fmt-bbc"><span class="lsky-fmt-badge lsky-fmt-badge-bbc">BB</span><span class="lsky-fmt-sub">BBCode 格式</span></label></div>
</div>
<div class="lsky-prev-box">
<div class="lsky-prev-head"><div class="lsky-prev-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div><span class="lsky-prev-label">PREVIEW</span></div>
<div class="lsky-prev-code" id="lsky-prev-code"></div>
</div>
</div>
</div>


<!-- footer removed -->
</div>

<script>
(function(){
// 隐藏Typecho自动生成的表单字段（保留提交按钮）
var dls=document.querySelectorAll('form > dl');
for(var i=0;i<dls.length;i++){if(dls[i].querySelector('input[name^="config"]'))dls[i].style.display='none'}
var AJ=(function(){var a=document.createElement('a');a.href=window.location.href;return a.protocol+'//'+a.host+'/usr/plugins/LskyPro/ajax.php'})();
function v(n){var e=document.querySelector('[name="config['+n+']"]');if(!e)e=document.querySelector('[name="'+n+'"]');return e?e.value.trim():''}
function el(n){var e=document.querySelector('[name="config['+n+']"]');return e||document.querySelector('[name="'+n+'"]')}
function msg(ok,t){var m=document.getElementById('lsky-test-msg');m.style.display='block';m.className='lsky-msg '+(ok?'lsky-msg-ok':'lsky-msg-err');m.innerHTML=t}
function rl(cid,bid,items,fld,hid){
var b=document.getElementById(bid),c=document.getElementById(cid),inp=el(fld);
b.style.display='block';c.innerHTML='';
items.forEach(function(i){
var s=document.createElement('span');s.className='lskypro-item lsky-item';
if(inp&&String(i.id)===String(inp.value))s.className+=' lsky-item-active';
s.textContent=i.name+' (ID:'+i.id+')';
s.onclick=function(){if(inp)inp.value=i.id;var a=c.querySelectorAll('.lsky-item');for(var j=0;j<a.length;j++)a[j].className='lsky-item lsky-item';s.className='lsky-item lsky-item lsky-item-active'};
c.appendChild(s)});
document.getElementById(hid).textContent='已加载 '+items.length+' 个，点击选择';
}
function xpost(fd,cb){
var x=new XMLHttpRequest();x.open('POST',AJ,true);x.timeout=15000;
x.onload=function(){try{cb(null,JSON.parse(x.responseText))}catch(e){cb(e)}};
x.onerror=function(){cb(new Error('网络错误'))};
x.ontimeout=function(){cb(new Error('超时'))};x.send(fd);
}

// 格式预览
var fmtMap={
markdown:'![图片](https://img.example.com/xxx.jpg)',
url:'https://img.example.com/xxx.jpg',
html:'&lt;img src="https://img.example.com/xxx.jpg" alt="图片" /&gt;',
bbcode:'[img]https://img.example.com/xxx.jpg[/img]'
};
function updatePrev(){
var r=document.querySelector('input[name="config[format]"]:checked');
var f=r?r.value:'markdown';
var code=document.getElementById('lsky-prev-code');
if(code)code.textContent=fmtMap[f]||fmtMap.markdown;
}
var radios=document.querySelectorAll('input[name="config[format]"]');
for(var i=0;i<radios.length;i++)radios[i].onchange=function(){
// 同步radio值到隐藏input
var hi=document.querySelector('input[name="config[format]"][type="text"]');if(hi)hi.value=this.value;
updatePrev();
};
updatePrev();

// 测试连接
document.getElementById('lsky-test-btn').onclick=function(){
var api=v('api'),tok=v('token'),ver=v('api_version')||'v2',btn=this;
if(!api||!tok){msg(false,'请先填写API网址和Token');return}
btn.disabled=true;document.getElementById('lsky-test-load').style.display='inline';
var fd=new FormData();fd.append('__lskypro_action','test_connection');fd.append('api',api);fd.append('token',tok);fd.append('api_version',ver);
xpost(fd,function(err,r){
document.getElementById('lsky-test-load').style.display='none';btn.disabled=false;
if(err){msg(false,'❌ 响应格式错误');return}
if(r.success){msg(true,'✅ 连接成功！欢迎 '+r.name);
var fd2=new FormData();fd2.append('__lskypro_action','get_strategies');fd2.append('api',api);fd2.append('token',tok);
xpost(fd2,function(e2,r2){if(!e2&&r2.success&&r2.strategies)rl('lsky-str-list','lsky-str-list',r2.strategies,'strategy_id','lsky-str-hint')});
var fd3=new FormData();fd3.append('__lskypro_action','get_albums');fd3.append('api',api);fd3.append('token',tok);
xpost(fd3,function(e3,r3){if(!e3&&r3.success&&r3.albums)rl('lsky-alb-list','lsky-alb-list',r3.albums,'album_id','lsky-alb-hint')});
}else{msg(false,'❌ '+(r.message||'连接失败'))}
});
};
})();
</script>
HTML;
    }

    /**
     * 注入编辑器脚本 - 处理格式化插入
     */
    public static function injectScript()
    {
        $opts = Options::alloc()->plugin('LskyPro');
        $format = $opts->format ?? 'markdown';
        $ajaxUrl = \Typecho\Common::url('usr/plugins/LskyPro/ajax.php', \Typecho\Common::url('/'));

        echo '<script>window.__lskyFormat="' . htmlspecialchars($format, ENT_QUOTES) . '";window.__lskyAjax="' . htmlspecialchars($ajaxUrl, ENT_QUOTES) . '";</script>' . "\n";
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

        // 确保是绝对URL（防止Typecho拼接站点URL）
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
