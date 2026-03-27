-- TRPG Bot 信息展示网站 数据库初始化脚本
-- 数据库名: dicelist
-- 编码: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码哈希',
  `nickname` varchar(100) NOT NULL DEFAULT '' COMMENT '昵称',
  `is_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否管理员 0否 1是',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- ----------------------------
-- 内容表（Bot信息）
-- ----------------------------
CREATE TABLE IF NOT EXISTS `bots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '发布用户ID',
  `platform` varchar(100) NOT NULL DEFAULT '' COMMENT '对接平台',
  `nickname` varchar(100) NOT NULL DEFAULT '' COMMENT '昵称',
  `id_url` varchar(255) NOT NULL DEFAULT '' COMMENT 'ID或URL',
  `framework` varchar(100) NOT NULL DEFAULT '' COMMENT '运行框架',
  `owner` varchar(100) NOT NULL DEFAULT '' COMMENT '骰主',
  `mode` varchar(50) NOT NULL DEFAULT '' COMMENT '模式',
  `blacklist` varchar(50) NOT NULL DEFAULT '' COMMENT '黑名单',
  `status` varchar(50) NOT NULL DEFAULT '' COMMENT '状态',
  `invite_condition` varchar(500) NOT NULL DEFAULT '' COMMENT '邀请条件',
  `remarks` varchar(500) NOT NULL DEFAULT '' COMMENT '备注',
  `description` text COMMENT '介绍（支持Markdown）',
  `view_count` int(11) NOT NULL DEFAULT 0 COMMENT '浏览次数',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_view_count` (`view_count`),
  CONSTRAINT `fk_bots_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bot信息表';

-- ----------------------------
-- 选项表（下拉列表选项）
-- ----------------------------
CREATE TABLE IF NOT EXISTS `options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL COMMENT '选项类型: platform/framework/mode/blacklist/status',
  `value` varchar(100) NOT NULL COMMENT '选项值',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用 1是 0否（软删除）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_type_active` (`type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='下拉选项表';

-- ----------------------------
-- 网站设置表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL COMMENT '设置键名',
  `setting_value` text COMMENT '设置值',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='网站设置表';

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------
-- 初始化下拉选项数据
-- ----------------------------
INSERT INTO `options` (`type`, `value`, `sort_order`) VALUES
-- 对接平台
('platform', 'QQ频道', 1),
('platform', 'QQ', 2),
('platform', 'Telegram', 3),
('platform', 'Discord', 4),
('platform', 'Dodo语音', 5),
('platform', 'KOOK开黑啦', 6),
-- 运行框架
('framework', '海豹核心-SealDice', 1),
('framework', '溯洄骰-Dice', 2),
('framework', '塔骰-SinaNya', 3),
('framework', '星骰-AstralDice', 4),
('framework', '青果骰-OlivaDice', 5),
-- 模式
('mode', '公骰', 1),
('mode', '私骰', 2),
-- 黑名单
('blacklist', '有', 1),
('blacklist', '无', 2),
-- 状态
('status', '在线', 1),
('status', '离线', 2),
('status', '冻结', 3),
('status', '封禁', 4),
('status', '维护', 5),
('status', '废弃', 6),
('status', '未知', 7);

-- ----------------------------
-- 初始化网站设置
-- ----------------------------
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'TRPG Bot 导航'),
('site_description', 'TRPG线上跑团Bot工具展示平台，汇聚优质骰子机器人'),
('copyright', 'Copyright © 2026 TRPG Bot 导航. All Rights Reserved.'),
('icp_number', ''),
('icp_link', 'https://beian.miit.gov.cn/');
