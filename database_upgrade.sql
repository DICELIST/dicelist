-- ============================================================
-- 数据库升级脚本 v2 - 邮箱验证 + 邮件系统
-- 在已有数据库上执行，不影响现有数据
-- ============================================================

SET NAMES utf8mb4;

-- 1. users 表新增 email 字段（旧用户允许为空）
ALTER TABLE `users`
  ADD COLUMN `email` varchar(255) NOT NULL DEFAULT '' COMMENT '绑定邮箱' AFTER `nickname`,
  ADD COLUMN `email_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '邮箱是否已验证' AFTER `email`,
  ADD KEY `idx_email` (`email`);

-- 2. 验证码表（注册/改密/换绑通用）
CREATE TABLE IF NOT EXISTS `verify_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL COMMENT '目标邮箱',
  `code` varchar(10) NOT NULL COMMENT '验证码',
  `purpose` varchar(30) NOT NULL COMMENT 'register/reset_pwd/rebind',
  `used` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已使用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  PRIMARY KEY (`id`),
  KEY `idx_email_purpose` (`email`, `purpose`, `used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮箱验证码表';

-- 3. 邮件发送模板表
CREATE TABLE IF NOT EXISTS `mail_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tpl_key` varchar(50) NOT NULL COMMENT '模板标识: register/reset_pwd/rebind',
  `subject` varchar(200) NOT NULL COMMENT '邮件主题',
  `body` text NOT NULL COMMENT '邮件正文（支持 {code} {site_name} {username} 占位符）',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tpl_key` (`tpl_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件模板表';

-- 4. 邮件模板默认内容
INSERT INTO `mail_templates` (`tpl_key`, `subject`, `body`) VALUES
('register',
 '[{site_name}] 邮箱验证码',
 '<div style="font-family:\'PingFang SC\',\'Microsoft YaHei\',sans-serif;max-width:520px;margin:0 auto;background:#f5f7fc;padding:30px 20px;">
  <div style="background:#fff;border-radius:12px;border:1px solid #d8dce8;overflow:hidden;">
    <div style="background:linear-gradient(135deg,#0a84ff,#0060cc);padding:24px 32px;">
      <h2 style="color:#fff;margin:0;font-size:1.3rem;">✉️ 邮箱验证</h2>
      <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:0.9rem;">{site_name}</p>
    </div>
    <div style="padding:32px;">
      <p style="color:#1a1d24;font-size:0.95rem;">你好，<strong>{username}</strong>，</p>
      <p style="color:#5a5f70;font-size:0.9rem;">你正在注册 {site_name} 账号，请使用以下验证码完成验证：</p>
      <div style="text-align:center;margin:28px 0;">
        <span style="display:inline-block;background:#e8f3ff;color:#0a84ff;font-size:2rem;font-weight:800;letter-spacing:0.3em;padding:16px 32px;border-radius:12px;border:2px solid rgba(10,132,255,0.2);">{code}</span>
      </div>
      <p style="color:#8a8fa0;font-size:0.82rem;text-align:center;">验证码 <strong>10分钟</strong> 内有效，请勿泄露给他人。</p>
    </div>
    <div style="background:#f5f7fc;padding:16px 32px;border-top:1px solid #d8dce8;text-align:center;">
      <p style="color:#b0b5c4;font-size:0.78rem;margin:0;">如非本人操作，请忽略此邮件。</p>
    </div>
  </div>
</div>'),
('reset_pwd',
 '[{site_name}] 重置密码验证码',
 '<div style="font-family:\'PingFang SC\',\'Microsoft YaHei\',sans-serif;max-width:520px;margin:0 auto;background:#f5f7fc;padding:30px 20px;">
  <div style="background:#fff;border-radius:12px;border:1px solid #d8dce8;overflow:hidden;">
    <div style="background:linear-gradient(135deg,#0a84ff,#0060cc);padding:24px 32px;">
      <h2 style="color:#fff;margin:0;font-size:1.3rem;">🔑 重置密码</h2>
      <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:0.9rem;">{site_name}</p>
    </div>
    <div style="padding:32px;">
      <p style="color:#1a1d24;font-size:0.95rem;">你好，<strong>{username}</strong>，</p>
      <p style="color:#5a5f70;font-size:0.9rem;">你正在申请重置 {site_name} 账号密码，请使用以下验证码：</p>
      <div style="text-align:center;margin:28px 0;">
        <span style="display:inline-block;background:#fff8e8;color:#f5a623;font-size:2rem;font-weight:800;letter-spacing:0.3em;padding:16px 32px;border-radius:12px;border:2px solid rgba(245,166,35,0.3);">{code}</span>
      </div>
      <p style="color:#8a8fa0;font-size:0.82rem;text-align:center;">验证码 <strong>10分钟</strong> 内有效，请勿泄露给他人。</p>
    </div>
    <div style="background:#f5f7fc;padding:16px 32px;border-top:1px solid #d8dce8;text-align:center;">
      <p style="color:#b0b5c4;font-size:0.78rem;margin:0;">如非本人操作，请及时修改密码保护账号安全。</p>
    </div>
  </div>
</div>'),
('rebind',
 '[{site_name}] 更换绑定邮箱验证码',
 '<div style="font-family:\'PingFang SC\',\'Microsoft YaHei\',sans-serif;max-width:520px;margin:0 auto;background:#f5f7fc;padding:30px 20px;">
  <div style="background:#fff;border-radius:12px;border:1px solid #d8dce8;overflow:hidden;">
    <div style="background:linear-gradient(135deg,#0a84ff,#0060cc);padding:24px 32px;">
      <h2 style="color:#fff;margin:0;font-size:1.3rem;">📧 更换邮箱</h2>
      <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:0.9rem;">{site_name}</p>
    </div>
    <div style="padding:32px;">
      <p style="color:#1a1d24;font-size:0.95rem;">你好，<strong>{username}</strong>，</p>
      <p style="color:#5a5f70;font-size:0.9rem;">你正在为 {site_name} 账号更换绑定邮箱，请使用以下验证码：</p>
      <div style="text-align:center;margin:28px 0;">
        <span style="display:inline-block;background:#e8f8f0;color:#1a6633;font-size:2rem;font-weight:800;letter-spacing:0.3em;padding:16px 32px;border-radius:12px;border:2px solid rgba(52,199,89,0.25);">{code}</span>
      </div>
      <p style="color:#8a8fa0;font-size:0.82rem;text-align:center;">验证码 <strong>10分钟</strong> 内有效，请勿泄露给他人。</p>
    </div>
    <div style="background:#f5f7fc;padding:16px 32px;border-top:1px solid #d8dce8;text-align:center;">
      <p style="color:#b0b5c4;font-size:0.78rem;margin:0;">如非本人操作，你的邮箱未受影响，请忽略此邮件。</p>
    </div>
  </div>
</div>');

-- 5. 邮件SMTP配置（存入site_settings）
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('mail_host',       'smtp.example.com'),
('mail_port',       '465'),
('mail_username',   'noreply@example.com'),
('mail_password',   ''),
('mail_from_name',  'TRPG Bot 导航'),
('mail_encryption', 'ssl');
