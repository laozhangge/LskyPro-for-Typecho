     1|<?php
     2|/**
     3| * 兰空图床上传插件 for Typecho
     4| * 
     5| * @package LskyPro for Typecho
     6| * @author 老张博客
     7| * @version 1.3.6
     8| * @link https://github.com/laozhangge/LskyPro-for-Typecho
     9| */
    10|
    11|if (!defined('__TYPECHO_ROOT_DIR__')) {
    12|    exit;
    13|}
    14|
    15|class LskyPro_Plugin implements Typecho_Plugin_Interface
    16|{
    17|    /**
    18|     * 插件版本
    19|     */
    20|    const VERSION = '1.3.6';
    21|    
    22|    /**
    23|     * 激活插件
    24|     */
    25|    public static function activate()
    26|    {
    27|        // 注册钩子
    28|        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('LskyPro_Plugin', 'render');
    29|        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('LskyPro_Plugin', 'render');
    30|        
    31|        // 创建数据库表
    32|        $db = Typecho_Db::get();
    33|        $prefix = $db->getPrefix();
    34|        
    35|        // 插件配置表
    36|        $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}lskypro_config` (
    37|            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    38|            `config_key` varchar(50) NOT NULL,
    39|            `config_value` text,
    40|            PRIMARY KEY (`id`),
    41|            UNIQUE KEY `config_key` (`config_key`)
    42|        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    43|        
    44|        // 初始化默认配置
    45|        $defaultConfig = array(
    46|            'domain' => '',
    47|            'tokens' => '',
    48|            'api_version' => 'v2',
    49|            'permission' => '1',
    50|            'storage_id' => '',
    51|            'album_id' => '',
    52|            'max_size' => '10',
    53|            'allowed_types' => 'jpg,jpeg,png,gif,webp,bmp'
    54|        );
    55|        
    56|        foreach ($defaultConfig as $key => $value) {
    57|            $db->query("INSERT IGNORE INTO `{$prefix}lskypro_config` (`config_key`, `config_value`) VALUES (?, ?)", $key, $value);
    58|        }
    59|        
    60|        return _t('兰空图床上传插件已激活');
    61|    }
    62|    
    63|    /**
    64|     * 停用插件
    65|     */
    66|    public static function deactivate()
    67|    {
    68|        return _t('兰空图床上传插件已停用');
    69|    }
    70|    
    71|    /**
    72|     * 获取插件配置
    73|     */
    74|    public static function getConfig($key = null)
    75|    {
    76|        $db = Typecho_Db::get();
    77|        $prefix = $db->getPrefix();
    78|        
    79|        if ($key !== null) {
    80|            $row = $db->fetchRow($db->select('config_value')->from("{$prefix}lskypro_config")->where('config_key = ?', $key));
    81|            return $row ? $row['config_value'] : '';
    82|        }
    83|        
    84|        $rows = $db->fetchAll($db->select('config_key', 'config_value')->from("{$prefix}lskypro_config"));
    85|        $config = array();
    86|        foreach ($rows as $row) {
    87|            $config[$row['config_key']] = $row['config_value'];
    88|        }
    89|        return $config;
    90|    }
    91|    
    92|    /**
    93|     * 保存插件配置
    94|     */
    95|    public static function saveConfig($key, $value)
    96|    {
    97|        $db = Typecho_Db::get();
    98|        $prefix = $db->getPrefix();
    99|        
   100|        $db->query("INSERT INTO `{$prefix}lskypro_config` (`config_key`, `config_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `config_value` = ?", $key, $value, $value);
   101|    }
   102|    
   103|    /**
   104|     * 插件配置页面
   105|     */
   106|    public static function config(Typecho\Widget\Helper\Form $form)
   107|    {
   108|        $config = self::getConfig();
   109|        
   110|        // 输出配置页面HTML
   111|        ?>
   112|        <style>
   113|            .lskypro-settings { max-width: 800px; }
   114|            .lskypro-settings h3 { margin: 20px 0 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
   115|            .lskypro-settings table { width: 100%; }
   116|            .lskypro-settings td { padding: 8px 0; vertical-align: top; }
   117|            .lskypro-settings td:first-child { width: 180px; font-weight: bold; }
   118|            .lskypro-settings input[type="text"], 
   119|            .lskypro-settings input[type="url"], 
   120|            .lskypro-settings input[type="number"],
   121|            .lskypro-settings select { width: 300px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; }
   122|            .lskypro-settings .description { color: #666; font-size: 12px; margin-top: 5px; }
   123|            .lskypro-settings .button { padding: 8px 16px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
   124|            .lskypro-settings .button:hover { background: #005a87; }
   125|            .lskypro-settings .button:disabled { background: #ccc; cursor: not-allowed; }
   126|            .lskypro-settings .notice { padding: 10px 15px; margin: 10px 0; border-left: 4px solid; }
   127|            .lskypro-settings .notice-success { border-color: #46b450; background: #f0f8f0; }
   128|            .lskypro-settings .notice-error { border-color: #dc3232; background: #fdf0f0; }
   129|            .lskypro-loading { display: none; margin-left: 10px; color: #666; }
   130|            .lskypro-result-input { width: 100%; padding: 5px; margin: 5px 0; cursor: pointer; }
   131|        </style>
   132|        
   133|        <div class="lskypro-settings">
   134|            <h3>基本设置</h3>
   135|            <table>
   136|                <tr>
   137|                    <td>API网址</td>
   138|                    <td>
   139|                        <input type="url" name="domain" id="lskypro-domain" value="<?php echo htmlspecialchars($config['domain']); ?>" placeholder="https://your-lsky.com" />
   140|                        <p class="description">填写兰空图床的域名，必须带有http://或https://</p>
   141|                    </td>
   142|                </tr>
   143|                <tr>
   144|                    <td>API Tokens</td>
   145|                    <td>
   146|                        <input type="text" name="tokens" id="lskypro-tokens" value="<?php echo htmlspecialchars($config['tokens']); ?>" placeholder="1|xxxxx..." />
   147|                        <p class="description">在兰空图床后台获取的API令牌</p>
   148|                    </td>
   149|                </tr>
   150|                <tr>
   151|                    <td>API版本</td>
   152|                    <td>
   153|                        <select name="api_version" id="lskypro-api-version">
   154|                            <option value="v1" <?php if ($config['api_version'] === 'v1') echo 'selected'; ?>>V1 (旧版本)</option>
   155|                            <option value="v2" <?php if ($config['api_version'] === 'v2') echo 'selected'; ?>>V2 (新版本)</option>
   156|                        </select>
   157|                        <p class="description">根据您的兰空图床版本选择</p>
   158|                    </td>
   159|                </tr>
   160|                <tr>
   161|                    <td>图片权限</td>
   162|                    <td>
   163|                        <select name="permission">
   164|                            <option value="1" <?php if ($config['permission'] === '1') echo 'selected'; ?>>公开</option>
   165|                            <option value="0" <?php if ($config['permission'] === '0') echo 'selected'; ?>>私有</option>
   166|                        </select>
   167|                    </td>
   168|                </tr>
   169|                <tr>
   170|                    <td>存储策略</td>
   171|                    <td>
   172|                        <select name="storage_id" id="lskypro-storage-id">
   173|                            <option value="">请先测试连接</option>
   174|                        </select>
   175|                        <button type="button" id="lskypro-load-strategies" class="button" disabled>刷新列表</button>
   176|                        <span id="lskypro-strategies-loading" class="lskypro-loading">加载中...</span>
   177|                        <p class="description">测试连接后自动加载</p>
   178|                    </td>
   179|                </tr>
   180|                <tr>
   181|                    <td>上传相册</td>
   182|                    <td>
   183|                        <select name="album_id" id="lskypro-album-id">
   184|                            <option value="">不指定相册</option>
   185|                        </select>
   186|                        <button type="button" id="lskypro-load-albums" class="button" disabled>刷新列表</button>
   187|                        <span id="lskypro-albums-loading" class="lskypro-loading">加载中...</span>
   188|                        <p class="description">测试连接后自动加载</p>
   189|                    </td>
   190|                </tr>
   191|                <tr>
   192|                    <td>测试连接</td>
   193|                    <td>
   194|                        <button type="button" id="lskypro-test-connection" class="button">测试连接</button>
   195|                        <span id="lskypro-test-loading" class="lskypro-loading">正在测试...</span>
   196|                        <div id="lskypro-connection-result" style="display:none;"></div>
   197|                    </td>
   198|                </tr>
   199|            </table>
   200|            
   201|            <h3>高级设置</h3>
   202|            <table>
   203|                <tr>
   204|                    <td>最大上传大小(MB)</td>
   205|                    <td>
   206|                        <input type="number" name="max_size" value="<?php echo intval($config['max_size']); ?>" min="1" max="100" />
   207|                    </td>
   208|                </tr>
   209|                <tr>
   210|                    <td>允许的图片类型</td>
   211|                    <td>
   212|                        <input type="text" name="allowed_types" value="<?php echo htmlspecialchars($config['allowed_types']); ?>" />
   213|                        <p class="description">用逗号分隔，如：jpg,jpeg,png,gif,webp</p>
   214|                    </td>
   215|                </tr>
   216|            </table>
   217|            
   218|            <h3>关于</h3>
   219|            <p>兰空图床上传 v<?php echo self::VERSION; ?></p>
   220|            <p>作者：<a href="https://laozhang.org" target="_blank">老张博客</a></p>
   221|            <p>插件主页：<a href="https://github.com/laozhangge/LskyPro-for-Typecho" target="_blank">GitHub</a></p>
   222|            <p>兰空图床官网：<a href="https://www.lsky.pro/" target="_blank">https://www.lsky.pro/</a></p>
   223|        </div>
   224|        
   225|        <script>
   226|        (function() {
   227|            var ajaxUrl = '<?php echo Typecho_Common::url("extending.php?panel=LskyPro_Plugin%2Fajax.php", $options->adminUrl); ?>';
   228|            var currentStorageId = '<?php echo $config['storage_id']; ?>';
   229|            var currentAlbumId = '<?php echo $config['album_id']; ?>';
   230|            
   231|            // 测试连接
   232|            document.getElementById('lskypro-test-connection').onclick = function() {
   233|                var domain = document.getElementById('lskypro-domain').value;
   234|                var tokens = document.getElementById('lskypro-tokens').value;
   235|                var apiVersion = document.getElementById('lskypro-api-version').value;
   236|                var resultBox = document.getElementById('lskypro-connection-result');
   237|                
   238|                if (!domain || !tokens) {
   239|                    resultBox.className = 'notice notice-error';
   240|                    resultBox.innerHTML = '<p><strong>错误：</strong>请先填写API网址和Tokens</p>';
   241|                    resultBox.style.display = 'block';
   242|                    return;
   243|                }
   244|                
   245|                document.getElementById('lskypro-test-loading').style.display = 'inline';
   246|                this.disabled = true;
   247|                resultBox.style.display = 'none';
   248|                
   249|                var xhr = new XMLHttpRequest();
   250|                xhr.open('POST', ajaxUrl, true);
   251|                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
   252|                xhr.onload = function() {
   253|                    if (xhr.status === 200) {
   254|                        var data = JSON.parse(xhr.responseText);
   255|                        if (data.success) {
   256|                            resultBox.className = 'notice notice-success';
   257|                            resultBox.innerHTML = '<p><strong>成功：</strong>' + data.message + '</p>';
   258|                            resultBox.style.display = 'block';
   259|                            loadStrategies();
   260|                            loadAlbums();
   261|                        } else {
   262|                            resultBox.className = 'notice notice-error';
   263|                            resultBox.innerHTML = '<p><strong>错误：</strong>' + data.message + '</p>';
   264|                            resultBox.style.display = 'block';
   265|                        }
   266|                    }
   267|                    document.getElementById('lskypro-test-loading').style.display = 'none';
   268|                    document.getElementById('lskypro-test-connection').disabled = false;
   269|                };
   270|                xhr.send('action=test_connection&domain=' + encodeURIComponent(domain) + '&tokens=' + encodeURIComponent(tokens) + '&api_version=' + encodeURIComponent(apiVersion));
   271|            };
   272|            
   273|            // 加载策略列表
   274|            function loadStrategies() {
   275|                var domain = document.getElementById('lskypro-domain').value;
   276|                var tokens = document.getElementById('lskypro-tokens').value;
   277|                
   278|                if (!domain || !tokens) return;
   279|                
   280|                document.getElementById('lskypro-strategies-loading').style.display = 'inline';
   281|                document.getElementById('lskypro-load-strategies').disabled = true;
   282|                
   283|                var xhr = new XMLHttpRequest();
   284|                xhr.open('POST', ajaxUrl, true);
   285|                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
   286|                xhr.onload = function() {
   287|                    if (xhr.status === 200) {
   288|                        var data = JSON.parse(xhr.responseText);
   289|                        var select = document.getElementById('lskypro-storage-id');
   290|                        select.innerHTML = '<option value="">请选择存储策略</option>';
   291|                        
   292|                        if (data.success && data.strategies) {
   293|                            data.strategies.forEach(function(item) {
   294|                                var option = document.createElement('option');
   295|                                option.value = item.id;
   296|                                option.textContent = item.name;
   297|                                if (item.id == currentStorageId) option.selected = true;
   298|                                select.appendChild(option);
   299|                            });
   300|                            document.getElementById('lskypro-load-strategies').disabled = false;
   301|                        }
   302|                    }
   303|                    document.getElementById('lskypro-strategies-loading').style.display = 'none';
   304|                };
   305|                xhr.send('action=get_strategies&domain=' + encodeURIComponent(domain) + '&tokens=' + encodeURIComponent(tokens));
   306|            }
   307|            
   308|            // 加载相册列表
   309|            function loadAlbums() {
   310|                var domain = document.getElementById('lskypro-domain').value;
   311|                var tokens = document.getElementById('lskypro-tokens').value;
   312|                
   313|                if (!domain || !tokens) return;
   314|                
   315|                document.getElementById('lskypro-albums-loading').style.display = 'inline';
   316|                document.getElementById('lskypro-load-albums').disabled = true;
   317|                
   318|                var xhr = new XMLHttpRequest();
   319|                xhr.open('POST', ajaxUrl, true);
   320|                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
   321|                xhr.onload = function() {
   322|                    if (xhr.status === 200) {
   323|                        var data = JSON.parse(xhr.responseText);
   324|                        var select = document.getElementById('lskypro-album-id');
   325|                        select.innerHTML = '<option value="">不指定相册</option>';
   326|                        
   327|                        if (data.success && data.albums) {
   328|                            data.albums.forEach(function(item) {
   329|                                var option = document.createElement('option');
   330|                                option.value = item.id;
   331|                                option.textContent = item.name;
   332|                                if (item.id == currentAlbumId) option.selected = true;
   333|                                select.appendChild(option);
   334|                            });
   335|                            document.getElementById('lskypro-load-albums').disabled = false;
   336|                        }
   337|                    }
   338|                    document.getElementById('lskypro-albums-loading').style.display = 'none';
   339|                };
   340|                xhr.send('action=get_albums&domain=' + encodeURIComponent(domain) + '&tokens=' + encodeURIComponent(tokens));
   341|            }
   342|            
   343|            // 刷新按钮
   344|            document.getElementById('lskypro-load-strategies').onclick = loadStrategies;
   345|            document.getElementById('lskypro-load-albums').onclick = loadAlbums;
   346|            
   347|            // 页面加载时如果有配置则自动加载列表
   348|            var domain = document.getElementById('lskypro-domain').value;
   349|            var tokens = document.getElementById('lskypro-tokens').value;
   350|            if (domain && tokens) {
   351|                loadStrategies();
   352|                loadAlbums();
   353|            }
   354|        })();
   355|        </script>
   356|        <?php
   357|    }
   358|    
   359|    /**
   360|     * 个人配置页面
   361|     */
   362|    public static function personalConfig(Typecho\Widget\Helper\Form $form)
   363|    {
   364|        // 个人配置页面（暂不使用）
   365|    }
   366|    
   367|    /**
   368|     * 渲染编辑器上传界面
   369|     */
   370|    public static function render()
   371|    {
   372|        $config = self::getConfig();
   373|        
   374|        if (empty($config['domain']) || empty($config['tokens'])) {
   375|            return;
   376|        }
   377|        
   378|        ?>
   379|        <link rel="stylesheet" href="<?php self::pluginUrl('css/style.css'); ?>">
   380|        <script src="<?php self::pluginUrl('js/axios.min.js'); ?>"></script>
   381|        <script src="<?php self::pluginUrl('js/paste-upload.js'); ?>"></script>
   382|        <script>
   383|            var lskyproData = {
   384|                domain: '<?php echo $config['domain']; ?>',
   385|                tokens: '<?php echo $config['tokens']; ?>',
   386|                api_version: '<?php echo $config['api_version']; ?>',
   387|                permission: '<?php echo $config['permission']; ?>',
   388|                storage_id: '<?php echo $config['storage_id']; ?>',
   389|                album_id: '<?php echo $config['album_id']; ?>',
   390|                max_size: <?php echo intval($config['max_size']); ?>,
   391|                allowed_types: '<?php echo $config['allowed_types']; ?>'.split(',')
   392|            };
   393|        </script>
   394|        
   395|        <div class="lskypro-upload-box">
   396|            <h4>兰空图床上传</h4>
   397|            <div class="lskypro-upload-area" id="lsky-upload-box">点击此区域上传图片</div>
   398|            <input type="file" id="lsky-upload-box-input" multiple accept="image/*" />
   399|            <div id="lskypro-result"></div>
   400|            <p class="lskypro-tip">支持的文件类型: <?php echo $config['allowed_types']; ?><br>最大文件大小: <?php echo $config['max_size']; ?>MB<br>支持粘贴上传 (Ctrl+V)</p>
   401|        </div>
   402|        
   403|        <script>
   404|        (function() {
   405|            var uploadBox = document.getElementById('lsky-upload-box');
   406|            var uploadInput = document.getElementById('lsky-upload-box-input');
   407|            var resultDiv = document.getElementById('lskypro-result');
   408|            
   409|            // 点击上传框触发文件选择
   410|            uploadBox.onclick = function() {
   411|                uploadInput.click();
   412|            };
   413|            
   414|            // 文件选择后处理上传
   415|            uploadInput.onchange = function() {
   416|                handleFileUpload(this.files);
   417|            };
   418|            
   419|            // 处理文件上传
   420|            function handleFileUpload(files) {
   421|                if (!files || files.length === 0) return;
   422|                
   423|                for (var i = 0; i < files.length; i++) {
   424|                    var file = files[i];
   425|                    
   426|                    // 检查文件类型
   427|                    if (lskyproData.allowed_types.length > 0) {
   428|                        var ext = file.name.split('.').pop().toLowerCase();
   429|                        if (lskyproData.allowed_types.indexOf(ext) === -1) {
   430|                            alert('不支持的文件类型: ' + ext);
   431|                            continue;
   432|                        }
   433|                    }
   434|                    
   435|                    // 检查文件大小
   436|                    if (file.size > lskyproData.max_size * 1024 * 1024) {
   437|                        alert('文件过大，最大支持 ' + lskyproData.max_size + 'MB');
   438|                        continue;
   439|                    }
   440|                    
   441|                    uploadFile(file);
   442|                }
   443|            }
   444|            
   445|            // 上传文件
   446|            function uploadFile(file) {
   447|                uploadBox.textContent = '正在上传中...';
   448|                uploadInput.disabled = true;
   449|                
   450|                var formData = new FormData();
   451|                formData.append('file', file);
   452|                
   453|                var apiVersion = lskyproData.api_version;
   454|                if (apiVersion === 'v2') {
   455|                    if (lskyproData.storage_id) formData.append('storage_id', lskyproData.storage_id);
   456|                    if (lskyproData.album_id) formData.append('album_id', lskyproData.album_id);
   457|                    formData.append('is_public', lskyproData.permission === '1' ? 1 : 0);
   458|                } else {
   459|                    formData.append('permission', lskyproData.permission);
   460|                    if (lskyproData.album_id) formData.append('album_id', lskyproData.album_id);
   461|                }
   462|                
   463|                var xhr = new XMLHttpRequest();
   464|                xhr.open('POST', lskyproData.domain + '/api/' + apiVersion + '/upload', true);
   465|                xhr.setRequestHeader('Authorization', 'Bearer ' + lskyproData.tokens);
   466|                xhr.setRequestHeader('Accept', 'application/json');
   467|                
   468|                xhr.onload = function() {
   469|                    if (xhr.status === 200) {
   470|                        var data = JSON.parse(xhr.responseText);
   471|                        var url = '';
   472|                        
   473|                        if (apiVersion === 'v2') {
   474|                            if (data.status === 'success') url = data.data.public_url || data.data.url || '';
   475|                        } else {
   476|                            if (data.status) url = data.data.links.url;
   477|                        }
   478|                        
   479|                        if (url) {
   480|                            showResult(url, file.name);
   481|                        } else {
   482|                            alert('上传失败: ' + (data.message || '未知错误'));
   483|                        }
   484|                    } else {
   485|                        alert('上传失败: HTTP ' + xhr.status);
   486|                    }
   487|                    
   488|                    uploadBox.textContent = '点击此区域上传图片';
   489|                    uploadInput.disabled = false;
   490|                    uploadInput.value = '';
   491|                };
   492|                
   493|                xhr.onerror = function() {
   494|                    alert('网络错误');
   495|                    uploadBox.textContent = '点击此区域上传图片';
   496|                    uploadInput.disabled = false;
   497|                    uploadInput.value = '';
   498|                };
   499|                
   500|                xhr.send(formData);
   501|