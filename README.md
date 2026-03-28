# TRPG Bot 导航

> 线上跑团 Bot 工具展示平台，骰主可公开展示自己搭建的骰子机器人（Bot）。  
> 玩家可通过搜索、筛选快速找到适合自己团桌的 Bot。

---

## 技术栈

| 类别 | 技术 |
|------|------|
| 后端 | PHP 8.2（原生，无框架） |
| 数据库 | MySQL 5.7 |
| 前端 | HTML5 + CSS3 + 原生 JavaScript |
| 服务器 | CentOS 7 + Nginx 1.24 + 宝塔面板 |

---

## 功能概览

### 前台
- **Bot 列表**：分页展示、多维筛选（平台/框架/模式/黑名单状态）、关键词搜索
- **Bot 详情**：Markdown 渲染详情；智能 ID/URL 展示（纯数字显示复制按钮；URL 显示跳转按钮+离站提醒）
- **用户注册**：强制绑定邮箱、邮箱验证码、用户名/昵称唯一性校验、注册协议勾选
- **用户登录**：用户名/昵称/邮箱三合一登录；IP 登录失败次数锁定（可配置）
- **忘记密码**：邮箱验证码重置密码
- **个人中心**：修改昵称（唯一性校验）、通过邮箱验证修改密码、换绑邮箱、草稿箱/回收站快捷入口
- **提交/编辑 Bot**：Markdown 编辑器（实时预览）、自动保存草稿、发布协议勾选
- **草稿箱**：查看和继续编辑未完成的草稿
- **回收站**：被拒绝的 Bot 列表，支持重新提交审核或永久删除
- **公告栏**：首页展示管理员发布的公告
- **友情链接**：页脚展示友链

### 管理后台（`/admin/`）
- **仪表盘**：总览统计（总Bot数/总用户数/待审核）
- **Bot 管理**：审核通过/拒绝（附拒绝原因），支持筛选待审核/已通过/已拒绝
- **用户管理**：查看用户、封禁/解封（附封禁原因）、设置/撤销管理员（仅超级管理员）
- **下拉选项管理**：维护 platform/framework/mode/blacklist/status 等筛选项
- **邮件设置**：SMTP 配置、测试发送、邮件模板编辑
- **公告管理**：增删改查公告，支持类型（信息/警告/成功/危险）和启用/禁用
- **友情链接**：增删改查友链，支持 logo/排序/启用禁用
- **协议管理**：编辑注册协议和发布协议（支持 Markdown）
- **操作日志**：记录所有管理员操作，支持按管理员/操作类型筛选，支持导出 CSV
- **网站设置**：基础信息（站名/描述/版权/备案）+ 运营设置（审核开关/公告弹窗/登录锁定次数）

---

## 目录结构

```
/
├── index.php            # 首页（Bot 列表）
├── detail.php           # Bot 详情页
├── submit.php           # 提交 Bot
├── edit.php             # 编辑 Bot
├── draft.php            # 草稿箱
├── draft_save.php       # 草稿自动保存（AJAX）
├── trash.php            # 回收站
├── delete.php           # 删除 Bot
├── login.php            # 登录
├── register.php         # 注册
├── logout.php           # 退出登录
├── forgot_password.php  # 忘记密码
├── profile.php          # 个人中心
├── send_code.php        # 发送邮箱验证码
├── health.php           # 健康检查接口
├── 404.html             # 自定义 404 页面
│
├── includes/
│   ├── config.php       # 数据库配置及常量
│   ├── db.php           # PDO 数据库连接
│   ├── functions.php    # 公共函数库 v3
│   ├── mailer.php       # 原生 SMTP 邮件发送器
│   ├── header.php       # 前台公共头部
│   └── footer.php       # 前台公共页脚（含友情链接）
│
├── admin/
│   ├── header.php       # 后台公共头部（侧边栏）
│   ├── footer.php       # 后台公共页脚
│   ├── index.php        # 仪表盘
│   ├── bots.php         # Bot 管理
│   ├── users.php        # 用户管理
│   ├── options.php      # 下拉选项管理
│   ├── mail.php         # 邮件设置
│   ├── announcements.php # 公告管理
│   ├── links.php        # 友情链接管理
│   ├── agreements.php   # 协议管理
│   ├── logs.php         # 操作日志
│   └── settings.php     # 网站设置
│
├── assets/
│   ├── css/style.css    # 全局样式
│   └── js/main.js       # 全局 JS
│
├── database.sql             # 初始数据库结构
├── database_upgrade.sql     # v2 升级脚本（邮箱/邮件功能）
├── database_upgrade_v3.sql  # v3 升级脚本（审核/封禁/日志等）
├── nginx.conf               # Nginx 配置参考
└── DEPLOY.md                # 部署说明
```

---

## 数据库部署

### 全新安装
```sql
-- 1. 建库
CREATE DATABASE dicelist CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. 依次执行以下脚本
SOURCE database.sql;
SOURCE database_upgrade.sql;
SOURCE database_upgrade_v3.sql;
```

### 从旧版升级
```sql
-- 已有 v1 数据库（仅有 database.sql）→ 执行：
SOURCE database_upgrade.sql;
SOURCE database_upgrade_v3.sql;

-- 已有 v2 数据库 → 仅执行：
SOURCE database_upgrade_v3.sql;
```

### 设置管理员
```sql
-- 将指定用户设为管理员
UPDATE users SET is_admin = 1 WHERE username = '你的用户名';

-- 设为超级管理员（谨慎操作，首次登录会自动绑定当前设备）
UPDATE users SET is_super = 1, is_admin = 1 WHERE username = '你的用户名';
```

---

## 安全特性

| 特性 | 实现方式 |
|------|---------|
| SQL 注入防御 | PDO 预处理语句，全局禁止字符串拼接 SQL |
| XSS 防御 | 所有输出均经 `htmlspecialchars()` / `e()` 转义 |
| CSRF 防御 | 所有 POST 表单携带 CSRF Token，服务端 `hash_equals` 验证 |
| 密码存储 | PHP `password_hash()` 使用 bcrypt 哈希 |
| 登录锁定 | IP 每日登录失败次数超限后锁定（次数可在后台配置） |
| 超管设备绑定 | 超级管理员账号首次登录自动绑定设备指纹，其他设备拒绝访问 |
| 会话安全 | 登录时 `session_regenerate_id(true)` 防止会话固定攻击 |
| 重定向验证 | 登录重定向目标使用正则白名单过滤，防止开放重定向 |
| 邮箱验证码 | 6位随机数，10分钟有效期，使用后立即失效 |

---

## 更新日志

### v1.0.0（2026-03-28）

#### 新功能
- **内容审核系统**：Bot 提交默认进入待审核队列，管理员可通过/拒绝（含拒绝原因），可在网站设置中关闭审核
- **用户封禁系统**：管理员可封禁用户（附原因），被封禁用户立即强制下线，其内容在前台隐藏
- **超级管理员分级**：新增 `is_super` 超级管理员，可管理普通管理员，首次登录自动绑定设备
- **IP 登录锁定**：登录失败次数超限后当天 IP 锁定，次数上限可在后台配置
- **草稿箱**：提交/编辑 Bot 时支持自动保存草稿（每60秒）和手动保存，草稿箱页面管理草稿
- **回收站**：被拒绝的 Bot 进入回收站，可重新提交审核或永久删除
- **公告系统**：后台管理公告，前台首页展示，支持4种类型（信息/警告/成功/危险）
- **友情链接**：后台管理友链，前台页脚展示，支持 logo 和排序
- **协议管理**：注册协议和发布协议可在后台编辑（Markdown格式），注册/提交时强制勾选
- **操作日志**：记录所有管理后台操作，支持筛选和 CSV 导出
- **昵称唯一性**：注册/修改昵称时全局唯一性校验（不区分大小写）
- **用户 ID 从10000起**：新注册用户 ID 从10000开始，避免用户量被轻易猜测

#### 改进
- 首页：仅展示已审核通过、作者未封禁的 Bot；新增公告展示区
- 个人中心：新增草稿箱/回收站快捷入口；昵称修改唯一性校验
- 登录页：添加 IP 失败锁定提示；超管设备验证
- 注册页：新增注册协议勾选框
- 管理后台侧边栏：新增公告、友情链接、协议、操作日志菜单
- 网站设置：新增运营设置卡片（审核开关/公告弹窗/登录锁定次数）
- 自定义 404 页面：完全重新设计，符合网站整体风格
- 页脚：展示友情链接

#### 数据库变更（`database_upgrade_v3.sql`）
- `users` 表新增：`is_banned`、`ban_reason`、`is_super`、`nickname_lower`（唯一索引）
- `bots` 表新增：`review_status`（0待审/1通过/2拒绝）、`review_remark`、`reviewed_at`、`reviewed_by`
- 新增表：`bot_drafts`、`announcements`、`agreements`、`friend_links`、`admin_logs`、`login_attempts`
- `site_settings` 新增键：`super_admin_device`、`login_max_attempts`、`review_required`、`announcement_popup`

---

## 开发环境搭建

```bash
# 1. 将文件放入 Web 根目录
# 2. 修改 includes/config.php 中的数据库配置
# 3. 导入 SQL 脚本
# 4. 确保 PHP 开启扩展：pdo_mysql、openssl、mbstring
# 5. 配置 Nginx（参考 nginx.conf）
```

详细部署步骤见 [DEPLOY.md](./DEPLOY.md)。

---

## 许可证

本项目代码仅供参考学习，请勿用于任何违法用途。
