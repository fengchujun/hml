<?php
/**
 * 会客厅预约控制器
 */

namespace addon\notes\api\controller;

use app\api\controller\BaseApi;
use addon\notes\model\LoungeReservation as ReservationModel;

class Reservation extends BaseApi
{
    /**
     * 添加预约
     */
    public function add()
    {
        // 检查token并获取member_id
        $token_result = $this->checkToken();
        if ($token_result['code'] < 0) {
            return $this->response($token_result);
        }

        $data = [
            'site_id' => $this->site_id,
            'note_id' => input('note_id', 0),
            'member_id' => $this->member_id,
            'name' => input('name', ''),
            'phone' => input('phone', ''),
            'message' => input('message', ''),
            'status' => 0, // 0待预约
        ];

        // 验证必填字段
        if (empty($data['note_id'])) {
            return $this->response([], 'NOTE_ID_REQUIRED', '会客厅ID不能为空');
        }

        if (empty($data['name'])) {
            return $this->response([], 'NAME_REQUIRED', '姓名不能为空');
        }

        if (empty($data['phone'])) {
            return $this->response([], 'PHONE_REQUIRED', '手机号不能为空');
        }

        if (empty($data['message'])) {
            return $this->response([], 'MESSAGE_REQUIRED', '留言不能为空');
        }

        $model = new ReservationModel();
        $result = $model->addReservation($data);

        return $this->response($result);
    }

    /**
     * 预约列表（用户查看自己的预约）
     */
    public function lists()
    {
        $page = input('page', 1);
        $page_size = input('page_size', 10);

        $model = new ReservationModel();
        $condition = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ];

        $list = $model->getReservationPageList($condition, $page, $page_size);

        return $this->response($list);
    }
}