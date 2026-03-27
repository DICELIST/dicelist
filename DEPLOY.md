# TRPG Bot 导航 - 完整服务器部署教程

> 环境要求：CentOS 7 | Nginx 1.24.0 | MySQL 5.7.44 | PHP 8.2 | 宝塔面板

---

## 目录

1. [宝塔面板安装环境](#一宝塔面板安装环境)
2. [创建数据库](#二创建数据库)
3. [上传网站文件](#三上传网站文件)
4. [创建网站（Nginx配置）](#四创建网站nginx配置)
5. [初始化数据库](#五初始化数据库)
6. [配置文件修改](#六配置文件修改)
7. [权限设置](#七权限设置)
8. [创建管理员账号](#八创建管理员账号)
9. [域名与HTTPS](#九域名与https)
10. [常见问题](#十常见问题)

---

## 一、宝塔面板安装环境

### 1.1 安装宝塔面板（如未安装）

SSH 登录服务器后执行：

```bash
yum install -y wget && wget -O install.sh https://download.bt.cn/install/install_6.0.sh && sh install.sh ed8484bec
```

安装完成后，按提示在浏览器访问宝塔面板地址，默认端口 8888。

### 1.2 安装运行环境

在宝塔面板 **软件商店** 中安装以下软件：

| 软件 | 版本 | 说明 |
|------|------|------|
| Nginx | 1.24.0 | Web服务器 |
| MySQL | 5.7.44 | 数据库 |
| PHP | 8.2 | 后端语言 |

> **PHP 8.2 必装扩展**（在 PHP 管理 → 安装扩展 中安装）：
> - `pdo_mysql`（必须）
> - `mbstring`（必须）
> - `json`（必须）
> - `session`（通常默认已安装）

---

## 二、创建数据库

1. 进入宝塔面板 **数据库** → **添加数据库**

2. 填写以下信息：
   - **数据库名**：`dicelist`
   - **用户名**：`dicelist`
   - **密码**：`RbMAn9YZeNBd9R89`
   - **字符集**：`utf8mb4`（重要！）
   - **访问权限**：本地

3. 点击 **提交** 完成创建

---

## 三、上传网站文件

### 方式A：宝塔文件管理（推荐）

1. 打开宝塔面板 **文件** 菜单
2. 进入 `/www/wwwroot/` 目录
3. 新建文件夹，命名 `dicelist`
4. 点击 **上传**，将整个项目目录的所有文件上传至 `/www/wwwroot/dicelist/`

### 方式B：FTP 上传

1. 宝塔面板 **FTP** → 创建FTP账户
2. 使用 FileZilla 等客户端连接，将文件上传到 `/www/wwwroot/dicelist/`

### 方式C：Git 拉取（如有代码仓库）

```bash
cd /www/wwwroot/
git clone https://your-git-repo.git dicelist
```

---

**上传后目录结构应如下：**

```
/www/wwwroot/dicelist/
├── admin/                  # 管理后台
│   ├── bots.php
│   ├── footer.php
│   ├── header.php
│   ├── index.php
│   ├── options.php
│   ├── settings.php
│   └── users.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── main.js
├── includes/
│   ├── config.php          # ★ 数据库配置（重要）
│   ├── db.php
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── database.sql            # 数据库初始化SQL
├── delete.php
├── detail.php
├── edit.php
├── health.php
├── index.php
├── login.php
├── logout.php
├── profile.php
├── register.php
└── submit.php
```

---

## 四、创建网站（Nginx配置）

1. 宝塔面板 **网站** → **添加站点**

2. 填写信息：
   - **域名**：你的域名（如 `botlist.example.com`）
   - **根目录**：`/www/wwwroot/dicelist`
   - **PHP版本**：PHP-82
   - **数据库**：不选（已手动创建）

3. 添加完成后，点击站点右侧 **设置** → **配置文件**，将配置替换为以下内容：

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;

    root /www/wwwroot/dicelist;
    index index.php index.html;
    charset utf-8;

    access_log /www/wwwlogs/dicelist.access.log;
    error_log  /www/wwwlogs/dicelist.error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass  unix:/tmp/php-cgi-82.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include       fastcgi_params;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\. {
        deny all;
    }

    location /includes/ {
        deny all;
    }
}
```

> 将 `your-domain.com` 替换为你的真实域名，保存后 Nginx 会自动重载。

---

## 五、初始化数据库

### 方式A：宝塔面板 phpMyAdmin（推荐）

1. 宝塔面板 **数据库** → 找到 `dicelist` → 点击 **管理**（会打开phpMyAdmin）
2. 在左侧选择 `dicelist` 数据库
3. 点击顶部 **导入** 选项卡
4. 选择文件：`/www/wwwroot/dicelist/database.sql`
5. 字符集选择 `utf8mb4`，点击 **执行**

### 方式B：命令行

```bash
mysql -u dicelist -pRbMAn9YZeNBd9R89 dicelist < /www/wwwroot/dicelist/database.sql
```

---

## 六、配置文件修改

编辑 `/www/wwwroot/dicelist/includes/config.php`：

```php
<?php
define('DB_HOST', 'localhost');     // 通常不需要修改
define('DB_NAME', 'dicelist');      // 数据库名
define('DB_USER', 'dicelist');      // 数据库用户名
define('DB_PASS', 'RbMAn9YZeNBd9R89'); // 数据库密码（按实际设置）
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL', '');             // 留空即可（相对路径）
define('SESSION_NAME', 'dicelist_session');
define('PER_PAGE', 12);
```

> 如果数据库密码有变动，只需修改 `DB_PASS` 这一行。

---

## 七、权限设置

在服务器终端（或宝塔 **终端**）执行：

```bash
# 设置目录所有者为 www（Nginx 运行用户）
chown -R www:www /www/wwwroot/dicelist

# 设置目录权限（755 = 所有者rwx, 组rx, 其他rx）
find /www/wwwroot/dicelist -type d -exec chmod 755 {} \;

# 设置文件权限（644 = 所有者rw, 组r, 其他r）
find /www/wwwroot/dicelist -type f -exec chmod 644 {} \;
```

> ⚠️ 重要：`includes/config.php` 包含数据库密码，确保它的权限是 644，不能被外部读取（Nginx配置已禁止直接访问 `/includes/`）。

---

## 八、创建管理员账号

数据库初始化后，需手动创建第一个管理员账号。

### 方式A：先注册，再设置管理员（推荐）

1. 访问网站 `http://your-domain.com/register.php`，正常注册账号
2. 在宝塔 phpMyAdmin 中执行以下SQL：

```sql
UPDATE `dicelist`.`users` SET `is_admin` = 1 WHERE `username` = '你的用户名';
```

### 方式B：直接插入管理员账号

在 phpMyAdmin 的 SQL 执行框中运行（替换用户名、昵称和密码哈希）：

```sql
-- 生成密码哈希的方式：在PHP中运行 echo password_hash('你的密码', PASSWORD_DEFAULT);
-- 或在宝塔终端执行：php -r "echo password_hash('你的密码', PASSWORD_DEFAULT);"
INSERT INTO `users` (`username`, `password`, `nickname`, `is_admin`)
VALUES ('admin', '$2y$10$...填入你生成的哈希...', '管理员', 1);
```

**生成密码哈希的命令**（在宝塔终端执行）：

```bash
php82 -r "echo password_hash('你的密码', PASSWORD_DEFAULT);"
# 复制输出的哈希值填入SQL
```

---

## 九、域名与HTTPS

### 申请免费SSL证书（Let's Encrypt）

1. 宝塔面板 **网站** → 找到你的站点 → **设置** → **SSL**
2. 选择 **Let's Encrypt** 选项卡
3. 勾选你的域名，点击 **申请**
4. 申请成功后开启 **强制HTTPS**

### HTTPS 配置后 Nginx 示例

宝塔会自动更新配置，无需手动修改。若需手动操作，确认 443 监听及证书路径正确。

---

## 十、常见问题

### Q1：访问页面出现白屏或 500 错误

检查 PHP 错误日志：
```bash
tail -f /www/wwwlogs/dicelist.error.log
cat /tmp/php82-error.log
```

常见原因：
- `pdo_mysql` 扩展未安装 → 宝塔 PHP 管理 → 扩展 → 安装 pdo_mysql
- 数据库密码不正确 → 检查 `includes/config.php`
- 文件权限问题 → 重新执行第七步权限设置

---

### Q2：页面样式丢失（CSS/JS不加载）

检查 Nginx 配置中 `root` 路径是否正确，以及静态资源路径是否匹配。

---

### Q3：上传文件后显示目录遍历

确认 `index.php` 文件存在于根目录，Nginx 配置中 `index index.php` 已设置。

---

### Q4：Session 无效，每次刷新都需重新登录

检查 PHP Session 配置：
```bash
php82 -r "echo session_save_path();"
```
确保 Session 目录可写：
```bash
chmod 777 /tmp
```

---

### Q5：MySQL 连接失败

测试数据库连接：
```bash
mysql -u dicelist -pRbMAn9YZeNBd9R89 -e "USE dicelist; SHOW TABLES;"
```
若失败，检查：
1. MySQL 是否正在运行：`systemctl status mysqld`
2. 数据库用户权限是否正确（宝塔面板数据库管理中确认）

---

### Q6：中文显示乱码

确认以下三处编码一致（均为 utf8mb4）：
1. 数据库字符集：宝塔 → 数据库 → 管理 → 字符集
2. `config.php` 中 `DB_CHARSET = 'utf8mb4'`
3. Nginx 配置中 `charset utf-8`

---

## 部署完成检查清单

- [ ] 宝塔已安装 Nginx / MySQL 5.7 / PHP 8.2
- [ ] PHP 已安装 pdo_mysql、mbstring 扩展
- [ ] 数据库 `dicelist` 创建完成（用户名/密码正确）
- [ ] 网站文件上传至 `/www/wwwroot/dicelist/`
- [ ] 数据库 SQL (`database.sql`) 已导入
- [ ] Nginx 站点配置正确，`fastcgi_pass` socket路径正确
- [ ] 文件权限已设置（`chown -R www:www`）
- [ ] 管理员账号已创建
- [ ] 访问首页可以正常显示
- [ ] 注册→登录→提交Bot流程测试通过
- [ ] 访问 `/admin/` 管理后台正常
- [ ] （可选）HTTPS 证书已配置

---

> 如有问题，查看宝塔面板的 Nginx 错误日志和 PHP 错误日志，大多数问题都能在日志中找到原因。
