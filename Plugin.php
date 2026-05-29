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
 * @version 2.0.2
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
        // 注册隐藏字段（Typecho用来保存值）
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

        // 读取已保存值
        try { $opts = Options::alloc()->plugin('LskyPro'); } catch (\Exception $e) { $opts = null; }
        $vApi   = htmlspecialchars($opts->api ?? '', ENT_QUOTES);
        $vToken = htmlspecialchars($opts->token ?? '', ENT_QUOTES);
        $vVer   = htmlspecialchars($opts->api_version ?? 'v2', ENT_QUOTES);
        $vPerm  = htmlspecialchars($opts->permission ?? '1', ENT_QUOTES);
        $vStr   = htmlspecialchars($opts->strategy_id ?? '', ENT_QUOTES);
        $vAlb   = htmlspecialchars($opts->album_id ?? '', ENT_QUOTES);
        $vSize  = htmlspecialchars($opts->max_size ?? '10', ENT_QUOTES);
        $vFmt   = $opts->format ?? 'markdown';
        $ckMd   = $vFmt === 'markdown' ? 'checked' : '';
        $ckUrl  = $vFmt === 'url' ? 'checked' : '';
        $ckHtm  = $vFmt === 'html' ? 'checked' : '';
        $ckBbc  = $vFmt === 'bbcode' ? 'checked' : '';

        // 输出自定义UI
        echo '<style>
        .lsky-wrap{background:#fff;padding:20px;margin:10px 0;border:1px solid #ddd;border-radius:4px}
        .lsky-wrap h3{margin:0 0 15px;padding-bottom:10px;border-bottom:1px solid #eee;font-size:14px}
        .lsky-row{margin-bottom:12px}
        .lsky-row label{display:block;font-size:12px;font-weight:600;color:#333;margin-bottom:4px}
        .lsky-row input[type=text]{width:100%;max-width:500px;padding:6px 10px;border:1px solid #ddd;border-radius:3px;font-size:13px}
        .lsky-row .hint{font-size:11px;color:#999;margin-top:3px}
        .lsky-btn{padding:6px 16px;background:#0073aa;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:13px}
        .lsky-btn:hover{background:#005a87}
        .lsky-btn:disabled{background:#ccc}
        .lsky-msg{display:none;padding:8px 12px;margin:10px 0;border-left:4px solid;font-size:13px}
        .lsky-radios{display:flex;gap:15px;margin-top:5px}
        .lsky-radios label{display:flex;align-items:center;gap:4px;font-weight:normal;cursor:pointer;font-size:13px}
        .lsky-item{display:inline-block;margin:3px;padding:4px 10px;background:#f0f0f0;border:1px solid #ddd;border-radius:3px;font-size:12px;cursor:pointer}
        .lsky-item:hover{background:#0073aa;color:#fff;border-color:#0073aa}
        .lsky-item-on{background:#0073aa;color:#fff;border-color:#005a87}
        </style>';

        // 连接设置
        echo '<div class="lsky-wrap">';
        echo '<h3>🔗 连接设置</h3>';
        echo '<div class="lsky-row"><label>API网址 *</label><input type="text" id="lsky-in-api" value="' . $vApi . '" placeholder="https://pic.laozhang.org"></div>';
        echo '<div class="lsky-row"><label>API Token *</label><input type="text" id="lsky-in-token" value="' . $vToken . '" placeholder="1|UYsgSjmtTkPjS8qPaLl98dJwdVtU492vQbDFI6pg"></div>';
        echo '<div class="lsky-row"><label>API版本</label><input type="text" id="lsky-in-ver" value="' . $vVer . '" placeholder="v2" style="max-width:100px"><span class="hint"> v1 或 v2</span></div>';
        echo '<div class="lsky-row"><label>图片权限</label><input type="text" id="lsky-in-perm" value="' . $vPerm . '" placeholder="1" style="max-width:100px"><span class="hint"> 1=公开 0=私有</span></div>';
        echo '<div style="margin-top:15px"><button type="button" id="lsky-test-btn" class="lsky-btn">测试连接</button> <span id="lsky-test-load" style="display:none;color:#666;font-size:12px">正在测试...</span></div>';
        echo '<div id="lsky-test-msg" class="lsky-msg"></div>';
        echo '</div>';

        // 存储设置
        echo '<div class="lsky-wrap">';
        echo '<h3>📦 存储设置 <small style="color:#999;font-weight:normal">测试连接后自动加载</small></h3>';
        echo '<div class="lsky-row"><label>存储策略ID</label><input type="text" id="lsky-in-str" value="' . $vStr . '" placeholder="留空使用默认策略"><div id="lsky-str-list" style="margin-top:5px"></div><div class="hint" id="lsky-str-hint">测试连接后显示可选策略</div></div>';
        echo '<div class="lsky-row"><label>相册ID</label><input type="text" id="lsky-in-alb" value="' . $vAlb . '" placeholder="留空不指定相册"><div id="lsky-alb-list" style="margin-top:5px"></div><div class="hint" id="lsky-alb-hint">测试连接后显示可选相册</div></div>';
        echo '<div class="lsky-row"><label>最大上传大小(MB)</label><input type="text" id="lsky-in-size" value="' . $vSize . '" placeholder="10" style="max-width:100px"></div>';
        echo '</div>';

        // 插入格式
        echo '<div class="lsky-wrap">';
        echo '<h3>📝 插入格式</h3>';
        echo '<div class="lsky-row"><label>选择上传后插入编辑器的链接格式</label>';
        echo '<div class="lsky-radios">';
        echo '<label><input type="radio" name="lsky-fmt" value="markdown" ' . $ckMd . '> Markdown</label>';
        echo '<label><input type="radio" name="lsky-fmt" value="url" ' . $ckUrl . '> URL</label>';
        echo '<label><input type="radio" name="lsky-fmt" value="html" ' . $ckHtm . '> HTML</label>';
        echo '<label><input type="radio" name="lsky-fmt" value="bbcode" ' . $ckBbc . '> BBCode</label>';
        echo '</div></div>';
        echo '</div>';

        // JS - 用window.onload确保Typecho表单已渲染
        $ajaxUrl = rtrim(Options::alloc()->siteUrl, '/') . '/usr/plugins/LskyPro/ajax.php';
        echo '<script>
        window.addEventListener("DOMContentLoaded", function(){
        // 隐藏Typecho自动生成的表单字段
        var dls=document.querySelectorAll("form > dl");
        for(var i=0;i<dls.length;i++){
            var has=dls[i].querySelector("input[name^\\"config\\"]");
            if(has) dls[i].style.display="none";
        }

        var AJ="' . htmlspecialchars($ajaxUrl, ENT_QUOTES) . '";

        // 同步自定义输入框 → Typecho隐藏字段
        function sync(){
            var map={"lsky-in-api":"api","lsky-in-token":"token","lsky-in-ver":"api_version","lsky-in-perm":"permission","lsky-in-str":"strategy_id","lsky-in-alb":"album_id","lsky-in-size":"max_size"};
            for(var k in map){
                var src=document.getElementById(k);
                var dst=document.querySelector("[name=\\"config["+map[k]+"]\\"]");
                if(src&&dst) dst.value=src.value;
            }
            // 同步radio → hidden format字段
            var checked=document.querySelector("input[name=\\"lsky-fmt\\"]:checked");
            var fmtEl=document.querySelector("[name=\\"config[format]\\"]");
            if(checked&&fmtEl) fmtEl.value=checked.value;
        }
        // 实时同步
        var inputs=document.querySelectorAll("#lsky-in-api,#lsky-in-token,#lsky-in-ver,#lsky-in-perm,#lsky-in-str,#lsky-in-alb,#lsky-in-size");
        for(var i=0;i<inputs.length;i++) inputs[i].addEventListener("input",sync);
        var radios=document.querySelectorAll("input[name=\\"lsky-fmt\\"]");
        for(var i=0;i<radios.length;i++) radios[i].addEventListener("change",sync);
        // 保存前同步
        var form=document.querySelector("form");
        if(form) form.addEventListener("submit",sync);

        function msg(ok,t){var m=document.getElementById("lsky-test-msg");m.style.display="block";m.style.borderColor=ok?"#46b450":"#dc3232";m.style.background=ok?"#f0f8f0":"#fdf0f0";m.innerHTML=t}
        function showList(listId,hintId,items,inputId){
            var c=document.getElementById(listId),inp=document.getElementById(inputId);
            c.innerHTML="";
            items.forEach(function(i){
                var s=document.createElement("span");s.className="lsky-item";
                if(inp&&String(i.id)===String(inp.value)) s.className+=" lsky-item-on";
                s.textContent=i.name+" (ID:"+i.id+")";
                s.onclick=function(){
                    if(inp)inp.value=i.id;
                    var a=c.querySelectorAll(".lsky-item");for(var j=0;j<a.length;j++)a[j].className="lsky-item";
                    s.className="lsky-item lsky-item-on";
                    sync();
                };
                c.appendChild(s);
            });
            document.getElementById(hintId).textContent="已加载 "+items.length+" 个，点击选择";
        }
        function xpost(fd,cb){
            var x=new XMLHttpRequest();x.open("POST",AJ,true);x.timeout=15000;
            x.onload=function(){try{cb(null,JSON.parse(x.responseText))}catch(e){cb(e)}};
            x.onerror=function(){cb(new Error("网络错误"))};x.ontimeout=function(){cb(new Error("超时"))};x.send(fd);
        }

        document.getElementById("lsky-test-btn").onclick=function(){
            var api=document.getElementById("lsky-in-api").value.trim();
            var tok=document.getElementById("lsky-in-token").value.trim();
            var ver=document.getElementById("lsky-in-ver").value.trim()||"v2";
            var btn=this;
            if(!api||!tok){msg(false,"请先填写API网址和Token");return}
            sync();
            btn.disabled=true;document.getElementById("lsky-test-load").style.display="inline";
            var fd=new FormData();fd.append("__lskypro_action","test_connection");fd.append("api",api);fd.append("token",tok);fd.append("api_version",ver);
            xpost(fd,function(err,r){
                document.getElementById("lsky-test-load").style.display="none";btn.disabled=false;
                if(err){msg(false,"❌ 响应格式错误");return}
                if(r.success){
                    msg(true,"✅ 连接成功！欢迎 "+r.name);
                    var fd2=new FormData();fd2.append("__lskypro_action","get_strategies");fd2.append("api",api);fd2.append("token",tok);
                    xpost(fd2,function(e2,r2){if(!e2&&r2.success&&r2.strategies)showList("lsky-str-list","lsky-str-hint",r2.strategies,"lsky-in-str")});
                    var fd3=new FormData();fd3.append("__lskypro_action","get_albums");fd3.append("api",api);fd3.append("token",tok);
                    xpost(fd3,function(e3,r3){if(!e3&&r3.success&&r3.albums)showList("lsky-alb-list","lsky-alb-hint",r3.albums,"lsky-in-alb")});
                }else{msg(false,"❌ "+(r.message||"连接失败"))}
            });
        };
        });
        </script>';
    }

    /**
     * 注入编辑器脚本
     */
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
