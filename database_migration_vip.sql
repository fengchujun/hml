-- ========================================
-- 特邀会员功能数据库迁移脚本
-- 执行时间：2025-01-15
-- ========================================

-- 1. 修改 hml_member 表，新增特邀会员相关字段
ALTER TABLE `hml_member`
ADD COLUMN `invite_quota` INT(11) NOT NULL DEFAULT 0 COMMENT '邀请名额总数' AFTER `level_expire_time`,
ADD COLUMN `invite_quota_used` INT(11) NOT NULL DEFAULT 0 COMMENT '已使用邀请名额' AFTER `invite_quota`,
ADD COLUMN `invite_quota_locked` INT(11) NOT NULL DEFAULT 0 COMMENT '已锁定邀请名额(审核中)' AFTER `invite_quota_used`,
ADD COLUMN `year_consumption` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '本年度消费金额' AFTER `invite_quota_locked`,
ADD COLUMN `last_check_year` INT(11) NOT NULL DEFAULT 0 COMMENT '最后保级检查年份' AFTER `year_consumption`,
ADD COLUMN `quota_expire_time` INT(11) NOT NULL DEFAULT 0 COMMENT '名额过期时间(当年12月31日23:59:59)' AFTER `last_check_year`,
ADD COLUMN `share_qrcode` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '个人推广小程序码路径' AFTER `quota_expire_time`;

-- 添加索引
ALTER TABLE `hml_member`
ADD KEY `idx_invite_quota` (`invite_quota`),
ADD KEY `idx_year_consumption` (`year_consumption`);

-- 2. 创建特邀会员申请表
CREATE TABLE IF NOT EXISTS `hml_member_vip_application` (
  `application_id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '申请ID',
  `site_id` INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `member_id` INT(11) NOT NULL COMMENT '申请人会员ID',
  `member_nickname` VARCHAR(50) DEFAULT '' COMMENT '申请人昵称',
  `member_mobile` VARCHAR(50) DEFAULT '' COMMENT '申请人手机号',
  `inviter_id` INT(11) NOT NULL COMMENT '邀请人会员ID',
  `inviter_nickname` VARCHAR(50) DEFAULT '' COMMENT '邀请人昵称',
  `realname` VARCHAR(50) NOT NULL COMMENT '真实姓名',
  `status` TINYINT(4) DEFAULT 0 COMMENT '审核状态：0=待审核, 1=审核通过, -1=审核拒绝',
  `audit_time` INT(11) DEFAULT 0 COMMENT '审核时间',
  `audit_remark` VARCHAR(255) DEFAULT '' COMMENT '审核意见',
  `create_time` INT(11) NOT NULL COMMENT '申请时间',
  PRIMARY KEY (`application_id`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_inviter_id` (`inviter_id`),
  KEY `idx_status` (`status`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='特邀会员申请表';

-- 3. 创建特邀会员配置表
CREATE TABLE IF NOT EXISTS `hml_member_vip_config` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `site_id` INT(11) NOT NULL DEFAULT 0 COMMENT '站点ID',
  `default_quota` INT(11) NOT NULL DEFAULT 2 COMMENT '默认邀请名额',
  `consumption_threshold` DECIMAL(10,2) NOT NULL DEFAULT 50000.00 COMMENT '消费达标门槛',
  `quota_reward` INT(11) NOT NULL DEFAULT 2 COMMENT '达标后奖励名额',
  `create_time` INT(11) DEFAULT 0,
  `update_time` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='特邀会员配置表';

-- 4. 插入默认配置
INSERT INTO `hml_member_vip_config` (`site_id`, `default_quota`, `consumption_threshold`, `quota_reward`, `create_time`)
VALUES (1, 2, 50000.00, 2, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `update_time` = UNIX_TIMESTAMP();

-- 5. 更新现有特邀会员（member_level=2）的数据
-- 给现有的特邀会员设置过期时间为当年12月31日
UPDATE `hml_member`
SET `level_expire_time` = UNIX_TIMESTAMP(CONCAT(YEAR(NOW()), '-12-31 23:59:59'))
WHERE `member_level` = 2 AND `level_expire_time` > 0 AND `level_expire_time` < UNIX_TIMESTAMP(CONCAT(YEAR(NOW()), '-12-31 23:59:59'));

-- ========================================
-- 说明：
-- 1. invite_quota: 邀请名额总数，特邀会员消费达5万后获得2个名额
-- 2. invite_quota_used: 已使用的名额（审核通过后扣除）
-- 3. invite_quota_locked: 锁定的名额（提交申请时锁定，审核拒绝后释放）
-- 4. year_consumption: 本年度消费金额，用于保级和名额发放判断
-- 5. last_check_year: 最后一次保级检查的年份
-- 6. quota_expire_time: 名额过期时间（当年12月31日）
-- 7. share_qrcode: 个人推广小程序码存储路径
-- ========================================
