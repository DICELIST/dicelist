-- ============================================================
-- 数据库升级脚本 v5
-- 在已有数据库(含v4)上执行，不影响现有数据
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 1. 创建 remember_tokens 表（保持登录功能）
-- ============================================================
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `token_hash`  VARCHAR(64)  NOT NULL COMMENT 'SHA-256(token)，不存明文',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='保持登录Token';

-- ============================================================
-- 2. 新增撤回通知邮件模板
-- ============================================================
INSERT IGNORE INTO `mail_templates` (`tpl_key`, `subject`, `body`) VALUES
('bot_revoked',
 '[{site_name}] 你的Bot「{bot_name}」已被撤回',
 '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f9f9f9;border-radius:12px;">
  <h2 style="color:#ff9500;margin-bottom:8px;">↩️ 审核已撤回</h2>
  <p>你好，<strong>{username}</strong>！</p>
  <p>你的 Bot「<strong>{bot_name}</strong>」已被管理员从已通过列表中撤回，暂时不再公开展示。</p>
  <p><strong>撤回原因：</strong>{revoke_reason}</p>
  <p>请根据上述原因修改内容后重新提交审核。如有疑问，请联系站点管理员。</p>
  <p style="margin-top:20px;">
    <a href="{edit_url}" style="display:inline-block;padding:10px 24px;background:#0a84ff;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">修改并重新提交</a>
  </p>
  <hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">
  <p style="font-size:12px;color:#999;">此邮件由 {site_name} 系统自动发送，请勿回复。</p>
</div>');

-- ============================================================
-- 3. 过期 token 清理（可选，定期手动执行）
-- ============================================================
-- DELETE FROM remember_tokens WHERE expires_at < NOW();

-- ============================================================
-- 完成
-- ============================================================
SELECT 'database_upgrade_v5.sql 执行完成' AS status;
