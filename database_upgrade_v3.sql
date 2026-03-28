-- ============================================================
-- 数据库升级脚本 v3 - 大功能更新
-- 在已有数据库(含v2)上执行，不影响现有数据
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 1. users 表扩展字段
-- ============================================================

-- 账号封禁（第一步：只加字段，不加唯一索引）
ALTER TABLE `users`
  ADD COLUMN `is_banned`      tinyint(1)   NOT NULL DEFAULT 0  COMMENT '是否封禁 0否 1是' AFTER `is_admin`,
  ADD COLUMN `ban_reason`     varchar(500) NOT NULL DEFAULT '' COMMENT '封禁原因'          AFTER `is_banned`,
  ADD COLUMN `is_super`       tinyint(1)   NOT NULL DEFAULT 0  COMMENT '是否超级管理员'    AFTER `ban_reason`,
  ADD COLUMN `nickname_lower` varchar(100) NOT NULL DEFAULT '' COMMENT '昵称小写（唯一索引用）' AFTER `nickname`;

-- 第二步：回填 nickname_lower
--   优先使用昵称的小写值；若昵称为空则用 '__uid_<id>' 占位，保证每行唯一
UPDATE `users`
  SET `nickname_lower` = CASE
    WHEN `nickname` != '' THEN LOWER(`nickname`)
    ELSE CONCAT('__uid_', `id`)
  END;

-- 第三步：再加唯一索引（此时每行已有唯一值）
ALTER TABLE `users`
  ADD UNIQUE KEY `uk_nickname` (`nickname_lower`);

-- 用户ID从10000开始（仅影响新注册用户）
ALTER TABLE `users` AUTO_INCREMENT = 10000;

-- ============================================================
-- 2. bots 表增加审核字段
-- ============================================================
ALTER TABLE `bots`
  ADD COLUMN `review_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '审核状态 0待审核 1通过 2拒绝' AFTER `user_id`,
  ADD COLUMN `review_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '审核备注/拒绝原因' AFTER `review_status`,
  ADD COLUMN `reviewed_at`   datetime DEFAULT NULL COMMENT '审核时间' AFTER `review_remark`,
  ADD COLUMN `reviewed_by`   int(11) DEFAULT NULL COMMENT '审核人ID' AFTER `reviewed_at`,
  ADD KEY `idx_review_status` (`review_status`);

-- 已有内容默认设为已审核通过（保留现有数据可见性）
UPDATE `bots` SET `review_status` = 1 WHERE `review_status` = 0;

-- ============================================================
-- 3. 草稿箱表（bot_drafts）
-- ============================================================
CREATE TABLE IF NOT EXISTS `bot_drafts` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`          int(11)      NOT NULL COMMENT '用户ID',
  `bot_id`           int(11)      DEFAULT NULL COMMENT '关联的Bot ID（编辑时）',
  `platform`         varchar(100) NOT NULL DEFAULT '',
  `nickname`         varchar(100) NOT NULL DEFAULT '',
  `id_url`           varchar(255) NOT NULL DEFAULT '',
  `framework`        varchar(100) NOT NULL DEFAULT '',
  `owner`            varchar(100) NOT NULL DEFAULT '',
  `mode`             varchar(50)  NOT NULL DEFAULT '',
  `blacklist`        varchar(50)  NOT NULL DEFAULT '',
  `status`           varchar(50)  NOT NULL DEFAULT '',
  `invite_condition` varchar(500) NOT NULL DEFAULT '',
  `remarks`          varchar(500) NOT NULL DEFAULT '',
  `description`      text,
  `saved_at`         datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后保存时间',
  `created_at`       datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_drafts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='草稿箱';

-- ============================================================
-- 4. 回收站（被拒绝的内容自动移入，视图/别名方式用bots表实现）
-- ============================================================
-- 回收站逻辑直接通过 bots.review_status=2 实现，无需单独表

-- ============================================================
-- 5. 公告表
-- ============================================================
CREATE TABLE IF NOT EXISTS `announcements` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `title`      varchar(200) NOT NULL DEFAULT '' COMMENT '公告标题',
  `content`    text         NOT NULL COMMENT '公告内容（支持HTML）',
  `type`       varchar(20)  NOT NULL DEFAULT 'info' COMMENT '类型: info/warning/success/danger',
  `is_active`  tinyint(1)   NOT NULL DEFAULT 1 COMMENT '是否启用',
  `sort_order` int(11)      NOT NULL DEFAULT 0 COMMENT '排序（越小越靠前）',
  `created_by` int(11)      DEFAULT NULL COMMENT '发布者ID',
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告表';

-- ============================================================
-- 6. 协议表（注册协议 / 发布协议）
-- ============================================================
CREATE TABLE IF NOT EXISTS `agreements` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `ag_key`     varchar(50)  NOT NULL COMMENT '协议键: register_agreement / publish_agreement',
  `title`      varchar(200) NOT NULL DEFAULT '',
  `content`    text         NOT NULL COMMENT '协议正文（支持HTML）',
  `updated_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ag_key` (`ag_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='协议表';

-- 默认协议内容
INSERT IGNORE INTO `agreements` (`ag_key`, `title`, `content`) VALUES
('register_agreement', '用户注册协议',
'<p>欢迎注册 TRPG Bot 导航平台。在注册前，请仔细阅读以下协议：</p>
<ol>
  <li>用户须遵守国家相关法律法规，不得发布违法、违规内容。</li>
  <li>用户对自己发布的内容负全部责任，本站不承担任何连带责任。</li>
  <li>本站有权对违规内容进行删除或封禁账号，无需事先通知。</li>
  <li>用户的账号信息须真实有效，禁止冒用他人身份。</li>
  <li>本站有权随时修改本协议，继续使用即视为接受修改后的协议。</li>
</ol>'),
('publish_agreement', '内容发布协议',
'<p>在提交内容前，请确认以下事项：</p>
<ol>
  <li>发布的 Bot 信息须真实准确，不得含有虚假或误导性内容。</li>
  <li>发布内容须符合平台规范，不得包含广告推销、色情、暴力等违规内容。</li>
  <li>发布者须对所发布的内容拥有合法权利。</li>
  <li>内容经管理员审核通过后方可对外展示，审核通常在 24 小时内完成。</li>
  <li>违规内容将被移至回收站，用户可修改后重新提交审核。</li>
</ol>');

-- ============================================================
-- 7. 友情链接表
-- ============================================================
CREATE TABLE IF NOT EXISTS `friend_links` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `name`       varchar(100) NOT NULL DEFAULT '' COMMENT '链接名称',
  `url`        varchar(500) NOT NULL DEFAULT '' COMMENT '链接地址',
  `logo`       varchar(500) NOT NULL DEFAULT '' COMMENT 'Logo URL（可选）',
  `sort_order` int(11)      NOT NULL DEFAULT 0 COMMENT '排序',
  `is_active`  tinyint(1)   NOT NULL DEFAULT 1 COMMENT '是否启用',
  `created_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='友情链接表';

-- ============================================================
-- 8. 操作日志表
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `admin_id`    int(11)      DEFAULT NULL COMMENT '操作者ID',
  `admin_name`  varchar(100) NOT NULL DEFAULT '' COMMENT '操作者用户名（快照）',
  `action`      varchar(100) NOT NULL DEFAULT '' COMMENT '操作类型',
  `target_type` varchar(50)  NOT NULL DEFAULT '' COMMENT '操作对象类型: user/bot/setting/announcement等',
  `target_id`   int(11)      DEFAULT NULL COMMENT '操作对象ID',
  `detail`      text COMMENT '操作详情',
  `ip`          varchar(45)  NOT NULL DEFAULT '' COMMENT '操作者IP',
  `created_at`  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员操作日志';

-- ============================================================
-- 9. 登录失败记录表（IP锁定）
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         int(11)     NOT NULL AUTO_INCREMENT,
  `ip`         varchar(45) NOT NULL DEFAULT '' COMMENT 'IP地址',
  `account`    varchar(100) NOT NULL DEFAULT '' COMMENT '尝试的账号',
  `created_at` datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录失败记录';

-- ============================================================
-- 10. 超级管理员设备绑定（存文件路径，记录在配置里）
-- 使用 site_settings 存储超管设备指纹哈希
-- ============================================================
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('super_admin_device', ''),       -- 超级管理员绑定设备指纹哈希（空=未绑定）
('login_max_attempts', '5'),      -- 同IP每日最多失败次数
('review_required', '1'),         -- 是否开启内容审核 1开 0关
('announcement_popup', '0');      -- 公告是否弹窗显示 1是 0否
