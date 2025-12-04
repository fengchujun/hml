## 紧急排查清单

### 1. 数据库迁移是否已执行？ ⚠️

**执行命令**：
```bash
mysql -u root -p your_database < /home/user/hml/database_migration_vip.sql
```

**验证字段是否存在**：
```sql
-- 检查 hml_member 表的新字段
DESCRIBE hml_member;

-- 查找这些字段：
-- invite_quota
-- invite_quota_used
-- invite_quota_locked
-- year_consumption
-- last_check_year
-- quota_expire_time
-- share_qrcode
```

**如果字段不存在**，必须先执行数据库迁移脚本！

---

### 2. 检查具体错误位置

**在 MemberVip.php 的 getMemberPromoteStats 方法开头添加调试代码**：

```php
public function getMemberPromoteStats($member_id, $site_id)
{
    // 添加日志
    \think\facade\Log::write('getMemberPromoteStats called: member_id=' . $member_id . ', site_id=' . $site_id);

    try {
        // 原有代码...
        $member = model('member')->getInfo([...]);
        \think\facade\Log::write('Member info: ' . json_encode($member));

        // ... 其他代码

    } catch (\Exception $e) {
        \think\facade\Log::write('Error in getMemberPromoteStats: ' . $e->getMessage());
        return $this->error('', 'Error: ' . $e->getMessage());
    }
}
```

---

### 3. 临时绕过方案

如果数据库迁移还没执行，可以先临时注释掉新字段的查询，让页面先能访问：

```php
// 临时方案：检查字段是否存在
$fields = 'member_id, nickname, member_level, member_level_name';

// 如果数据库已迁移，添加新字段
// $fields .= ', invite_quota, invite_quota_used, invite_quota_locked, year_consumption, quota_expire_time, share_qrcode';

$member = model('member')->getInfo([
    ['member_id', '=', $member_id],
    ['site_id', '=', $site_id],
    ['is_delete', '=', 0]
], $fields);
```

---

### 4. 前端错误可能性

如果后端API返回正常，但前端报错，检查 `promote.vue` 文件：

```vue
<!-- 确保 v-for 遍历的数据存在 -->
<view v-for="(member, index) in recommendedMembers" :key="member.member_id">
  <!-- 改为： -->
</view>

<view v-if="recommendedMembers && recommendedMembers.length > 0">
  <view v-for="(member, index) in recommendedMembers" :key="member.member_id">
    <!-- ... -->
  </view>
</view>
```

---

请先确认**数据库迁移是否已执行**，这是最关键的！
