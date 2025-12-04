# 特邀会员功能部署清单

## 📋 部署前检查

### ✅ 必须完成的步骤

- [ ] 1. **执行数据库迁移**
  ```bash
  mysql -u root -p your_database < database_migration_vip.sql
  ```
  - 创建3个新表：`hml_member_vip_application`、`hml_member_vip_config`
  - 为 `hml_member` 表添加7个新字段
  - 插入默认配置数据

- [ ] 2. **上传后端文件**
  - `/app/model/member/MemberVip.php` → 核心业务逻辑
  - `/app/api/controller/Membervip.php` → 前端API控制器
  - `/app/shop/controller/Membervip.php` → 后台管理控制器
  - `/app/shop/view/membervip/application_list.html` → 后台审核页面

- [ ] 3. **上传前端文件**
  - `/uniapp/pages_tool/member/promote.vue` → 个人推广页面
  - `/uniapp/pages_tool/member/vip_apply.vue` → 特邀会员申请页面
  - `/uniapp/pages_tool/member/vip_status.vue` → 申请状态查看页面

- [ ] 4. **修改 App.vue**
  - 参考 `APP_VUE_MODIFICATIONS.md` 文档
  - 在 `getMemberInfo()` 方法中添加特邀会员检查逻辑
  - 新增 `checkVipInvitation()` 方法

- [ ] 5. **配置后台菜单**
  - 路径：后台 → 权限管理 → 菜单管理
  - 添加菜单：会员管理 → 特邀会员审核
  - URL: `/shop/membervip/applicationList`

- [ ] 6. **添加个人中心入口**
  - 文件：`/uniapp/pages/member/index.vue`
  - 添加"我的推广"菜单项
  - 跳转到：`/pages_tool/member/promote`

---

## 🔧 可选配置

### 定时任务（强烈推荐）

**年度保级检查任务**：
```bash
# 每年1月1日凌晨0:05执行
5 0 1 1 * cd /home/user/hml && php think queue:work --daemon
```

**订单完成事件监听**：
- 在订单完成事件中累加 `year_consumption` 字段
- 检查是否达到50000元，如果达到则发放2个邀请名额

### 上传目录权限
```bash
chmod 755 /home/user/hml/upload/qrcode/
chown www-data:www-data /home/user/hml/upload/qrcode/
```

---

## 🧪 部署后测试

### 测试场景1：特邀会员邀请普通用户

1. **前置条件**：
   - 用户A是特邀会员（member_level=2）
   - 用户A有可用邀请名额（invite_quota > invite_quota_used + invite_quota_locked）

2. **测试步骤**：
   - 用户A打开"我的推广"页面
   - 分享小程序给用户B（携带source_member参数）
   - 用户B通过分享链接打开小程序
   - 用户B注册/登录
   - 系统自动跳转到申请页面
   - 用户B填写真实姓名并提交
   - 查看用户A的 `invite_quota_locked` 是否+1

3. **预期结果**：
   - ✅ 用户B成功提交申请
   - ✅ 用户A的锁定名额+1
   - ✅ 后台看到待审核申请

### 测试场景2：后台审核通过

1. **测试步骤**：
   - 登录后台管理系统
   - 进入"特邀会员审核"页面
   - 选择待审核申请，点击"通过"
   - 确认操作

2. **预期结果**：
   - ✅ 用户B的 `member_level` 变为 2
   - ✅ 用户B的 `member_level_name` 变为 '特邀会员'
   - ✅ 用户A的 `invite_quota_locked` -1，`invite_quota_used` +1
   - ✅ `hml_member_level_records` 表有变更记录

### 测试场景3：后台审核拒绝

1. **测试步骤**：
   - 登录后台管理系统
   - 进入"特邀会员审核"页面
   - 选择待审核申请，点击"拒绝"
   - 填写拒绝原因并确认

2. **预期结果**：
   - ✅ 申请状态变为已拒绝
   - ✅ 用户A的 `invite_quota_locked` -1（释放名额）
   - ✅ 用户B保持普通会员身份

### 测试场景4：名额用完

1. **前置条件**：
   - 用户A的可用名额为0

2. **测试步骤**：
   - 用户C通过用户A的分享链接注册

3. **预期结果**：
   - ✅ 提示"邀请人的名额已用完"
   - ✅ 用户C直接成为普通会员
   - ✅ 不进入申请流程

### 测试场景5：个人推广页面

1. **测试步骤**：
   - 特邀会员登录小程序
   - 进入"我的推广"页面

2. **预期结果**：
   - ✅ 显示保级进度（年度消费/50000）
   - ✅ 显示邀请名额统计
   - ✅ 显示推广统计数据
   - ✅ 自动生成小程序码
   - ✅ 显示推荐的会员列表

---

## 🐛 问题排查

### 问题1：推荐人ID没有记录

**排查步骤**：
```sql
-- 检查会员表的source_member字段
SELECT member_id, nickname, source_member FROM hml_member WHERE member_id = XXX;
```

**常见原因**：
- 分享链接没有携带 `source_member` 参数
- localStorage 存储失败
- 登录接口没有传递 `source_member`

### 问题2：小程序码生成失败

**排查步骤**：
```bash
# 检查目录权限
ls -la /home/user/hml/upload/qrcode/

# 检查API是否正常
curl "http://yourdomain.com/weapp/api/weapp/createShareQrcode?token=xxx"
```

**常见原因**：
- 目录权限不足（需要755）
- 小程序配置错误（AppID/AppSecret）
- 接口调用超时

### 问题3：审核后等级没有变化

**排查步骤**：
```sql
-- 检查会员等级
SELECT member_id, nickname, member_level, member_level_name FROM hml_member WHERE member_id = XXX;

-- 检查等级变更记录
SELECT * FROM hml_member_level_records WHERE member_id = XXX ORDER BY change_time DESC LIMIT 5;
```

**常见原因**：
- 事务回滚（查看错误日志）
- 数据库字段类型错误
- 审核接口返回错误

### 问题4：名额扣除/释放异常

**排查步骤**：
```sql
-- 检查名额字段
SELECT member_id, nickname, invite_quota, invite_quota_used, invite_quota_locked
FROM hml_member
WHERE member_id = XXX;

-- 计算可用名额
SELECT
  member_id,
  nickname,
  invite_quota AS total,
  invite_quota_used AS used,
  invite_quota_locked AS locked,
  (invite_quota - invite_quota_used - invite_quota_locked) AS available
FROM hml_member
WHERE member_level = 2;
```

---

## 📊 数据库验证SQL

### 检查新表是否创建成功
```sql
SHOW TABLES LIKE 'hml_member_vip%';
```

### 检查新字段是否添加成功
```sql
DESCRIBE hml_member;
```

### 查看默认配置
```sql
SELECT * FROM hml_member_vip_config;
```

### 查看所有特邀会员
```sql
SELECT
  member_id,
  nickname,
  member_level_name,
  invite_quota,
  invite_quota_used,
  invite_quota_locked,
  (invite_quota - invite_quota_used - invite_quota_locked) AS available_quota,
  year_consumption,
  level_expire_time
FROM hml_member
WHERE member_level = 2 AND is_delete = 0;
```

### 查看待审核申请
```sql
SELECT * FROM hml_member_vip_application WHERE status = 0 ORDER BY create_time DESC;
```

---

## 📝 部署后记录

### 部署信息

- **部署日期**: ___________
- **部署人员**: ___________
- **数据库备份**: ___________
- **部署环境**: 生产环境 / 测试环境

### 测试结果

- [ ] 特邀会员邀请流程测试通过
- [ ] 后台审核功能测试通过
- [ ] 个人推广页面测试通过
- [ ] 小程序码生成测试通过
- [ ] 名额管理测试通过

### 遗留问题

1. _________________________________
2. _________________________________
3. _________________________________

---

## 🎯 后续优化建议

1. **消息通知**：
   - 申请提交后发送通知给邀请人
   - 审核结果通知申请人
   - 达到保级标准提醒

2. **数据统计**：
   - 特邀会员转化率统计
   - 邀请效果分析
   - 保级成功率统计

3. **用户体验**：
   - 添加推广海报生成功能
   - 优化申请表单（添加更多字段）
   - 增加推广奖励机制

---

**部署完成后，请仔细测试所有场景，确保功能正常运行！** ✅
