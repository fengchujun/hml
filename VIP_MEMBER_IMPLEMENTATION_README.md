# 特邀会员功能实现文档

## 📋 功能概述

本项目实现了一个完整的茶叶小程序特邀会员邀请和审核系统，包括：

- ✅ 特邀会员邀请名额管理
- ✅ 特邀会员申请和审核流程
- ✅ 年度保级机制
- ✅ 个人推广页面（小程序码生成、推广数据统计）
- ✅ 后台审核管理界面
- ✅ 推荐人ID传递和记录

---

## 🎯 核心业务规则

### 1. 特邀会员等级说明

- **等级标识**: `member_level = 2`，`member_level_name = '特邀会员'`
- **基础权益**: 特邀会员折扣 + 专属礼品（首次获得时）
- **附加权益**: 推荐名额（需消费达标获得）

### 2. 消费达标的双重作用

**年消费满50,000元** = 同时实现两个目标：

1. **当年获得2个推荐名额**（当年12月31日前有效）
2. **次年保级成功**（保持特邀会员身份）

**示例**：
- 2026年5月消费满5万 → 2026年5月-12月31日有2个推荐名额 + 2027年全年保级成功
- 2027年只消费3万 → 2027年全年无推荐名额 + 2028年1月1日保级失败，降为积分会员（50万积分档）

### 3. 保级规则

**保级周期**: 自然年（1月1日-12月31日）

- 按自然年统计消费
- 当年消费满5万 → 次年保级成功
- 当年消费不足5万 → 次年1月1日保级失败

**首次成为特邀会员**：
- 被邀请成为特邀会员后，如果当年消费不足5万
- 次年1月1日直接保级失败，降为积分会员（50万积分档）

### 4. 降级与回归

**保级失败后果**：
- 降级为：积分会员（50万积分档）
- 失去：特邀会员身份 + 所有特邀权益
- 获得：50万积分会员权益

**回归方式**：
- 从积分会员无法通过消费升级为特邀会员
- 只能通过特邀会员邀请，重新成为特邀会员

### 5. 邀请名额机制

**名额获取**：
- 消费满5万元 → 获得2个邀请名额
- 名额当年有效（自然年12月31日过期）

**名额状态**：
- `invite_quota`: 总名额
- `invite_quota_used`: 已使用（审核通过后扣除）
- `invite_quota_locked`: 已锁定（申请提交时锁定，审核拒绝后释放）
- 可用名额 = 总名额 - 已使用 - 已锁定

---

## 📁 项目文件结构

```
/home/user/hml/
├── database_migration_vip.sql                    # 数据库迁移脚本（必须先执行）
├── app/
│   ├── model/member/
│   │   └── MemberVip.php                         # 核心业务逻辑模型
│   ├── api/controller/
│   │   └── Membervip.php                         # 前端API控制器
│   └── shop/
│       ├── controller/
│       │   └── Membervip.php                     # 后台管理控制器
│       └── view/membervip/
│           └── application_list.html             # 后台审核页面
├── uniapp/pages_tool/member/
│   ├── promote.vue                               # 个人推广页面
│   ├── vip_apply.vue                             # 特邀会员申请页面
│   └── vip_status.vue                            # 申请状态查看页面
└── VIP_MEMBER_IMPLEMENTATION_README.md           # 本文档
```

---

## 🗄️ 数据库设计

### 1. 新增字段（hml_member表）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `invite_quota` | INT | 邀请名额总数 |
| `invite_quota_used` | INT | 已使用邀请名额 |
| `invite_quota_locked` | INT | 已锁定邀请名额（审核中） |
| `year_consumption` | DECIMAL | 本年度消费金额 |
| `last_check_year` | INT | 最后保级检查年份 |
| `quota_expire_time` | INT | 名额过期时间（当年12月31日23:59:59） |
| `share_qrcode` | VARCHAR | 个人推广小程序码路径 |

### 2. 新增表

#### **hml_member_vip_application** (特邀会员申请表)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `application_id` | INT | 申请ID（主键） |
| `site_id` | INT | 站点ID |
| `member_id` | INT | 申请人会员ID |
| `member_nickname` | VARCHAR | 申请人昵称 |
| `member_mobile` | VARCHAR | 申请人手机号 |
| `inviter_id` | INT | 邀请人会员ID |
| `inviter_nickname` | VARCHAR | 邀请人昵称 |
| `realname` | VARCHAR | 真实姓名 |
| `status` | TINYINT | 审核状态（0=待审核, 1=审核通过, -1=审核拒绝） |
| `audit_time` | INT | 审核时间 |
| `audit_remark` | VARCHAR | 审核意见 |
| `create_time` | INT | 申请时间 |

#### **hml_member_vip_config** (特邀会员配置表)

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | INT | 配置ID（主键） |
| `site_id` | INT | 站点ID |
| `default_quota` | INT | 默认邀请名额（默认2） |
| `consumption_threshold` | DECIMAL | 消费达标门槛（默认50000） |
| `quota_reward` | INT | 达标后奖励名额（默认2） |
| `create_time` | INT | 创建时间 |
| `update_time` | INT | 更新时间 |

---

## 🚀 部署步骤

### Step 1: 执行数据库迁移

```bash
# 在MySQL中执行以下命令
mysql -u your_username -p your_database < /home/user/hml/database_migration_vip.sql
```

或者通过PHPMyAdmin导入 `database_migration_vip.sql` 文件。

### Step 2: 确认文件已上传

确保所有新创建的文件都已上传到服务器对应目录：

- ✅ 后端模型和控制器
- ✅ 前端页面（Vue组件）
- ✅ 后台管理页面（HTML）

### Step 3: 配置路由（如需要）

后端API路由会自动生效，无需额外配置。

前端页面路径：
- 个人推广页面: `/pages_tool/member/promote`
- 特邀会员申请: `/pages_tool/member/vip_apply?inviter_id=X`
- 申请状态查看: `/pages_tool/member/vip_status`

### Step 4: 添加个人中心入口

在 `/uniapp/pages/member/index.vue` 中添加"我的推广"入口：

```vue
<view class="menu-item" @click="goToPromote">
	<text class="iconfont icon-tuiguang"></text>
	<text>我的推广</text>
</view>
```

```javascript
methods: {
	goToPromote() {
		this.$util.redirectTo('/pages_tool/member/promote');
	}
}
```

### Step 5: 配置后台菜单

在后台系统中添加菜单项：

**菜单路径**: `会员管理` > `特邀会员审核`
**链接地址**: `/shop/membervip/applicationList`

---

## 🔄 完整业务流程

### 用户侧流程

```
1. 特邀会员分享链接（自动携带source_member参数）
   ↓
2. 被邀请人点击链接
   ↓
3. App.vue捕获source_member并保存到本地存储
   ↓
4. 未登录 → 自动跳转登录页
   ↓
5. 登录/注册成功
   ↓
6. 系统检查推荐人是否是特邀会员
   ├─ 是特邀会员 + 有名额 → 引导填写申请表
   │     ↓
   │  提交申请 → 锁定名额 → 等待审核
   │     ↓
   │  ├─ 审核通过 → 升级为特邀会员 + 扣除邀请人名额
   │  └─ 审核拒绝 → 释放锁定名额 + 成为普通会员
   │
   └─ 非特邀会员 或 无名额 → 直接成为普通会员
```

### 后台管理流程

```
1. 查看特邀会员申请列表
   ↓
2. 审核操作
   ├─ 通过 → 升级会员等级 + 扣除邀请人名额
   └─ 拒绝 → 释放邀请人名额 + 填写拒绝原因
   ↓
3. 配置管理
   ├─ 设置默认名额（如2个）
   ├─ 设置消费门槛（如50000元）
   └─ 手动调整会员名额
```

### 自动化任务（需额外配置定时任务）

```
1. 订单完成事件 → 累加year_consumption → 达到5万 → 发放2个名额
2. 每年1月1日定时任务 → 检查保级 → 不达标 → 降级为积分会员
```

---

## 📱 前端页面说明

### 1. 个人推广页面 (`promote.vue`)

**访问路径**: `/pages_tool/member/promote`

**功能**：
- 展示会员等级信息
- **特邀会员专属**：
  - 保级进度（年度消费 / 50000元）
  - 邀请名额统计（总数、已用、锁定、可用）
- 推广统计（累计推荐、特邀会员、普通会员）
- 推广工具：
  - 小程序码（自动生成并存储）
  - 引导转发图片（预留位置，需自行添加）
  - 分享按钮（携带source_member参数）
- 推荐会员列表（最近20人）

**小程序码生成逻辑**：
- 首次打开页面时，如果是特邀会员且没有小程序码，自动调用生成接口
- 生成后路径存储在 `hml_member.share_qrcode` 字段
- 后续访问直接读取，无需重复生成

### 2. 特邀会员申请页面 (`vip_apply.vue`)

**访问路径**: `/pages_tool/member/vip_apply?inviter_id=X`

**功能**：
- 显示邀请人信息和剩余名额
- 名额检查（有名额/无名额提示）
- 填写真实姓名表单
- 提交申请（锁定名额）

**跳转时机**：
- 通过特邀会员邀请链接注册/登录后
- 系统检测到推荐人是特邀会员且有名额时自动跳转

### 3. 申请状态查看页面 (`vip_status.vue`)

**访问路径**: `/pages_tool/member/vip_status`

**功能**：
- 显示申请状态（待审核/审核通过/审核拒绝）
- 展示申请信息和审核结果
- 提供相应操作按钮

---

## 🖥️ 后台管理说明

### 审核管理页面

**访问路径**: `/shop/membervip/applicationList`

**功能**：
- 申请列表展示（分页）
- 状态筛选（全部/待审核/已通过/已拒绝）
- 关键词搜索（会员昵称/手机号）
- 审核操作：
  - **通过**: 升级会员等级为特邀会员，扣除邀请人名额
  - **拒绝**: 填写拒绝原因，释放邀请人锁定的名额

**操作权限**：
- 只能审核待审核状态的申请
- 已处理的申请不可再次操作

---

## 🔌 API接口说明

### 前端API（/api/membervip/）

| 接口 | 说明 | 参数 | 返回 |
|------|------|------|------|
| `checkInviterQuota` | 检查邀请人名额 | `inviter_id` | 名额信息 |
| `applyVipMember` | 提交特邀会员申请 | `inviter_id`, `realname` | 申请结果 |
| `getApplicationStatus` | 查询申请状态 | - | 申请信息 |
| `getPromoteStats` | 获取推广统计数据 | - | 推广数据 |

### 后台API（/shop/membervip/）

| 接口 | 说明 | 参数 | 返回 |
|------|------|------|------|
| `applicationList` | 获取申请列表 | `page`, `page_size`, `status` | 列表数据 |
| `approve` | 审核通过 | `application_id` | 操作结果 |
| `reject` | 审核拒绝 | `application_id`, `remark` | 操作结果 |
| `updateQuota` | 修改会员名额 | `member_id`, `quota` | 操作结果 |
| `config` | 获取/更新配置 | `default_quota`, `consumption_threshold`, `quota_reward` | 配置数据 |

---

## ⚠️ 注意事项

### 1. 推荐人ID传递

**✅ 已实现的页面**：
- DIY页面（通过 `diy.js` 自动处理）
- 商品详情页（`goods_detail_base.js`）

**📝 需要检查的页面**：
- 其他自定义页面（如活动页）
- 确保分享时使用 `this.$util.getCurrentShareRoute(member_id)` 方法

**检查方法**：
```javascript
// 在页面的 onShareAppMessage 方法中
onShareAppMessage() {
	let route = this.$util.getCurrentShareRoute(this.$store.state.memberInfo.member_id);
	return {
		title: '分享标题',
		path: route.path,
		imageUrl: '分享图片'
	};
}
```

### 2. 登录后的跳转逻辑

**现有机制**：
- `App.vue` 已实现 `initiateLogin` 机制
- 登录成功后会跳转回原页面
- `loginComplete` 方法处理跳转

**需要添加**：
- 登录成功后检查推荐人是否是特邀会员
- 如果是且有名额，跳转到申请页面
- 否则正常跳转

**实现位置**: 修改 `App.vue` 的 `getMemberInfo()` 或 `login.vue` 的登录成功回调

### 3. 小程序码生成

**生成时机**：
- 特邀会员首次打开推广页面时
- 系统自动调用 `/weapp/api/weapp/createShareQrcode` 接口
- 生成后存储路径到 `hml_member.share_qrcode`

**注意**：
- 确保 `/upload/qrcode/` 目录有写入权限
- 小程序码永久有效，无需重复生成
- 如果生成失败，不影响其他功能正常使用

### 4. 定时任务配置（需额外实施）

**年度保级检查**：
```bash
# 每年1月1日凌晨0:05执行
5 0 1 1 * php /home/user/hml/think queue /app/job/MemberVipAnnualCheck
```

**订单完成后处理**：
- 在订单完成事件中监听
- 累加 `year_consumption` 字段
- 检查是否达到5万，达到则发放名额

### 5. 权限配置

**后台访问权限**：
- 需要在后台权限管理中添加 `特邀会员审核` 菜单
- 分配给相应角色

---

## 🐛 常见问题

### Q1: 推荐人ID没有记录到数据库？

**排查步骤**：
1. 检查分享链接是否包含 `source_member` 参数
2. 查看 `uni.getStorageSync('source_member')` 是否有值
3. 确认登录/注册接口是否传递了 `source_member` 参数
4. 查看后端日志，确认数据是否入库

### Q2: 小程序码生成失败？

**排查步骤**：
1. 检查 `/weapp/api/weapp/createShareQrcode` 接口是否正常
2. 确认 `upload/qrcode/` 目录权限
3. 查看后端日志，确认调用是否成功
4. 检查小程序配置（AppID、AppSecret）

### Q3: 审核通过后会员等级没有升级？

**排查步骤**：
1. 查看 `hml_member_level_records` 表是否有记录
2. 确认事务是否正常提交
3. 检查 `hml_member` 表的 `member_level` 字段是否更新为 2

### Q4: 名额扣除/释放异常？

**排查步骤**：
1. 查看 `invite_quota_locked` 和 `invite_quota_used` 字段
2. 确认审核操作是否成功
3. 检查数据库事务日志

---

## 📞 技术支持

如有问题，请联系开发团队或提交Issue。

**关键文件清单**：
- 数据库脚本: `/home/user/hml/database_migration_vip.sql`
- 核心业务逻辑: `/home/user/hml/app/model/member/MemberVip.php`
- 前端页面: `/home/user/hml/uniapp/pages_tool/member/promote.vue`
- 后台审核页面: `/home/user/hml/app/shop/view/membervip/application_list.html`

---

## 📝 更新日志

**v1.0.0** - 2025-01-15
- ✅ 实现特邀会员邀请和审核功能
- ✅ 实现个人推广页面和小程序码生成
- ✅ 实现后台审核管理界面
- ✅ 实现推荐人ID传递和记录机制

---

**祝您使用愉快！🎉**
