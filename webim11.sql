/*
MySQL Data Transfer

Source Server         : Localhost
Source Server Version : 50528
Source Host           : localhost:3306
Source Database       : webim11

Target Server Type    : MYSQL
Target Server Version : 50528
File Encoding         : 65001

Date: 2016-02-19 01:29:28
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for sys_access
-- ----------------------------
DROP TABLE IF EXISTS `sys_access`;
CREATE TABLE `sys_access` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `accessed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` bigint(20) NOT NULL,
  `referer` tinytext COLLATE utf8_turkish_ci,
  `user_agent` tinytext COLLATE utf8_turkish_ci,
  PRIMARY KEY (`id`),
  KEY `accessed_at` (`accessed_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content
-- ----------------------------
DROP TABLE IF EXISTS `sys_content`;
CREATE TABLE `sys_content` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(25) COLLATE utf8_turkish_ci NOT NULL,
  `language` varchar(5) COLLATE utf8_turkish_ci NOT NULL,
  `url` varchar(100) COLLATE utf8_turkish_ci NOT NULL,
  `title` tinytext COLLATE utf8_turkish_ci NOT NULL,
  `publish_date` datetime NOT NULL,
  `expire_date` datetime DEFAULT NULL,
  `order` decimal(5,0) unsigned DEFAULT NULL,
  `public` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'true',
  `version` int(10) unsigned DEFAULT '1',
  `active` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'true',
  PRIMARY KEY (`id`),
  UNIQUE KEY `content` (`parent_id`,`type`,`language`,`url`) USING BTREE,
  KEY `date` (`publish_date`,`expire_date`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `con_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_category
-- ----------------------------
DROP TABLE IF EXISTS `sys_content_category`;
CREATE TABLE `sys_content_category` (
  `content_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `order` decimal(5,0) unsigned DEFAULT NULL,
  PRIMARY KEY (`content_id`,`category_id`),
  KEY `order` (`category_id`,`order`) USING BTREE,
  CONSTRAINT `con_cat_category_id` FOREIGN KEY (`category_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `con_cat_content_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_comment
-- ----------------------------
DROP TABLE IF EXISTS `sys_content_comment`;
CREATE TABLE `sys_content_comment` (
  `content_id` int(10) unsigned NOT NULL,
  `comment_id` int(10) unsigned NOT NULL,
  `order` decimal(5,0) unsigned DEFAULT NULL,
  PRIMARY KEY (`content_id`,`comment_id`),
  KEY `order` (`comment_id`,`order`) USING BTREE,
  CONSTRAINT `con_com_comment_id` FOREIGN KEY (`comment_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `con_com_content_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_form_value
-- ----------------------------
CREATE TABLE `sys_content_form_value` (
  `content_id` int(10) unsigned NOT NULL,
  `property_id` int(10) unsigned NOT NULL,
  `value` varchar(255) COLLATE utf8_turkish_ci DEFAULT NULL,
  `text` varchar(255) COLLATE utf8_turkish_ci DEFAULT NULL,
  PRIMARY KEY (`content_id`,`property_id`),
  KEY `feature_id` (`property_id`) USING BTREE,
  KEY `content_id` (`content_id`),
  CONSTRAINT `con_for_con_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_hit
-- ----------------------------
DROP TABLE IF EXISTS `sys_content_hit`;
CREATE TABLE `sys_content_hit` (
  `content_id` int(10) unsigned NOT NULL,
  `access_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`content_id`,`access_id`),
  CONSTRAINT `con_hit_content_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_media
-- ----------------------------
DROP TABLE IF EXISTS `sys_content_media`;
CREATE TABLE `sys_content_media` (
  `content_id` int(10) unsigned NOT NULL,
  `media_id` int(10) unsigned NOT NULL,
  `order` tinyint(3) unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8_turkish_ci DEFAULT NULL,
  PRIMARY KEY (`content_id`,`media_id`),
  KEY `order` (`media_id`,`order`),
  CONSTRAINT `con_med_content_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `con_med_media_id` FOREIGN KEY (`media_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_meta
-- ----------------------------
DROP TABLE IF EXISTS `sys_content_meta`;
CREATE TABLE `sys_content_meta` (
  `content_id` int(11) unsigned NOT NULL,
  `key` varchar(100) COLLATE utf8_turkish_ci NOT NULL,
  `value` text COLLATE utf8_turkish_ci,
  PRIMARY KEY (`content_id`,`key`),
  CONSTRAINT `con_met_content_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_object
-- ----------------------------
DROP TABLE IF EXISTS `sys_object`;
CREATE TABLE `sys_object` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `type` enum('user','group') COLLATE utf8_turkish_ci NOT NULL,
  `role` varchar(25) COLLATE utf8_turkish_ci NOT NULL,
  `name` varchar(100) COLLATE utf8_turkish_ci NOT NULL,
  `email` varchar(100) COLLATE utf8_turkish_ci DEFAULT NULL,
  `first_name` varchar(50) COLLATE utf8_turkish_ci DEFAULT NULL,
  `last_name` varchar(50) COLLATE utf8_turkish_ci DEFAULT NULL,
  `version` int(10) unsigned DEFAULT '1',
  `active` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'true',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `email` (`email`),
  KEY `name_group` (`type`,`name`,`active`),
  KEY `email_group` (`type`,`email`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_permission
-- ----------------------------
DROP TABLE IF EXISTS `sys_content_permission`;
CREATE TABLE `sys_content_permission` (
  `content_id` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `permission` varchar(100) COLLATE utf8_turkish_ci NOT NULL,
  `status` enum('grant','deny') COLLATE utf8_turkish_ci DEFAULT 'grant',
  PRIMARY KEY (`content_id`,`object_id`,`permission`),
  KEY `object_id` (`object_id`) USING BTREE,
  CONSTRAINT `con_per_content_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `con_per_object_id` FOREIGN KEY (`object_id`) REFERENCES `sys_object` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_rate
-- ----------------------------
DROP TABLE IF EXISTS `sys_content_rate`;
CREATE TABLE `sys_content_rate` (
  `content_id` int(10) unsigned NOT NULL,
  `access_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `score` decimal(5,2) unsigned NOT NULL DEFAULT '2.50',
  PRIMARY KEY (`content_id`,`access_id`),
  KEY `access_id` (`access_id`) USING BTREE,
  CONSTRAINT `con_rat_access_id` FOREIGN KEY (`access_id`) REFERENCES `sys_access` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `con_rat_content_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_content_tag
-- ----------------------------
DROP TABLE IF EXISTS `sys_content_tag`;
CREATE TABLE `sys_content_tag` (
  `content_id` int(11) unsigned NOT NULL,
  `tag` varchar(100) COLLATE utf8_turkish_ci NOT NULL,
  PRIMARY KEY (`content_id`,`tag`),
  KEY `tag` (`tag`),
  CONSTRAINT `con_tag_content_id` FOREIGN KEY (`content_id`) REFERENCES `sys_content` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
-- ----------------------------
-- Table structure for sys_form
-- ----------------------------
DROP TABLE IF EXISTS `sys_form`;
CREATE TABLE `sys_form` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `language` varchar(5) COLLATE utf8_turkish_ci NOT NULL,
  `name` varchar(50) COLLATE utf8_turkish_ci NOT NULL,
  `label` varchar(255) COLLATE utf8_turkish_ci NOT NULL,
  `version` int(10) unsigned DEFAULT '1',
  `active` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'true',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`language`,`name`),
  KEY `label` (`language`,`label`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_form_field
-- ----------------------------
DROP TABLE IF EXISTS `sys_form_field`;
CREATE TABLE `sys_form_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `language` varchar(5) COLLATE utf8_turkish_ci NOT NULL,
  `type` enum('text','select','radio','checkbox','textarea','file') COLLATE utf8_turkish_ci DEFAULT 'text',
  `name` varchar(50) COLLATE utf8_turkish_ci DEFAULT NULL,
  `label` varchar(255) COLLATE utf8_turkish_ci NOT NULL,
  `default` tinytext COLLATE utf8_turkish_ci,
  `meta` text COLLATE utf8_turkish_ci,
  `version` int(10) unsigned DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`language`,`name`),
  KEY `label` (`language`,`label`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_form_group
-- ----------------------------
DROP TABLE IF EXISTS `sys_form_group`;
CREATE TABLE `sys_form_group` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `language` varchar(5) COLLATE utf8_turkish_ci NOT NULL,
  `label` varchar(255) COLLATE utf8_turkish_ci NOT NULL,
  `version` int(10) unsigned DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `label` (`language`,`label`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_form_property
-- ----------------------------
DROP TABLE IF EXISTS `sys_form_property`;
CREATE TABLE `sys_form_property` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `field_id` int(10) unsigned NOT NULL,
  `meta` text COLLATE utf8_turkish_ci,
  `order` int(10) unsigned DEFAULT NULL,
  `version` int(10) unsigned DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `form` (`form_id`,`group_id`,`field_id`),
  KEY `group_id` (`group_id`),
  KEY `form_id` (`form_id`),
  KEY `field_id` (`field_id`) USING BTREE,
  CONSTRAINT `for_pro_fie_id` FOREIGN KEY (`field_id`) REFERENCES `sys_form_field` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `for_pro_for_id` FOREIGN KEY (`form_id`) REFERENCES `sys_form` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `for_pro_gro_id` FOREIGN KEY (`group_id`) REFERENCES `sys_form_group` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_mail
-- ----------------------------
DROP TABLE IF EXISTS `sys_mail`;
CREATE TABLE `sys_mail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` char(13) COLLATE utf8_turkish_ci NOT NULL,
  `reply_to` int(10) unsigned DEFAULT NULL,
  `sender_id` int(10) unsigned DEFAULT NULL,
  `date` datetime NOT NULL,
  `subject` tinytext COLLATE utf8_turkish_ci,
  `content` text COLLATE utf8_turkish_ci,
  `content_type` enum('plain','html') COLLATE utf8_turkish_ci DEFAULT 'plain',
  `priority` enum('low','normal','high','urgent') COLLATE utf8_turkish_ci DEFAULT 'normal',
  `trashed` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'false',
  `trashed_at` datetime DEFAULT NULL,
  `version` int(10) unsigned DEFAULT '1',
  `active` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'true',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  KEY `sender_id` (`sender_id`) USING BTREE,
  KEY `reply_to` (`reply_to`),
  CONSTRAINT `mai_reply_to` FOREIGN KEY (`reply_to`) REFERENCES `sys_mail` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `mai_sender_id` FOREIGN KEY (`sender_id`) REFERENCES `sys_object` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_mail_attachment
-- ----------------------------
DROP TABLE IF EXISTS `sys_mail_attachment`;
CREATE TABLE `sys_mail_attachment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mail_id` int(10) unsigned NOT NULL,
  `order` tinyint(3) unsigned DEFAULT NULL,
  `file` blob,
  PRIMARY KEY (`id`),
  KEY `mail_id` (`mail_id`),
  CONSTRAINT `mai_att_mail_id` FOREIGN KEY (`mail_id`) REFERENCES `sys_mail` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_mail_recipient
-- ----------------------------
DROP TABLE IF EXISTS `sys_mail_recipient`;
CREATE TABLE `sys_mail_recipient` (
  `mail_id` int(10) unsigned NOT NULL,
  `recipient_id` int(10) unsigned NOT NULL,
  `blind` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'false',
  `read` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'false',
  `read_at` datetime DEFAULT NULL,
  `starred` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'false',
  `starred_at` datetime DEFAULT NULL,
  `archived` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'false',
  `archived_at` datetime DEFAULT NULL,
  `trashed` enum('true','false') COLLATE utf8_turkish_ci DEFAULT 'false',
  `trashed_at` datetime DEFAULT NULL,
  UNIQUE KEY `recipient` (`recipient_id`,`mail_id`) USING BTREE,
  KEY `mail_id` (`mail_id`) USING BTREE,
  CONSTRAINT `mai_rec_mail_id` FOREIGN KEY (`mail_id`) REFERENCES `sys_mail` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `mai_rec_recipient_id` FOREIGN KEY (`recipient_id`) REFERENCES `sys_object` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_object_member
-- ----------------------------
DROP TABLE IF EXISTS `sys_object_member`;
CREATE TABLE `sys_object_member` (
  `parent_id` int(10) unsigned NOT NULL,
  `child_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`parent_id`,`child_id`),
  KEY `child_id` (`child_id`) USING BTREE,
  CONSTRAINT `obj_met_child_id` FOREIGN KEY (`child_id`) REFERENCES `sys_object` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `obj_met_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `sys_object` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_object_meta
-- ----------------------------
DROP TABLE IF EXISTS `sys_object_meta`;
CREATE TABLE `sys_object_meta` (
  `object_id` int(10) unsigned NOT NULL,
  `key` varchar(100) COLLATE utf8_turkish_ci NOT NULL,
  `value` tinytext COLLATE utf8_turkish_ci,
  PRIMARY KEY (`object_id`,`key`),
  CONSTRAINT `obj_met_object_id` FOREIGN KEY (`object_id`) REFERENCES `sys_object` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_object_session
-- ----------------------------
DROP TABLE IF EXISTS `sys_object_session`;
CREATE TABLE `sys_object_session` (
  `access_id` bigint(20) unsigned NOT NULL,
  `login_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `object_id` int(10) unsigned NOT NULL,
  `login_try` decimal(3,0) unsigned DEFAULT '0',
  PRIMARY KEY (`access_id`),
  KEY `object_id` (`object_id`),
  CONSTRAINT `obj_ses_access_id` FOREIGN KEY (`access_id`) REFERENCES `sys_access` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `obj_ses_object_id` FOREIGN KEY (`object_id`) REFERENCES `sys_object` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Table structure for sys_settings
-- ----------------------------
DROP TABLE IF EXISTS `sys_settings`;
CREATE TABLE `sys_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('system','group','user') COLLATE utf8_turkish_ci NOT NULL DEFAULT 'system',
  `owner_id` int(10) unsigned DEFAULT NULL,
  `key` varchar(100) COLLATE utf8_turkish_ci NOT NULL,
  `value` text COLLATE utf8_turkish_ci,
  `version` int(10) unsigned DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings` (`type`,`owner_id`,`key`) USING BTREE,
  KEY `owner` (`type`,`owner_id`),
  KEY `set_owner_id` (`owner_id`),
  CONSTRAINT `set_owner_id` FOREIGN KEY (`owner_id`) REFERENCES `sys_object` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- ----------------------------
-- Add constraint to sys_content_form_value
-- ----------------------------
ALTER TABLE `sys_content_form_value` ADD CONSTRAINT `con_for_pro_id` FOREIGN KEY (`property_id`) REFERENCES `sys_form_property` (`id`) ON UPDATE CASCADE;

-- ----------------------------
-- Default User
-- ----------------------------
INSERT INTO sys_object (`type`, `role`, `name`, `email`, `first_name`, `last_name`) VALUES (
'user', 'root', 'forsaken', 'me@orhanpolat.com', 'Orhan', 'POLAT'
);
INSERT INTO sys_object_meta VALUES (1, 'pass', '6dd075556effaa6e7f1e3e3ba9fdc5fa');


