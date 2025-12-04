<?php
/**
 * 特邀会员业务逻辑模型 - 调试版本
 * 临时文件，用于定位 foreach 错误
 */

namespace app\model\member;

use app\model\BaseModel;

class MemberVipDebug extends BaseModel
{
    /**
     * 获取会员推广统计数据（带详细日志）
     * @param int $member_id 会员ID
     * @param int $site_id 站点ID
     * @return array
     */
    public function getMemberPromoteStats($member_id, $site_id)
    {
        try {
            // 日志1：开始执行
            \think\facade\Log::write('===== getMemberPromoteStats START =====', 'info');
            \think\facade\Log::write('Params: member_id=' . $member_id . ', site_id=' . $site_id, 'info');

            // 日志2：获取会员信息
            \think\facade\Log::write('Step 1: Getting member info...', 'info');
            $member = model('member')->getInfo([
                ['member_id', '=', $member_id],
                ['site_id', '=', $site_id],
                ['is_delete', '=', 0]
            ], 'member_id, nickname, member_level, member_level_name, invite_quota, invite_quota_used, invite_quota_locked, year_consumption, quota_expire_time, share_qrcode');

            \think\facade\Log::write('Member info result: ' . json_encode($member, JSON_UNESCAPED_UNICODE), 'info');

            if (empty($member)) {
                \think\facade\Log::write('Error: Member not found', 'error');
                return $this->error('', '会员不存在');
            }

            // 日志3：统计普通会员
            \think\facade\Log::write('Step 2: Counting normal members...', 'info');
            $normal_member_count = model('member')->getCount([
                ['source_member', '=', $member_id],
                ['site_id', '=', $site_id],
                ['member_level', '<>', 2],
                ['is_delete', '=', 0]
            ]);
            \think\facade\Log::write('Normal member count: ' . $normal_member_count, 'info');

            // 日志4：统计特邀会员
            \think\facade\Log::write('Step 3: Counting VIP members...', 'info');
            $vip_member_count = model('member')->getCount([
                ['source_member', '=', $member_id],
                ['site_id', '=', $site_id],
                ['member_level', '=', 2],
                ['is_delete', '=', 0]
            ]);
            \think\facade\Log::write('VIP member count: ' . $vip_member_count, 'info');

            // 日志5：获取推荐会员列表
            \think\facade\Log::write('Step 4: Getting recommended members list...', 'info');
            $recommended_members_result = model('member')->getList([
                ['source_member', '=', $member_id],
                ['site_id', '=', $site_id],
                ['is_delete', '=', 0]
            ], 'member_id, nickname, headimg, member_level, member_level_name, reg_time', 'reg_time desc', 1, 20);

            \think\facade\Log::write('Raw result type: ' . gettype($recommended_members_result), 'info');
            \think\facade\Log::write('Raw result: ' . json_encode($recommended_members_result, JSON_UNESCAPED_UNICODE), 'info');

            // 处理返回结果
            $recommended_members = [];
            if (is_array($recommended_members_result)) {
                if (isset($recommended_members_result['data'])) {
                    $recommended_members = $recommended_members_result['data'];
                    \think\facade\Log::write('Extracted from data key, count: ' . count($recommended_members), 'info');
                } elseif (isset($recommended_members_result[0])) {
                    $recommended_members = $recommended_members_result;
                    \think\facade\Log::write('Using result directly, count: ' . count($recommended_members), 'info');
                } else {
                    \think\facade\Log::write('WARNING: Result is array but no data found', 'warning');
                }
            } else {
                \think\facade\Log::write('ERROR: Result is not array!', 'error');
            }

            \think\facade\Log::write('Final recommended_members count: ' . count($recommended_members), 'info');

            // 日志6：计算名额
            \think\facade\Log::write('Step 5: Calculating quota...', 'info');
            $available_quota = 0;
            if ($member['member_level'] == 2) {
                $available_quota = $member['invite_quota'] - $member['invite_quota_used'] - $member['invite_quota_locked'];
            }
            \think\facade\Log::write('Available quota: ' . $available_quota, 'info');

            // 日志7：计算保级进度
            \think\facade\Log::write('Step 6: Calculating preserve progress...', 'info');
            $preserve_progress = 0;
            $preserve_target = 50000;
            if ($member['member_level'] == 2 && $preserve_target > 0) {
                $preserve_progress = min(100, round($member['year_consumption'] / $preserve_target * 100, 2));
            }
            \think\facade\Log::write('Preserve progress: ' . $preserve_progress, 'info');

            // 日志8：构造返回数据
            \think\facade\Log::write('Step 7: Building response...', 'info');
            $response = [
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
                'recommended_members' => is_array($recommended_members) ? array_values($recommended_members) : []
            ];

            \think\facade\Log::write('Response built successfully', 'info');
            \think\facade\Log::write('===== getMemberPromoteStats END =====', 'info');

            return $this->success($response);

        } catch (\Exception $e) {
            \think\facade\Log::write('===== EXCEPTION CAUGHT =====', 'error');
            \think\facade\Log::write('Error Message: ' . $e->getMessage(), 'error');
            \think\facade\Log::write('Error File: ' . $e->getFile(), 'error');
            \think\facade\Log::write('Error Line: ' . $e->getLine(), 'error');
            \think\facade\Log::write('Stack Trace: ' . $e->getTraceAsString(), 'error');

            return $this->error('', 'Debug Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
