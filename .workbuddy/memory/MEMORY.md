# 长期记忆 - 信息展示网站项目

## 项目：TRPG Bot 导航

**最后更新：2026-03-28**

### 概述
TRPG 线上跑团 Bot 工具展示平台，骰主可公开展示自己搭建的 Bot。

### 技术偏好
- 后端：PHP 8.2 原生（无框架，保持简洁）
- 数据库：MySQL 5.7（数据库名/用户名均为 dicelist）
- 前端：HTML5 + CSS3 + 原生 JS（无React/Vue等框架）
- 服务器：CentOS 7 + Nginx 1.24 + 宝塔面板

### 项目路径
`d:/桌面/代码开发/信息展示网站/`

### 关键配置
- 数据库密码：`RbMAn9YZeNBd9R89`
- PHP-FPM Socket（宝塔）：`/tmp/php-cgi-82.sock`
- 网站目录（宝塔默认）：`/www/wwwroot/dicelist/`

### 下拉选项类型
platform / framework / mode / blacklist / status

### 版本状态
**v1.0.0 已全部完成（2026-03-28）**

完成的文件清单：
- `admin/header.php` — 侧边栏新增：公告管理、友情链接、协议管理、操作日志
- `admin/agreements.php` ★新建 — 注册/发布协议 CRUD
- `admin/logs.php` ★新建 — 操作日志（筛选+CSV导出）
- `admin/settings.php` — 拆分两个 form section，新增运营设置（审核开关/公告弹窗/登录锁定次数），使用 toggle 开关UI
- `login.php` — 新增 IP 登录锁定 + 封禁检查 + 超管设备绑定验证（首次自动绑定）
- `register.php` — 新增注册协议勾选框（有协议内容时强制），带弹窗预览
- `profile.php` — 昵称唯一性校验（nickname_lower），添加草稿箱/回收站快捷按钮
- `includes/footer.php` — 新增友情链接展示区
- `assets/css/style.css` — 新增友情链接样式（.footer-links-*）和公告样式（.announcement-*）
- `README.md` ★新建 — v1.0.0 完整文档

### 待确认事项（需部署后测试）
- `database_upgrade_v3.sql` 需先在生产数据库执行（已修复唯一索引冲突 bug，见下）
- 超管设备绑定：首次登录后自动将当前 UA+IP 哈希写入 site_settings.super_admin_device
- 如需重置设备绑定：`UPDATE site_settings SET setting_value='' WHERE setting_key='super_admin_device'`

### 已知 Bug 修复记录
- **database_upgrade_v3.sql `#1062` 冲突**（2026-03-28）：原脚本在 ALTER TABLE 中同时加字段和唯一索引，导致已有用户 nickname_lower 全为空字符串产生冲突。修复方案：拆为三步——①加字段、②UPDATE 回填（昵称非空取 LOWER(nickname)，空则用 `__uid_<id>` 占位）、③再加唯一索引。

