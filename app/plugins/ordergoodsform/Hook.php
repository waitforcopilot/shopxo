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
namespace app\plugins\ordergoodsform;

use think\facade\Db;
use app\service\UserService;
use app\plugins\ordergoodsform\service\BaseService;
use app\plugins\ordergoodsform\service\GoodsFormService;

/**
 * 订单商品表单 - 钩子入口
 * @author   Devil
 * @blog     http://gong.gg/
 * @version  0.0.1
 * @datetime 2019-08-11T21:51:08+0800
 */
class Hook
{
    // 基础属性
    public $module_name;
    public $controller_name;
    public $action_name;
    public $mca;
    public $plugins_config;
    public $user;
    public $user_id;

    /**
     * 应用响应入口
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-08-11T14:25:44+0800
     * @param    [array]          $params [输入参数]
     */
    public function handle($params = [])
    {
        // 钩子名称
        if(!empty($params['hook_name']))
        {
            // 当前模块/控制器/方法
            $this->module_name = RequestModule();
            $this->controller_name = RequestController();
            $this->action_name = RequestAction();
            $this->mca = $this->module_name.$this->controller_name.$this->action_name;

            // 配置信息
            $config = BaseService::BaseConfig();
            $this->plugins_config = empty($config['data']) ? [] : $config['data'];

            // 用户信息
            $this->user = UserService::LoginUserInfo();
            $this->user_id = empty($this->user['id']) ? 0 : $this->user['id'];

            // 是否引入公共的表单操作
            $is_style = ((!empty($this->plugins_config['is_goods_detail_form']) && $this->mca == 'indexgoodsindex') || (!empty($this->plugins_config['is_buy_show']) && !empty($this->plugins_config['is_buy_show_form']) && $this->mca == 'indexbuyindex'));

            // 走钩子
            $ret = '';
            switch($params['hook_name'])
            {
                // 公共css
                case 'plugins_css' :
                    if($is_style)
                    {
                        $ret = 'static/plugins/ordergoodsform/css/index/style.css';
                    }
                    break;

                // 公共js
                case 'plugins_js' :
                    if($is_style)
                    {
                        $ret = 'static/plugins/ordergoodsform/js/index/style.js';
                    }
                    break;

                // 商品详情页库存数量顶部
                case 'plugins_view_goods_detail_base_inventory_top' :
                    $ret = $this->GoodsDetailBaseInventoryTopContent($params);
                    break;

                // 订单确认页面商品基础信息底部
                case 'plugins_view_buy_group_goods_inside_base_bottom' :
                    $ret = $this->BuyGoodsBaseBottomContent($params);
                    break;

                // 订单确认页面接口数据
                case 'plugins_service_base_data_return_api_buy_index' :
                    $this->BuyResultHandle($params);
                    break;

                // 订单添加结束
                case 'plugins_service_buy_order_insert_end' :
                    $ret = $this->BuyOrderInsertEndHandle($params);
                    break;

                // 用户/后端订单列表
                case 'plugins_view_index_order_list_operate' :
                case 'plugins_view_admin_order_list_operate' :
                    $ret = $this->OrderListOperateButtonHandle($params);
                    break;

                // 订单列表接口数据 - 手机
                case 'plugins_service_base_data_return_api_order_index' :
                    if(!empty($this->plugins_config['is_index_order_list_operate']) && !empty($params['data']['data']))
                    {
                        $params['data']['data'] = $this->OrderResultHandle($params['data']['data']);
                    }
                    break;
            }
            return $ret;
        }
    }

    /**
     * 订单数据处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $order [订单数据]
     */
    public function OrderResultHandle($order = [])
    {
        $form_order = Db::name('PluginsOrdergoodsformOrderData')->where(['order_id'=>array_column($order, 'id')])->column('order_id');
        if(!empty($form_order))
        {
            foreach($order as &$v)
            {
                if(in_array($v['id'], $form_order))
                {
                    $v['plugins_ordergoodsform_data'] = 1;
                }
            }
        }
        return $order;
    }

    /**
     * 用户/后端订单列表操作按钮
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-14
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function OrderListOperateButtonHandle($params = [])
    {
        if(!empty($params['data']) && !empty($params['data']['id']))
        {
            // 是否开启入口
            switch($this->module_name)
            {
                // 后端
                case 'admin' :
                    $status = !empty($this->plugins_config['is_admin_order_list_operate']);
                    break;

                // 用户端
                case 'index' :
                    $status = !empty($this->plugins_config['is_index_order_list_operate']);
                    break;

                // 默认
                default :
                    $status = false;
            }
            if($status === true)
            {
                $p = ['id'=>$params['data']['id']];
                $url = ($this->module_name == 'admin') ? PluginsAdminUrl('ordergoodsform', 'goodsform', 'order', $p) : PluginsHomeUrl('ordergoodsform', 'goods', 'order', $p);
                MyViewAssign('url', $url);
                return MyView('../../../plugins/ordergoodsform/view/index/public/order_list_operate_button');
            }
        }
    }

    /**
     * 订单添加结束处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-14
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function BuyOrderInsertEndHandle($params = [])
    {
        if(!empty($params['order_id']) && !empty($params['goods']) && !empty($this->user_id))
        {
            $goods_ids = array_unique(array_column($params['goods'], 'goods_id'));
            return GoodsFormService::BuyOrderGoodsFormInsert($params['order_id'], $goods_ids, $this->user_id);
        }
    }

    /**
     * 订单确认页面接口数据
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-01-06
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    private function BuyResultHandle($params = [])
    {
        if(!empty($this->plugins_config['is_app_buy_form']) && !empty($params['data']) && !empty($params['data']['goods_list']))
        {
            foreach($params['data']['goods_list'] as &$v)
            {
                if(!empty($v['goods_items']))
                {
                    foreach($v['goods_items'] as &$g)
                    {
                        $data = GoodsFormService::GoodsDetailNavTopForm($g['goods_id'], $this->user_id);
                        if(!empty($data))
                        {
                            $g['plugins_ordergoodsform_data'] = $data;
                        }
                    }
                }
            }
        }
    }

    /**
     * 订单确认页面商品基础信息底部
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function BuyGoodsBaseBottomContent($params = [])
    {
        if(!empty($this->plugins_config['is_buy_show']) && !empty($params['data']['goods_id']))
        {
            $data = GoodsFormService::GoodsDetailNavTopForm($params['data']['goods_id'], $this->user_id);
            $view = empty($this->plugins_config['is_buy_show_form']) ? 'form_read' : 'form_edit';
            return MyView('../../../plugins/ordergoodsform/view/index/public/'.$view, ['config_data'=>$data, 'goods_id'=>$params['data']['goods_id']]);
        }
    }


    /**
     * 商品详情页库存数量顶部
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-10
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function GoodsDetailBaseInventoryTopContent($params = [])
    {
        if(!empty($this->plugins_config['is_goods_detail_form']) && !empty($params['goods_id']))
        {
            $data = GoodsFormService::GoodsDetailNavTopForm($params['goods_id'], $this->user_id);
            return MyView('../../../plugins/ordergoodsform/view/index/public/form_edit', ['config_data'=>$data, 'goods_id'=>$params['goods_id']]);
        }
    }
}
?>