<?php
/**
 * 特邀会员后台管理控制器
 */

namespace app\shop\controller;

use app\model\member\MemberVip as MemberVipModel;

class Membervip extends BaseShop
{
    /**
     * 特邀会员申请列表页面
     */
    public function applicationList()
    {
        if (request()->isAjax()) {
            $page = input('page', 1);
            $page_size = input('page_size', PAGE_LIST_ROWS);
            $status = input('status', '');
            $search_text = input('search_text', ''); // 搜索关键词

            $model = new MemberVipModel();
            $result = $model->getApplicationList($this->site_id, $status, $page, $page_size);

            return json($result);
        }

        return $this->fetch();
    }

    /**
     * 审核通过
     */
    public function approve()
    {
        $application_id = input('application_id', 0);

        if (empty($application_id)) {
            return json($this->error('', '请提供申请ID'));
        }

        $model = new MemberVipModel();
        $result = $model->approveApplication($application_id, $this->site_id);

        return json($result);
    }

    /**
     * 审核拒绝
     */
    public function reject()
    {
        $application_id = input('application_id', 0);
        $remark = input('remark', '');

        if (empty($application_id)) {
            return json($this->error('', '请提供申请ID'));
        }

        if (empty($remark)) {
            return json($this->error('', '请填写拒绝原因'));
        }

        $model = new MemberVipModel();
        $result = $model->rejectApplication($application_id, $remark, $this->site_id);

        return json($result);
    }

    /**
     * 修改会员邀请名额
     */
    public function updateQuota()
    {
        $member_id = input('member_id', 0);
        $quota = input('quota', 0);

        if (empty($member_id)) {
            return json($this->error('', '请提供会员ID'));
        }

        if ($quota < 0) {
            return json($this->error('', '名额数量不能为负数'));
        }

        $model = new MemberVipModel();
        $result = $model->updateMemberQuota($member_id, $quota, $this->site_id);

        return json($result);
    }

    /**
     * 配置页面
     */
    public function config()
    {
        $model = new MemberVipModel();

        if (request()->isAjax()) {
            $data = [
                'default_quota' => input('default_quota', 2),
                'consumption_threshold' => input('consumption_threshold', 50000),
                'quota_reward' => input('quota_reward', 2)
            ];

            $result = $model->updateVipConfig($this->site_id, $data);
            return json($result);
        }

        // 获取当前配置
        $result = $model->getVipConfig($this->site_id);
        $this->assign('config', $result['data']);

        return $this->fetch();
    }
}
