-- ============================================================
-- 数据库升级脚本 v4
-- 在已有数据库(含v3)上执行，不影响现有数据
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 1. 新增审核通知邮件模板
-- ============================================================
INSERT IGNORE INTO `mail_templates` (`tpl_key`, `subject`, `body`) VALUES
('bot_approved',
 '[{site_name}] 你的Bot「{bot_name}」已通过审核',
 '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f9f9f9;border-radius:12px;">
  <h2 style="color:#34c759;margin-bottom:8px;">✅ 审核通过</h2>
  <p>你好，<strong>{username}</strong>！</p>
  <p>你提交的 Bot「<strong>{bot_name}</strong>」已通过管理员审核，现已对外公开展示。</p>
  <p style="margin-top:20px;">
    <a href="{bot_url}" style="display:inline-block;padding:10px 24px;background:#0a84ff;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">查看Bot详情</a>
  </p>
  <hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">
  <p style="font-size:12px;color:#999;">此邮件由 {site_name} 系统自动发送，请勿回复。</p>
</div>'),

('bot_rejected',
 '[{site_name}] 你的Bot「{bot_name}」审核未通过',
 '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f9f9f9;border-radius:12px;">
  <h2 style="color:#ff3b30;margin-bottom:8px;">❌ 审核未通过</h2>
  <p>你好，<strong>{username}</strong>！</p>
  <p>你提交的 Bot「<strong>{bot_name}</strong>」未能通过管理员审核。</p>
  <p><strong>拒绝原因：</strong>{reject_reason}</p>
  <p>请根据上述原因修改内容后重新提交审核。</p>
  <p style="margin-top:20px;">
    <a href="{edit_url}" style="display:inline-block;padding:10px 24px;background:#0a84ff;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">修改并重新提交</a>
  </p>
  <hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">
  <p style="font-size:12px;color:#999;">此邮件由 {site_name} 系统自动发送，请勿回复。</p>
</div>');

-- ============================================================
-- 2. 说明：图形验证码不需要数据表（使用PHP session存储）
-- ============================================================
-- 图形验证码通过 captcha.php 生成，答案写入 $_SESSION['captcha_code']
-- 无需数据库表

-- ============================================================
-- 完成
-- ============================================================
SELECT 'database_upgrade_v4.sql 执行完成' AS status;
