<?php
// +----------------------------------------------------------------------
// | ShopXO 国内领先企业级B2C免费开源电商系统
// +----------------------------------------------------------------------
// | Copyright (c) 2011~2099 http://shopxo.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( https://opensource.org/licenses/mit-license.php )
// +----------------------------------------------------------------------
// | Author: Devil
// +----------------------------------------------------------------------
namespace app\plugins\ordergoodsform\admin;

use app\service\GoodsCategoryService;
use app\plugins\ordergoodsform\admin\Common;
use app\plugins\ordergoodsform\service\BaseService;
use app\plugins\ordergoodsform\service\GoodsFormService;

/**
 * 订单商品表单 - 商品表单
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2020-09-10
 * @desc    description
 */
class GoodsForm extends Common
{
    /**
     * 首页
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Index($params = [])
    {
        // 总数
        $total = GoodsFormService::GoodsFormTotal($this->form_where);

        // 分页
        $page_params = [
            'number'    =>  $this->page_size,
            'total'     =>  $total,
            'where'     =>  $this->data_request,
            'page'      =>  $this->page,
            'url'       =>  PluginsAdminUrl('ordergoodsform', 'goodsform', 'index'),
        ];
        $page = new \base\Page($page_params);

        // 获取列表
        $data_params = [
            'where'         => $this->form_where,
            'm'             => $page->GetPageStarNumber(),
            'n'             => $this->page_size,
            'order_by'      => $this->form_order_by['data'],
        ];
        $ret = GoodsFormService::GoodsFormList($data_params);

        // 基础参数赋值
        MyViewAssign('params', $this->data_request);
        MyViewAssign('page_html', $page->GetPageHtml());
        MyViewAssign('data_list', $ret['data']);
        return MyView('../../../plugins/ordergoodsform/view/admin/goodsform/index');
    }

    /**
     * 详情
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Detail($params = [])
    {
        if(!empty($params['id']))
        {
            // 条件
            $where = [
                ['id', '=', intval($params['id'])],
            ];

            // 获取列表
            $data_params = [
                'where'         => $where,
            ];
            $ret = GoodsFormService::GoodsFormList($data_params);
            $data = (empty($ret['data']) || empty($ret['data'][0])) ? [] : $ret['data'][0];
            MyViewAssign('data', $data);
        }
        return MyView('../../../plugins/ordergoodsform/view/admin/goodsform/detail');
    }

    /**
     * 编辑页面
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function SaveInfo($params = [])
    {
        // 数据
        $data = [];
        if(!empty($params['id']))
        {
            // 获取列表
            $data_params = [
                'where' => ['id'=>intval($params['id'])],
            ];
            $ret = GoodsFormService::GoodsFormList($data_params);
            $data = empty($ret['data'][0]) ? [] : $ret['data'][0];
        }

        // 关联的商品数据
        $goods = [
            'goods_ids' => empty($data['goods_list']) ? [] : array_column($data['goods_list'], 'id'),
            'goods'     => empty($data['goods_list']) ? [] : $data['goods_list'],
        ];
        MyViewAssign('goods', $goods);

        // 商品分类
        MyViewAssign('goods_category_list', GoodsCategoryService::GoodsCategoryAll());

        // 静态数据
        MyViewAssign('common_is_text_list', MyConst('common_is_text_list'));
        MyViewAssign('document_type_list', BaseService::ConstData('document_type_list'));

        // 数据
        MyViewAssign('data', $data);
        MyViewAssign('params', $params);
        return MyView('../../../plugins/ordergoodsform/view/admin/goodsform/saveinfo');
    }

    /**
     * 元素选择页面
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Element($params = [])
    {
        $config = GoodsFormService::ConfigDataViewHandle([$this->data_post]);
        $html = MyView('../../../plugins/ordergoodsform/view/admin/goodsform/element', ['module_data'=>['data'=>$config]]);
        return DataReturn('success', 0, ['html'=>$html, 'data'=>$config[0]]);
    }

    /**
     * 添加/编辑
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function Save($params = [])
    {
        // 是否ajax请求
        if(!IS_AJAX)
        {
            return ViewError();
        }

        // 开始处理
        return GoodsFormService::GoodsFormSave($params);
    }

    /**
     * 删除
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function Delete($params = [])
    {
        // 是否ajax请求
        if(!IS_AJAX)
        {
            return ViewError();
        }

        // 开始处理
        $params['admin'] = $this->admin;
        return GoodsFormService::GoodsFormDelete($params);
    }

    /**
     * 状态更新
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function StatusUpdate($params = [])
    {
        // 是否ajax请求
        if(!IS_AJAX)
        {
            return ViewError();
        }

        // 开始处理
        $params['admin'] = $this->admin;
        return GoodsFormService::GoodsFormStatusUpdate($params);
    }

    /**
     * 商品搜索
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-29
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function GoodsSearch($params = [])
    {
        // 是否ajax请求
        if(!IS_AJAX)
        {
            return ViewError();
        }

        // 搜索数据
        return BaseService::GoodsSearchList($params);
    }

    /**
     * 订单数据
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-15
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Order($params = [])
    {
        $ret = GoodsFormService::GoodsFromOrderDataList($params);
        return MyView('../../../plugins/ordergoodsform/view/public/order', ['data'=>$ret['data']]);
    }
}
?>