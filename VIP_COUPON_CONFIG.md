# 特邀会员审核通过自动发放优惠券功能说明

## 功能概述

当特邀会员申请审核通过时，系统会自动发放一张欢迎优惠券给新晋特邀会员。

## 配置方法

### 1. 执行数据库更新

如果您已经执行过之前的迁移脚本，需要执行以下 SQL 添加新字段：

```sql
-- 添加欢迎优惠券配置字段
ALTER TABLE `hml_member_vip_config`
ADD COLUMN IF NOT EXISTS `welcome_coupon_id` INT(11) NOT NULL DEFAULT 0 COMMENT '欢迎优惠券类型ID（审核通过后发放）' AFTER `quota_reward`;
```

如果是全新安装，直接执行完整的 `database_migration_vip.sql` 即可。

### 2. 配置要发放的优惠券

#### 方式一：通过数据库直接配置

1. 找到您想发放的优惠券类型ID（coupon_type_id）
   - 在后台进入：营销 → 优惠券管理
   - 或查询数据库表：`hml_promotion_coupon_type`
   - 记住对应的 `coupon_type_id`

2. 更新配置表：

```sql
UPDATE `hml_member_vip_config`
SET `welcome_coupon_id` = 1  -- 将 1 替换为您的优惠券类型ID
WHERE `site_id` = 1;         -- 如果是多站点，修改对应的站点ID
```

**示例：**
假设您有一张"特邀会员专享券"，coupon_type_id 为 5，则执行：

```sql
UPDATE `hml_member_vip_config`
SET `welcome_coupon_id` = 5
WHERE `site_id` = 1;
```

#### 方式二：创建测试优惠券

如果您还没有合适的优惠券，可以先创建一张：

1. 进入后台：营销 → 优惠券管理 → 添加优惠券
2. 设置优惠券信息：
   - 优惠券名称：特邀会员专享券
   - 优惠类型：满减或折扣
   - 领取方式：可以设置为"不可直接领取"（因为是系统自动发放）
   - 库存：设置足够的数量
3. 保存后，查看优惠券列表找到对应的ID
4. 执行上述 SQL 更新配置

### 3. 禁用自动发券

如果不想自动发放优惠券，将 `welcome_coupon_id` 设置为 0：

```sql
UPDATE `hml_member_vip_config`
SET `welcome_coupon_id` = 0
WHERE `site_id` = 1;
```

## 工作流程

1. 用户申请成为特邀会员
2. 管理员在后台审核通过
3. 系统自动：
   - ✅ 升级会员等级为特邀会员
   - ✅ 扣除邀请人名额
   - ✅ 记录等级变更日志
   - ✅ **发放欢迎优惠券**（如果配置了）
4. 新特邀会员可在"我的优惠券"中查看

## 技术细节

### 发券类型

系统使用 `GET_TYPE_MERCHANT_GIVE`（商家发放）方式发券，优势：
- ✅ 不受优惠券"每人限领"数量限制
- ✅ 不受优惠券总库存限制（只要有库存即可）
- ✅ 不受"是否可直接领取"开关限制

### 发券失败处理

- 发券失败**不会影响**审核流程
- 审核仍然会成功，会员仍然会升级为特邀会员
- 错误会记录在日志中：`runtime/log/` 目录下的日志文件

### 日志查看

发券成功或失败都会记录日志，可以通过以下方式查看：

```bash
# 查看最近的日志
tail -f runtime/log/202501/15.log

# 搜索发券相关日志
grep "特邀会员欢迎优惠券" runtime/log/202501/*.log
```

## 常见问题

### Q1: 优惠券没有发放成功？

**检查步骤：**

1. 确认配置正确：
```sql
SELECT welcome_coupon_id FROM hml_member_vip_config WHERE site_id = 1;
```

2. 确认优惠券存在且有效：
```sql
SELECT * FROM hml_promotion_coupon_type WHERE coupon_type_id = 您的ID;
-- 检查 status=1（启用）且有库存
```

3. 查看日志文件查找错误信息

### Q2: 可以发放多张优惠券吗？

当前版本只支持发放一张优惠券。如需发放多张，可以：
1. 创建一个优惠券礼包
2. 或联系开发人员扩展功能

### Q3: 优惠券类型ID在哪里找？

```sql
-- 查询所有优惠券及其ID
SELECT coupon_type_id, coupon_name, type, money, discount, status
FROM hml_promotion_coupon_type
WHERE site_id = 1 AND status = 1
ORDER BY coupon_type_id DESC;
```

### Q4: 已经审核通过的会员能补发优惠券吗？

需要手动发放。可以通过后台"会员管理 → 发放优惠券"功能给特定会员发券。

## 测试建议

1. 先用测试优惠券进行测试
2. 提交一个测试申请
3. 审核通过后，检查：
   - 会员等级是否升级
   - 优惠券是否到账
   - 日志是否记录

确认无误后再配置正式的优惠券。

## 相关文件

- 数据库迁移：`database_migration_vip.sql`
- 业务逻辑：`app/model/member/MemberVip.php` - `giveWelcomeCoupon()` 方法
- 审核接口：`app/shop/controller/Membervip.php` - `approve()` 方法
