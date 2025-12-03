<?php

namespace app\api\controller;

use app\model\goods\GoodsCategory as GoodsCategoryModel;

/**
 * 商品分类
 * Class Goodscategory
 * @package app\api\controller
 */
class Goodscategory extends BaseApi
{

    /**
     * 树状结构信息
     */
    public function tree()
    {
        $level = $this->params['level'] ?? 3;// 分类等级 1 2 3
        $goods_category_model = new GoodsCategoryModel();
        $condition = [
            [ 'is_show', '=', 0 ],
            [ 'level', '<=', $level ],
            [ 'site_id', '=', $this->site_id ]
        ];

        $field = 'category_id,category_name,short_name,pid,level,image,category_id_1,category_id_2,category_id_3,image_adv,link_url,is_recommend,icon';
        $order = 'sort desc';
        $list = $goods_category_model->getCategoryTree($condition, $field, $order);


    // 获取品牌列表
    $brand_model = new \app\model\goods\GoodsBrand();
    $brand_result = $brand_model->getBrandList(
        [['site_id', '=', $this->site_id]], 
        'brand_id,brand_name,image_url,sort', 
        'sort asc,brand_id desc'
    );
    
    // 获取商品标签列表
    $label_model = new \app\model\goods\GoodsLabel();
    $label_result = $label_model->getLabelList(
        [['site_id', '=', $this->site_id]], 
        'id,label_name,sort', 
        'sort asc,id desc'
    );

    // 重新组装返回数据，保持分类列表的数组格式
    if ($list['code'] >= 0) {
        $category_list = $list['data'];  // 保存原始分类数据
        $brand_list = $brand_result['code'] >= 0 ? $brand_result['data'] : [];
        $label_list = $label_result['code'] >= 0 ? $label_result['data'] : [];
        
        // 重新组装数据结构
        $list['data'] = array_values($category_list);  // 确保是数组而不是对象
        $list['brand_list'] = $brand_list;
        $list['label_list'] = $label_list;
    }

        return $this->response($list);
    }

    public function info()
    {
        $category_id = $this->params[ 'category_id' ] ?? 0;
        if(empty($category_id))
        {
            return $this->response($this->error([], '缺少必须字段category_id'));
        }
        $goods_category_model = new GoodsCategoryModel();
        $res = $goods_category_model->getCategoryInfo([ [ 'category_id', '=', $category_id ], [ 'site_id', '=', $this->site_id ] ]);
        if (!empty($res[ 'data' ])) {
            $category_ids = [ $res[ 'data' ][ 'category_id_1' ], $res[ 'data' ][ 'category_id_2' ], $res[ 'data' ][ 'category_id_3' ] ];
            $category_list = $goods_category_model->getCategoryList([
                [ 'site_id', '=', $this->site_id ],
                [ 'is_show', '=', 0 ],
                [ 'category_id', 'in', $category_ids ]
            ], 'category_id,category_name')[ 'data' ];
            $res[ 'data' ][ 'category_full_name' ] = implode('$_SPLIT_$', array_column($category_list, 'category_name'));

            $child_list = $goods_category_model->getCategoryList([ [ 'pid', '=', $category_id ], [ 'site_id', '=', $this->site_id ], [ 'is_show', '=', 0 ] ], 'category_id,category_name,short_name,pid,level,is_show,sort,image,attr_class_id,attr_class_name,category_id_1,category_id_2,category_id_3,commission_rate,image_adv,is_recommend,icon', 'sort asc,category_id desc')[ 'data' ];
            if (empty($child_list)) {
                // 查询上级商品分类
                $child_list = $goods_category_model->getCategoryList([['pid', '=', $res['data']['pid']], ['site_id', '=', $this->site_id], ['is_show', '=', 0]], 'category_id,category_name,short_name,pid,level,is_show,sort,image,attr_class_id,attr_class_name,category_id_1,category_id_2,category_id_3,commission_rate,image_adv,is_recommend,icon', 'sort asc,category_id desc')['data'];
            }
            $res[ 'data' ][ 'child_list' ] = $child_list;
        }
        return $this->response($res);
    }

    /**
     * 分类列表
     * @return false|string
     */
    public function lists()
    {
        $level = $this->params['level'] ?? 1;// 分类等级 1 2 3
        $goods_category_model = new GoodsCategoryModel();
        $condition = [
            [ 'is_show', '=', 0 ],
            [ 'level', '<=', $level ],
            [ 'site_id', '=', $this->site_id ]
        ];
        $field = 'category_id,category_name,short_name,pid,level,image,category_id_1,category_id_2,category_id_3,image_adv,link_url,is_recommend,icon';
        $order = 'sort asc,category_id desc';
        $res = $goods_category_model->getCategoryList($condition, $field, $order);
        return $this->response($res);
    }
}