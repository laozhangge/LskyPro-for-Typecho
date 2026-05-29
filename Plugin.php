<?php
/**
 * 兰空图床上传插件 for Typecho
 * 
 * @package LskyPro for Typecho
 * @author 老张博客
 * @version 1.3.6
 * @link https://github.com/laozhangge/LskyPro-for-Typecho
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class LskyPro_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 插件版本
     */
    const VERSION = '1.3.6';
    
    /**
     * 激活插件
     */
    public static function activate()
    {
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('LskyPro_Plugin', 'render');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('LskyPro_Plugin', 'render');
        
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}lskypro_config` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `config_key` varchar(50) NOT NULL,
            `config_value` text,
            PRIMARY KEY (`id`),
            UNIQUE KEY `config_key` (`config_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        $defaultConfig = array(
            'domain' => '',
            'tokens' => '',
            'api_version' => 'v2',
            'permission' => '1',
            'storage_id' => '',
            'album_id' => '',
            'max_size' => '10',
            'allowed_types' => 'jpg,jpeg,png,gif,webp,bmp'
        );
        
        foreach ($defaultConfig as $key => $value) {
            $db->query("INSERT IGNORE INTO `{$prefix}lskypro_config` (`config_key`, `config_value`) VALUES (?, ?)", $key, $value);
        }
        
        return _t('兰空图床上传插件已激活');
    }
    
    /**
     * 停用插件
     */
    public static function deactivate()
    {
        return _t('兰空图床上传插件已停用');
    }
    
    /**
     * 获取插件配置
     */
    public static function getConfig($key = null)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        if ($key !== null) {
            $row = $db->fetchRow($db->select('config_value')->from("{$prefix}lskypro_config")->where('config_key = ?', $key));
            return $row ? $row['config_value'] : '';
        }
        
        $rows = $db->fetchAll($db->select('config_key', 'config_value')->from("{$prefix}lskypro_config"));
        $config = array();
        foreach ($rows as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        return $config;
    }
    
    /**
     * 保存插件配置
     */
    public static function saveConfig($key, $value)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        $db->query("INSERT INTO `{$prefix}lskypro_config` (`config_key`, `config_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `config_value` = ?", $key, $value, $value);
    }
    
    /**
     * 生成插件资源URL
     */
    private static function pluginUrl($path)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        echo $options->pluginUrl . '/LskyPro-Typecho/' . $path;
    }
    
    /**
     * 插件配置页面
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $config = self::getConfig();
        ?>
        <style>
            .lskypro-settings { max-width: 800px; }
            .lskypro-settings h3 { margin: 20px 0 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            .lskypro-settings table { width: 100%; }
            .lskypro-settings td { padding: 8px 0; vertical-align: top; }
            .lskypro-settings td:first-child { width: 180px; font-weight: bold; }
            .lskypro-settings input[type="text"], 
            .lskypro-settings input[type="url"], 
            .lskypro-settings input[type="number"],
            .lskypro-settings select { width: 300px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; }
            .lskypro-settings .description { color: #666; font-size: 12px; margin-top: 5px; }
            .lskypro-settings .button { padding: 8px 16px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
            .lskypro-settings .button:hover { background: #005a87; }
            .lskypro-settings .button:disabled { background: #ccc; cursor: not-allowed; }
            .lskypro-settings .notice { padding: 10px 15px; margin: 10px 0; border-left: 4px solid; }
            .lskypro-settings .notice-success { border-color: #46b450; background: #f0f8f0; }
            .lskypro-settings .notice-error { border-color: #dc3232; background: #fdf0f0; }
            .lskypro-loading { display: none; margin-left: 10px; color: #666; }
            .lskypro-result-input { width: 100%; padding: 5px; margin: 5px 0; cursor: pointer; }
        </style>
        
        <div class="lskypro-settings">
            <h3>基本设置</h3>
            <table>
                <tr>
                    <td>API网址</td>
                    <td>
                        <input type="url" name="domain" id="lskypro-domain" value="<?php echo htmlspecialchars($config['domain']); ?>" placeholder="https://your-lsky.com" />
                        <p class="description">填写兰空图床的域名，必须带有http://或https://</p>
                    </td>
                </tr>
                <tr>
                    <td>API Tokens</td>
                    <td>
                        <input type="text" name="tokens" id="lskypro-tokens" value="<?php echo htmlspecialchars($config['tokens']); ?>" placeholder="1|xxxxx..." />
                        <p class="description">在兰空图床后台获取的API令牌</p>
                    </td>
                </tr>
                <tr>
                    <td>API版本</td>
                    <td>
                        <select name="api_version" id="lskypro-api-version">
                            <option value="v1" <?php if ($config['api_version'] === 'v1') echo 'selected'; ?>>V1 (旧版本)</option>
                            <option value="v2" <?php if ($config['api_version'] === 'v2') echo 'selected'; ?>>V2 (新版本)</option>
                        </select>
                        <p class="description">根据您的兰空图床版本选择</p>
                    </td>
                </tr>
                <tr>
                    <td>图片权限</td>
                    <td>
                        <select name="permission">
                            <option value="1" <?php if ($config['permission'] === '1') echo 'selected'; ?>>公开</option>
                            <option value="0" <?php if ($config['permission'] === '0') echo 'selected'; ?>>私有</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>存储策略</td>
                    <td>
                        <select name="storage_id" id="lskypro-storage-id">
                            <option value="">请先测试连接</option>
                        </select>
                        <button type="button" id="lskypro-load-strategies" class="button" disabled>刷新列表</button>
                        <span id="lskypro-strategies-loading" class="lskypro-loading">加载中...</span>
                        <p class="description">测试连接后自动加载</p>
                    </td>
                </tr>
                <tr>
                    <td>上传相册</td>
                    <td>
                        <select name="album_id" id="lskypro-album-id">
                            <option value="">不指定相册</option>
                        </select>
                        <button type="button" id="lskypro-load-albums" class="button" disabled>刷新列表</button>
                        <span id="lskypro-albums-loading" class="lskypro-loading">加载中...</span>
                        <p class="description">测试连接后自动加载</p>
                    </td>
                </tr>
                <tr>
                    <td>测试连接</td>
                    <td>
                        <button type="button" id="lskypro-test-connection" class="button">测试连接</button>
                        <span id="lskypro-test-loading" class="lskypro-loading">正在测试...</span>
                        <div id="lskypro-connection-result" style="display:none;"></div>
                    </td>
                </tr>
            </table>
            
            <h3>高级设置</h3>
            <table>
                <tr>
                    <td>最大上传大小(MB)</td>
                    <td>
                        <input type="number" name="max_size" value="<?php echo intval($config['max_size']); ?>" min="1" max="100" />
                    </td>
                </tr>
                <tr>
                    <td>允许的图片类型</td>
                    <td>
                        <input type="text" name="allowed_types" value="<?php echo htmlspecialchars($config['allowed_types']); ?>" />
                        <p class="description">用逗号分隔，如：jpg,jpeg,png,gif,webp</p>
                    </td>
                </tr>
            </table>
            
            <h3>关于</h3>
            <p>兰空图床上传 v<?php echo self::VERSION; ?></p>
            <p>作者：<a href="https://laozhang.org" target="_blank">老张博客</a></p>
            <p>插件主页：<a href="https://github.com/laozhangge/LskyPro-for-Typecho" target="_blank">GitHub</a></p>
            <p>兰空图床官网：<a href="https://www.lsky.pro/" target="_blank">https://www.lsky.pro/</a></p>
        </div>
        
        <script>
        (function() {
            var ajaxUrl = '<?php echo Typecho_Common::url("extending.php?panel=LskyPro_Plugin%2Fajax.php", $options->adminUrl); ?>';
            var currentStorageId = '<?php echo $config['storage_id']; ?>';
            var currentAlbumId = '<?php echo $config['album_id']; ?>';
            
            document.getElementById('lskypro-test-connection').onclick = function() {
                var domain = document.getElementById('lskypro-domain').value;
                var tokens = document.getElementById('lskypro-tokens').value;
                var apiVersion = document.getElementById('lskypro-api-version').value;
                var resultBox = document.getElementById('lskypro-connection-result');
                
                if (!domain || !tokens) {
                    resultBox.className = 'notice notice-error';
                    resultBox.innerHTML = '<p><strong>错误：</strong>请先填写API网址和Tokens</p>';
                    resultBox.style.display = 'block';
                    return;
                }
                
                document.getElementById('lskypro-test-loading').style.display = 'inline';
                this.disabled = true;
                resultBox.style.display = 'none';
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            resultBox.className = 'notice notice-success';
                            resultBox.innerHTML = '<p><strong>成功：</strong>' + data.message + '</p>';
                            resultBox.style.display = 'block';
                            loadStrategies();
                            loadAlbums();
                        } else {
                            resultBox.className = 'notice notice-error';
                            resultBox.innerHTML = '<p><strong>错误：</strong>' + data.message + '</p>';
                            resultBox.style.display = 'block';
                        }
                    }
                    document.getElementById('lskypro-test-loading').style.display = 'none';
                    document.getElementById('lskypro-test-connection').disabled = false;
                };
                xhr.send('action=test_connection&domain=' + encodeURIComponent(domain) + '&tokens=' + encodeURIComponent(tokens) + '&api_version=' + encodeURIComponent(apiVersion));
            };
            
            function loadStrategies() {
                var domain = document.getElementById('lskypro-domain').value;
                var tokens = document.getElementById('lskypro-tokens').value;
                if (!domain || !tokens) return;
                
                document.getElementById('lskypro-strategies-loading').style.display = 'inline';
                document.getElementById('lskypro-load-strategies').disabled = true;
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var data = JSON.parse(xhr.responseText);
                        var select = document.getElementById('lskypro-storage-id');
                        select.innerHTML = '<option value="">请选择存储策略</option>';
                        if (data.success && data.strategies) {
                            data.strategies.forEach(function(item) {
                                var option = document.createElement('option');
                                option.value = item.id;
                                option.textContent = item.name;
                                if (item.id == currentStorageId) option.selected = true;
                                select.appendChild(option);
                            });
                            document.getElementById('lskypro-load-strategies').disabled = false;
                        }
                    }
                    document.getElementById('lskypro-strategies-loading').style.display = 'none';
                };
                xhr.send('action=get_strategies&domain=' + encodeURIComponent(domain) + '&tokens=' + encodeURIComponent(tokens));
            }
            
            function loadAlbums() {
                var domain = document.getElementById('lskypro-domain').value;
                var tokens = document.getElementById('lskypro-tokens').value;
                if (!domain || !tokens) return;
                
                document.getElementById('lskypro-albums-loading').style.display = 'inline';
                document.getElementById('lskypro-load-albums').disabled = true;
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var data = JSON.parse(xhr.responseText);
                        var select = document.getElementById('lskypro-album-id');
                        select.innerHTML = '<option value="">不指定相册</option>';
                        if (data.success && data.albums) {
                            data.albums.forEach(function(item) {
                                var option = document.createElement('option');
                                option.value = item.id;
                                option.textContent = item.name;
                                if (item.id == currentAlbumId) option.selected = true;
                                select.appendChild(option);
                            });
                            document.getElementById('lskypro-load-albums').disabled = false;
                        }
                    }
                    document.getElementById('lskypro-albums-loading').style.display = 'none';
                };
                xhr.send('action=get_albums&domain=' + encodeURIComponent(domain) + '&tokens=' + encodeURIComponent(tokens));
            }
            
            document.getElementById('lskypro-load-strategies').onclick = loadStrategies;
            document.getElementById('lskypro-load-albums').onclick = loadAlbums;
            
            var domain = document.getElementById('lskypro-domain').value;
            var tokens = document.getElementById('lskypro-tokens').value;
            if (domain && tokens) {
                loadStrategies();
                loadAlbums();
            }
        })();
        </script>
        <?php
    }
    
    /**
     * 个人配置页面
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        // 个人配置页面（暂不使用）
    }
    
    /**
     * 渲染编辑器上传界面
     */
    public static function render()
    {
        $config = self::getConfig();
        
        if (empty($config['domain']) || empty($config['tokens'])) {
            return;
        }
        
        ?>
        <link rel="stylesheet" href="<?php self::pluginUrl('css/style.css'); ?>">
        <script src="<?php self::pluginUrl('js/axios.min.js'); ?>"></script>
        <script src="<?php self::pluginUrl('js/paste-upload.js'); ?>"></script>
        <script>
            var lskyproData = {
                domain: '<?php echo $config['domain']; ?>',
                tokens: '<?php echo $config['tokens']; ?>',
                api_version: '<?php echo $config['api_version']; ?>',
                permission: '<?php echo $config['permission']; ?>',
                storage_id: '<?php echo $config['storage_id']; ?>',
                album_id: '<?php echo $config['album_id']; ?>',
                max_size: <?php echo intval($config['max_size']); ?>,
                allowed_types: '<?php echo $config['allowed_types']; ?>'.split(',')
            };
        </script>
        
        <div class="lskypro-upload-box">
            <h4>兰空图床上传</h4>
            <div class="lskypro-upload-area" id="lsky-upload-box">点击此区域上传图片</div>
            <input type="file" id="lsky-upload-box-input" multiple accept="image/*" style="display:none;" />
            <div id="lskypro-result"></div>
            <p class="lskypro-tip">支持的文件类型: <?php echo $config['allowed_types']; ?><br>最大文件大小: <?php echo $config['max_size']; ?>MB<br>支持粘贴上传 (Ctrl+V)</p>
        </div>
        
        <script>
        (function() {
            var uploadBox = document.getElementById('lsky-upload-box');
            var uploadInput = document.getElementById('lsky-upload-box-input');
            var resultDiv = document.getElementById('lskypro-result');
            
            uploadBox.onclick = function() {
                uploadInput.click();
            };
            
            uploadInput.onchange = function() {
                handleFileUpload(this.files);
            };
            
            function handleFileUpload(files) {
                if (!files || files.length === 0) return;
                
                for (var i = 0; i < files.length; i++) {
                    var file = files[i];
                    
                    if (lskyproData.allowed_types.length > 0) {
                        var ext = file.name.split('.').pop().toLowerCase();
                        if (lskyproData.allowed_types.indexOf(ext) === -1) {
                            alert('不支持的文件类型: ' + ext);
                            continue;
                        }
                    }
                    
                    if (file.size > lskyproData.max_size * 1024 * 1024) {
                        alert('文件过大，最大支持 ' + lskyproData.max_size + 'MB');
                        continue;
                    }
                    
                    uploadFile(file);
                }
            }
            
            function uploadFile(file) {
                uploadBox.textContent = '正在上传中...';
                uploadInput.disabled = true;
                
                var formData = new FormData();
                formData.append('file', file);
                
                var apiVersion = lskyproData.api_version;
                if (apiVersion === 'v2') {
                    if (lskyproData.storage_id) formData.append('storage_id', lskyproData.storage_id);
                    if (lskyproData.album_id) formData.append('album_id', lskyproData.album_id);
                    formData.append('is_public', lskyproData.permission === '1' ? 1 : 0);
                } else {
                    formData.append('permission', lskyproData.permission);
                    if (lskyproData.album_id) formData.append('album_id', lskyproData.album_id);
                }
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', lskyproData.domain + '/api/' + apiVersion + '/upload', true);
                xhr.setRequestHeader('Authorization', 'Bearer ' + lskyproData.tokens);
                xhr.setRequestHeader('Accept', 'application/json');
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var data = JSON.parse(xhr.responseText);
                        var url = '';
                        
                        if (apiVersion === 'v2') {
                            if (data.status === 'success') url = data.data.public_url || data.data.url || '';
                        } else {
                            if (data.status) url = data.data.links.url;
                        }
                        
                        if (url) {
                            showResult(url, file.name);
                        } else {
                            alert('上传失败: ' + (data.message || '未知错误'));
                        }
                    } else {
                        alert('上传失败: HTTP ' + xhr.status);
                    }
                    
                    uploadBox.textContent = '点击此区域上传图片';
                    uploadInput.disabled = false;
                    uploadInput.value = '';
                };
                
                xhr.onerror = function() {
                    alert('网络错误');
                    uploadBox.textContent = '点击此区域上传图片';
                    uploadInput.disabled = false;
                    uploadInput.value = '';
                };
                
                xhr.send(formData);
            }
            
            function showResult(url, filename) {
                var html = '<div style="margin:10px 0;padding:10px;background:#f0f8f0;border:1px solid #46b450;border-radius:4px;">';
                html += '<p><strong>上传成功：</strong>' + filename + '</p>';
                html += '<p>URL: <input type="text" class="lskypro-result-input" value="' + url + '" onclick="this.select();" readonly /></p>';
                html += '<p><a href="' + url + '" target="_blank">预览</a></p>';
                html += '</div>';
                resultDiv.innerHTML = html + resultDiv.innerHTML;
            }
            
            // 粘贴上传支持
            document.addEventListener('paste', function(e) {
                var items = e.clipboardData && e.clipboardData.items;
                if (!items) return;
                
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        e.preventDefault();
                        var file = items[i].getAsFile();
                        if (file) {
                            uploadFile(file);
                        }
                        break;
                    }
                }
            });
        })();
        </script>
        <?php
    }
}
