<?php
/**
 * 特邀会员 API控制器
 */

namespace app\api\controller;

use app\model\member\MemberVip as MemberVipModel;

class Membervip extends BaseApi
{
    /**
     * 检查邀请人名额
     * @return array
     */
    public function checkInviterQuota()
    {
        $inviter_id = input('inviter_id', 0);

        if (empty($inviter_id)) {
            return $this->response($this->error('', '请提供邀请人ID'));
        }

        $model = new MemberVipModel();
        $result = $model->checkInviterQuota($inviter_id, $this->site_id);
        return $this->response($result);
    }

    /**
     * 提交特邀会员申请
     * @return array
     */
    public function applyVipMember()
    {
        $token = $this->checkToken();
        if ($token['code'] < 0) return $this->response($token);

        $data = [
            'member_id' => $this->member_id,
            'site_id' => $this->site_id,
            'inviter_id' => input('inviter_id', 0),
            'realname' => input('realname', '')
        ];

        // 验证必填项
        if (empty($data['inviter_id'])) {
            return $this->response($this->error('', '请提供邀请人ID'));
        }

        if (empty($data['realname'])) {
            return $this->response($this->error('', '请填写真实姓名'));
        }

        $model = new MemberVipModel();
        $result = $model->applyVipMember($data);
        return $this->response($result);
    }

    /**
     * 查询申请状态
     * @return array
     */
    public function getApplicationStatus()
    {
        $token = $this->checkToken();
        if ($token['code'] < 0) return $this->response($token);

        $model = new MemberVipModel();
        $result = $model->getApplicationStatus($this->member_id, $this->site_id);
        return $this->response($result);
    }

    /**
     * 获取会员推广统计数据
     * @return array
     */
    public function getPromoteStats()
    {
        $token = $this->checkToken();
        if ($token['code'] < 0) return $this->response($token);

        $model = new MemberVipModel();
        $result = $model->getMemberPromoteStats($this->member_id, $this->site_id);

        // 如果是特邀会员且没有小程序码，则生成
        if ($result['code'] >= 0 && $result['data']['member_info']['is_vip']) {
            if (empty($result['data']['member_info']['share_qrcode'])) {
                $this->generateShareQrcode($this->member_id);
                // 重新获取数据
                $result = $model->getMemberPromoteStats($this->member_id, $this->site_id);
            }
        }

        return $this->response($result);
    }

    /**
     * 生成推广小程序码（私有方法）
     * @param int $member_id 会员ID
     * @return void
     */
    private function generateShareQrcode($member_id)
    {
        try {
            // 检查文件是否存在
            $member = model('member')->getInfo([
                ['member_id', '=', $member_id]
            ], 'share_qrcode');

            if (!empty($member['share_qrcode']) && file_exists(__ROOT__ . '/' . $member['share_qrcode'])) {
                return; // 文件已存在，不需要重新生成
            }

            // 调用小程序码生成接口
            $api_url = request()->domain() . '/weapp/api/weapp/createShareQrcode';
            $token = request()->param('token');

            // 构造请求参数
            $params = http_build_query(['token' => $token]);
            $response = http($api_url . '?' . $params);

            if ($response) {
                $result = json_decode($response, true);
                if ($result && $result['code'] == 0 && !empty($result['data']['path'])) {
                    // 保存小程序码路径到数据库
                    model('member')->update([
                        'share_qrcode' => $result['data']['path']
                    ], [
                        ['member_id', '=', $member_id]
                    ]);
                }
            }
        } catch (\Exception $e) {
            // 生成失败不影响主流程
            \think\facade\Log::write('生成推广小程序码失败：' . $e->getMessage());
        }
    }
}
