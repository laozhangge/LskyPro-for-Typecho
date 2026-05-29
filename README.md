# LskyPro for Typecho

兰空图床(LskyPro) Typecho 插件 — 通过WordPress兰空图床插件复刻而来

## ⚠️ 重要：安装前必读

插件目录必须命名为 **`LskyPro`**（不是 `LskyPro-for-Typecho`）！

Typecho根据目录名加载插件类，命名空间必须匹配。

## 安装方法

1. 下载本仓库
2. 将目录重命名为 **`LskyPro`**
3. 上传到 Typecho 的 `usr/plugins/LskyPro/Plugin.php`
4. 在后台 → 插件 → 启用
5. 点击"设置"填写API网址和Token
6. 点击"测试连接"验证配置，自动加载存储策略和相册列表

## 功能特性

- ✅ 编辑器上传图片自动存到兰空图床
- ✅ 支持 API V1 / V2 版本
- ✅ 支持存储策略选择
- ✅ 支持相册选择
- ✅ 支持公开/私有权限设置
- ✅ 测试连接功能（无需先保存配置）
- ✅ 上传失败自动回退到本地存储
- ✅ 设置页面可查看可用策略和相册列表

## 配置说明

| 配置项 | 说明 |
|--------|------|
| API网址 | 兰空图床域名，如 `https://pic.laozhang.org` |
| API Token | 后台获取的API令牌 |
| API版本 | 根据兰空版本选择 V1 或 V2 |
| 图片权限 | 公开/私有 |
| 存储策略ID | 留空使用默认，可点击测试连接查看可用策略 |
| 相册ID | 留空不指定，可点击测试连接查看可用相册 |
| 最大上传大小 | 默认10MB |

## 目录结构

```
usr/plugins/LskyPro/
└── Plugin.php
```

只需一个文件即可。

## 作者

- **老张博客** - [https://laozhang.org](https://laozhang.org)
- **GitHub** - [https://github.com/laozhangge/LskyPro-for-Typecho](https://github.com/laozhangge/LskyPro-for-Typecho)

## 兰空图床

- 官网：[https://www.lsky.pro/](https://www.lsky.pro/)
