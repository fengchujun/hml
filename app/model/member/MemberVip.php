<?php
/**
 * 特邀会员业务逻辑模型
 */

namespace app\model\member;

use app\model\BaseModel;

class MemberVip extends BaseModel
{
    /**
     * 检查邀请人名额
     * @param int $inviter_id 邀请人ID
     * @param int $site_id 站点ID
     * @return array
     */
    public function checkInviterQuota($inviter_id, $site_id)
    {
        // 获取邀请人信息
        $inviter = model('member')->getInfo([
            ['member_id', '=', $inviter_id],
            ['site_id', '=', $site_id],
            ['member_level', '=', 2], // 特邀会员
            ['is_delete', '=', 0]
        ], 'member_id, nickname, member_level_name, invite_quota, invite_quota_used, invite_quota_locked, quota_expire_time');

        if (empty($inviter)) {
            return $this->error('', '邀请人不是特邀会员');
        }

        // 检查名额是否过期（如果设置了过期时间）
        if ($inviter['quota_expire_time'] > 0 && time() > $inviter['quota_expire_time']) {
            return $this->error('', '邀请人的名额已过期');
        }

        // 计算剩余名额 = 总名额 - 已用 - 锁定中
        $available_quota = $inviter['invite_quota'] - $inviter['invite_quota_used'] - $inviter['invite_quota_locked'];

        return $this->success([
            'has_quota' => $available_quota > 0,
            'available_quota' => max(0, $available_quota),
            'total_quota' => $inviter['invite_quota'],
            'used_quota' => $inviter['invite_quota_used'],
            'locked_quota' => $inviter['invite_quota_locked'],
            'inviter_nickname' => $inviter['nickname'],
            'inviter_level_name' => $inviter['member_level_name']
        ]);
    }

    /**
     * 提交特邀会员申请
     * @param array $data 申请数据
     * @return array
     */
    public function applyVipMember($data)
    {
        // 1. 检查是否已经是特邀会员
        $member_info = model('member')->getInfo([
            ['member_id', '=', $data['member_id']],
            ['site_id', '=', $data['site_id']],
            ['is_delete', '=', 0]
        ], 'member_level, nickname, mobile');

        if (empty($member_info)) {
            return $this->error('', '会员不存在');
        }

        if ($member_info['member_level'] == 2) {
            return $this->error('', '您已经是特邀会员');
        }

        // 2. 检查是否有待审核的申请
        $existing = model('member_vip_application')->getInfo([
            ['member_id', '=', $data['member_id']],
            ['site_id', '=', $data['site_id']],
            ['status', '=', 0]
        ]);

        if (!empty($existing)) {
            return $this->error('', '您有待审核的申请，请等待审核结果');
        }

        // 3. 检查邀请人名额
        $quota_check = $this->checkInviterQuota($data['inviter_id'], $data['site_id']);
        if ($quota_check['code'] < 0) {
            return $quota_check;
        }

        if (!$quota_check['data']['has_quota']) {
            // 名额已用完，返回特殊错误码，前端判断后成为普通会员
            return $this->error('QUOTA_EXHAUSTED', '邀请人的名额已用完，您现在注册的是普通会员');
        }

        // 4. 获取邀请人信息
        $inviter_info = model('member')->getInfo([
            ['member_id', '=', $data['inviter_id']],
            ['site_id', '=', $data['site_id']]
        ], 'nickname');

        // 5. 开启事务
        model('member_vip_application')->startTrans();

        try {
            // 6. 创建申请记录
            $application_data = [
                'site_id' => $data['site_id'],
                'member_id' => $data['member_id'],
                'member_nickname' => $member_info['nickname'],
                'member_mobile' => $member_info['mobile'],
                'inviter_id' => $data['inviter_id'],
                'inviter_nickname' => $inviter_info['nickname'],
                'realname' => $data['realname'],
                'status' => 0,
                'create_time' => time()
            ];

            $application_id = model('member_vip_application')->add($application_data);

            if (!$application_id) {
                throw new \Exception('申请提交失败');
            }

            // 7. 锁定邀请人名额
            model('member')->update([
                'invite_quota_locked' => ['inc', 1]
            ], [
                ['member_id', '=', $data['inviter_id']],
                ['site_id', '=', $data['site_id']]
            ]);

            // 8. 如果需要，更新申请人的推荐人（适用于C邀请B的情况，B原本是A推荐注册的）
            if (!empty($data['update_source_member']) && $data['update_source_member'] == 1) {
                model('member')->update([
                    'source_member' => $data['inviter_id']
                ], [
                    ['member_id', '=', $data['member_id']],
                    ['site_id', '=', $data['site_id']]
                ]);
            }

            model('member_vip_application')->commit();
            return $this->success($application_id, '申请提交成功，请等待审核');

        } catch (\Exception $e) {
            model('member_vip_application')->rollback();
            return $this->error('', '申请提交失败：' . $e->getMessage());
        }
    }

    /**
     * 查询申请状态
     * @param int $member_id 会员ID
     * @param int $site_id 站点ID
     * @return array
     */
    public function getApplicationStatus($member_id, $site_id)
    {
        $application = model('member_vip_application')->getInfo([
            ['member_id', '=', $member_id],
            ['site_id', '=', $site_id]
        ], '*', 'create_time desc');

        if (empty($application)) {
            return $this->success([
                'has_application' => false
            ]);
        }

        $status_text = [
            0 => '待审核',
            1 => '审核通过',
            -1 => '审核拒绝'
        ];

        return $this->success([
            'has_application' => true,
            'application_id' => $application['application_id'],
            'status' => $application['status'],
            'status_text' => $status_text[$application['status']] ?? '未知',
            'realname' => $application['realname'],
            'inviter_nickname' => $application['inviter_nickname'],
            'create_time' => $application['create_time'],
            'audit_time' => $application['audit_time'],
            'audit_remark' => $application['audit_remark']
        ]);
    }

    /**
     * 获取申请列表（后台管理）
     * @param int $site_id 站点ID
     * @param string $status 状态筛选
     * @param string $search_text 搜索关键词（会员昵称或手机号）
     * @param int $page 页码
     * @param int $page_size 每页数量
     * @return array
     */
    public function getApplicationList($site_id, $status = '', $search_text = '', $page = 1, $page_size = PAGE_LIST_ROWS)
    {
        $condition = [
            ['site_id', '=', $site_id]
        ];

        if ($status !== '') {
            $condition[] = ['status', '=', $status];
        }

        // 搜索条件：会员昵称或手机号
        if (!empty($search_text)) {
            $condition[] = ['member_nickname|member_mobile', 'like', '%' . $search_text . '%'];
        }

        $list = model('member_vip_application')->pageList($condition, '*', 'create_time desc', $page, $page_size);

        return $this->success($list);
    }

    /**
     * 审核通过
     * @param int $application_id 申请ID
     * @param int $site_id 站点ID
     * @return array
     */
    public function approveApplication($application_id, $site_id)
    {
        // 1. 获取申请信息
        $application = model('member_vip_application')->getInfo([
            ['application_id', '=', $application_id],
            ['site_id', '=', $site_id]
        ]);

        if (empty($application)) {
            return $this->error('', '申请不存在');
        }

        if ($application['status'] != 0) {
            return $this->error('', '该申请已处理');
        }

        // 2. 开启事务
        model('member_vip_application')->startTrans();

        try {
            // 3. 更新申请状态
            model('member_vip_application')->update([
                'status' => 1,
                'audit_time' => time()
            ], [
                ['application_id', '=', $application_id]
            ]);

            // 4. 升级会员等级为特邀会员
            $expire_time = strtotime(date('Y-12-31 23:59:59')); // 当年最后一秒
            model('member')->update([
                'member_level' => 2,
                'member_level_name' => '特邀会员',
                'member_level_type' => 1,
                'level_expire_time' => $expire_time,
                'is_member' => 1,
                'member_time' => time()
            ], [
                ['member_id', '=', $application['member_id']],
                ['site_id', '=', $site_id]
            ]);

            // 5. 扣除邀请人名额（锁定 -> 已用）
            model('member')->update([
                'invite_quota_locked' => ['dec', 1],
                'invite_quota_used' => ['inc', 1]
            ], [
                ['member_id', '=', $application['inviter_id']],
                ['site_id', '=', $site_id]
            ]);

            // 6. 记录等级变更日志
            model('member_level_records')->add([
                'member_id' => $application['member_id'],
                'site_id' => $site_id,
                'before_level_id' => 1,
                'before_level_name' => '普通会员',
                'before_level_type' => 0,
                'after_level_id' => 2,
                'after_level_name' => '特邀会员',
                'after_level_type' => 1,
                'action_type' => 'admin',
                'action_desc' => '特邀会员申请审核通过',
                'change_type' => 'upgrade',
                'change_time' => time()
            ]);

            model('member_vip_application')->commit();
            return $this->success('', '审核通过');

        } catch (\Exception $e) {
            model('member_vip_application')->rollback();
            return $this->error('', '审核失败：' . $e->getMessage());
        }
    }

    /**
     * 审核拒绝
     * @param int $application_id 申请ID
     * @param string $remark 拒绝原因
     * @param int $site_id 站点ID
     * @return array
     */
    public function rejectApplication($application_id, $remark, $site_id)
    {
        // 1. 获取申请信息
        $application = model('member_vip_application')->getInfo([
            ['application_id', '=', $application_id],
            ['site_id', '=', $site_id]
        ]);

        if (empty($application)) {
            return $this->error('', '申请不存在');
        }

        if ($application['status'] != 0) {
            return $this->error('', '该申请已处理');
        }

        // 2. 开启事务
        model('member_vip_application')->startTrans();

        try {
            // 3. 更新申请状态
            model('member_vip_application')->update([
                'status' => -1,
                'audit_time' => time(),
                'audit_remark' => $remark
            ], [
                ['application_id', '=', $application_id]
            ]);

            // 4. 释放邀请人的锁定名额
            model('member')->update([
                'invite_quota_locked' => ['dec', 1]
            ], [
                ['member_id', '=', $application['inviter_id']],
                ['site_id', '=', $site_id]
            ]);

            model('member_vip_application')->commit();
            return $this->success('', '已拒绝申请');

        } catch (\Exception $e) {
            model('member_vip_application')->rollback();
            return $this->error('', '操作失败：' . $e->getMessage());
        }
    }

    /**
     * 更新会员邀请名额（后台手动调整）
     * @param int $member_id 会员ID
     * @param int $quota 名额数量
     * @param int $site_id 站点ID
     * @return array
     */
    public function updateMemberQuota($member_id, $quota, $site_id)
    {
        $member = model('member')->getInfo([
            ['member_id', '=', $member_id],
            ['site_id', '=', $site_id],
            ['member_level', '=', 2]
        ]);

        if (empty($member)) {
            return $this->error('', '该会员不是特邀会员');
        }

        $res = model('member')->update([
            'invite_quota' => $quota
        ], [
            ['member_id', '=', $member_id],
            ['site_id', '=', $site_id]
        ]);

        if ($res) {
            return $this->success('', '名额更新成功');
        } else {
            return $this->error('', '名额更新失败');
        }
    }

    /**
     * 获取特邀会员配置
     * @param int $site_id 站点ID
     * @return array
     */
    public function getVipConfig($site_id)
    {
        $config = model('member_vip_config')->getInfo([
            ['site_id', '=', $site_id]
        ]);

        if (empty($config)) {
            // 返回默认配置
            $config = [
                'default_quota' => 2,
                'consumption_threshold' => 50000.00,
                'quota_reward' => 2
            ];
        }

        return $this->success($config);
    }

    /**
     * 更新特邀会员配置
     * @param int $site_id 站点ID
     * @param array $data 配置数据
     * @return array
     */
    public function updateVipConfig($site_id, $data)
    {
        $config = model('member_vip_config')->getInfo([
            ['site_id', '=', $site_id]
        ]);

        $update_data = [
            'default_quota' => $data['default_quota'] ?? 2,
            'consumption_threshold' => $data['consumption_threshold'] ?? 50000,
            'quota_reward' => $data['quota_reward'] ?? 2,
            'update_time' => time()
        ];

        if (empty($config)) {
            // 新增
            $update_data['site_id'] = $site_id;
            $update_data['create_time'] = time();
            $res = model('member_vip_config')->add($update_data);
        } else {
            // 更新
            $res = model('member_vip_config')->update($update_data, [
                ['site_id', '=', $site_id]
            ]);
        }

        if ($res) {
            return $this->success('', '配置更新成功');
        } else {
            return $this->error('', '配置更新失败');
        }
    }

    /**
     * 获取会员推广统计数据
     * @param int $member_id 会员ID
     * @param int $site_id 站点ID
     * @return array
     */
    public function getMemberPromoteStats($member_id, $site_id)
    {
        // 1. 获取会员基本信息
        $member = model('member')->getInfo([
            ['member_id', '=', $member_id],
            ['site_id', '=', $site_id],
            ['is_delete', '=', 0]
        ], 'member_id, nickname, member_level, member_level_name, invite_quota, invite_quota_used, invite_quota_locked, year_consumption, quota_expire_time, share_qrcode');

        if (empty($member)) {
            return $this->error('', '会员不存在');
        }

        // 2. 统计推荐的普通会员数量
        $normal_member_count = model('member')->getCount([
            ['source_member', '=', $member_id],
            ['site_id', '=', $site_id],
            ['member_level', '<>', 2], // 非特邀会员
            ['is_delete', '=', 0]
        ]);

        // 3. 统计推荐的特邀会员数量
        $vip_member_count = model('member')->getCount([
            ['source_member', '=', $member_id],
            ['site_id', '=', $site_id],
            ['member_level', '=', 2], // 特邀会员
            ['is_delete', '=', 0]
        ]);

        // 4. 获取推荐的会员列表（最近20个）
        $recommended_members_result = model('member')->getList([
            ['source_member', '=', $member_id],
            ['site_id', '=', $site_id],
            ['is_delete', '=', 0]
        ], 'member_id, nickname, headimg, member_level, member_level_name, reg_time', 'reg_time desc', 1, 20);

        // 处理返回结果（getList可能返回数组结构）
        $recommended_members = [];
        if (is_array($recommended_members_result)) {
            if (isset($recommended_members_result['data'])) {
                $recommended_members = $recommended_members_result['data'];
            } elseif (isset($recommended_members_result[0])) {
                $recommended_members = $recommended_members_result;
            }
        }

        // 5. 计算剩余名额
        $available_quota = 0;
        if ($member['member_level'] == 2) {
            $available_quota = $member['invite_quota'] - $member['invite_quota_used'] - $member['invite_quota_locked'];
        }

        // 6. 计算保级进度
        $preserve_progress = 0;
        $preserve_target = 50000; // 5万元保级门槛
        if ($member['member_level'] == 2 && $preserve_target > 0) {
            $preserve_progress = min(100, round($member['year_consumption'] / $preserve_target * 100, 2));
        }

        return $this->success([
            'member_info' => [
                'member_id' => $member['member_id'] ?? 0,
                'nickname' => $member['nickname'] ?? '',
                'member_level' => $member['member_level'] ?? 1,
                'member_level_name' => $member['member_level_name'] ?? '普通会员',
                'is_vip' => ($member['member_level'] ?? 0) == 2,
                'share_qrcode' => $member['share_qrcode'] ?? ''
            ],
            'quota_info' => [
                'total_quota' => (int)($member['invite_quota'] ?? 0),
                'used_quota' => (int)($member['invite_quota_used'] ?? 0),
                'locked_quota' => (int)($member['invite_quota_locked'] ?? 0),
                'available_quota' => (int)max(0, $available_quota),
                'quota_expire_time' => (int)($member['quota_expire_time'] ?? 0)
            ],
            'preserve_info' => [
                'year_consumption' => (float)($member['year_consumption'] ?? 0),
                'preserve_target' => (float)$preserve_target,
                'preserve_progress' => (float)$preserve_progress,
                'need_amount' => (float)max(0, $preserve_target - ($member['year_consumption'] ?? 0))
            ],
            'stats' => [
                'normal_member_count' => (int)$normal_member_count,
                'vip_member_count' => (int)$vip_member_count,
                'total_count' => (int)($normal_member_count + $vip_member_count)
            ],
            'recommended_members' => is_array($recommended_members) ? $recommended_members : []
        ]);
    }
}
