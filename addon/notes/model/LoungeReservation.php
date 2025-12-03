<?php
/**
 * 会客厅预约模型
 */

namespace addon\notes\model;

use app\model\BaseModel;
use think\facade\Db;

class LoungeReservation extends BaseModel
{
    /**
     * 添加预约
     */
    public function addReservation($data)
    {
        try {
            // 检查是否已存在未完成的预约（状态为0待预约或1已预约）
            $existingReservation = Db::name('lounge_reservation')
                ->where([
                    ['site_id', '=', $data['site_id']],
                    ['note_id', '=', $data['note_id']],
                    ['member_id', '=', $data['member_id']],
                    ['status', 'in', [0, 1]]
                ])
                ->find();

            if ($existingReservation) {
                return $this->error('', '您已预约过该会客厅，请等待客服联系后再预约');
            }

            $data['create_time'] = time();
            $data['update_time'] = time();

            $res = Db::name('lounge_reservation')->insert($data);

            if ($res) {
                return $this->success(['id' => Db::name('lounge_reservation')->getLastInsID()], '预约成功');
            } else {
                return $this->error('', '预约失败');
            }
        } catch (\Exception $e) {
            return $this->error('', $e->getMessage());
        }
    }

    /**
     * 编辑预约
     */
    public function editReservation($data)
    {
        try {
            $id = $data['id'];

            // 只更新允许的字段
            $updateData = [];
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if (isset($data['reservation_time'])) {
                $updateData['reservation_time'] = $data['reservation_time'];
            }
            $updateData['update_time'] = time();

            // 执行更新
            $res = Db::name('lounge_reservation')
                ->where('id', $id)
                ->update($updateData);

            // 注意：ThinkPHP的update在没有改变数据时会返回0，但这不算失败
            // 所以我们检查 !== false 而不是 > 0
            if ($res !== false) {
                return $this->success([], '更新成功');
            } else {
                return $this->error('', '更新失败');
            }
        } catch (\Exception $e) {
            return $this->error('', $e->getMessage());
        }
    }

    /**
     * 删除预约
     */
    public function deleteReservation($condition)
    {
        try {
            $res = Db::name('lounge_reservation')
                ->where($condition)
                ->delete();

            if ($res) {
                return $this->success([], '删除成功');
            } else {
                return $this->error('', '删除失败');
            }
        } catch (\Exception $e) {
            return $this->error('', $e->getMessage());
        }
    }

    /**
     * 获取预约分页列表
     */
    public function getReservationPageList($condition = [], $page = 1, $page_size = 10, $order = 'create_time desc')
    {
        try {
            $list = Db::name('lounge_reservation')
                ->alias('lr')
                ->join('notes n', 'lr.note_id = n.note_id', 'left')
                ->join('member m', 'lr.member_id = m.member_id', 'left')
                ->field('lr.*, n.note_title, m.nickname, m.mobile')
                ->where($condition)
                ->order($order)
                ->paginate([
                    'list_rows' => $page_size,
                    'page' => $page
                ]);

            return $this->success([
                'count' => $list->total(),
                'page_count' => ceil($list->total() / $page_size),
                'list' => $list->items()
            ]);
        } catch (\Exception $e) {
            return $this->error('', $e->getMessage());
        }
    }

    /**
     * 获取预约详情
     */
    public function getReservationDetail($condition)
    {
        try {
            $info = Db::name('lounge_reservation')
                ->alias('lr')
                ->join('notes n', 'lr.note_id = n.note_id', 'left')
                ->join('member m', 'lr.member_id = m.member_id', 'left')
                ->field('lr.*, n.note_title, m.nickname, m.mobile')
                ->where($condition)
                ->find();

            if ($info) {
                return $this->success($info);
            } else {
                return $this->error('', '未找到预约信息');
            }
        } catch (\Exception $e) {
            return $this->error('', $e->getMessage());
        }
    }

    /**
     * 获取状态文本
     */
    public static function getStatusText($status)
    {
        $statusArray = [
            0 => '待预约',
            1 => '已预约',
            2 => '已到访',
            3 => '已取消'
        ];

        return $statusArray[$status] ?? '未知';
    }
}