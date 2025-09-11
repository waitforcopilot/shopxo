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
namespace app\plugins\express\service;

use think\facade\Db;
use app\service\UserService;
use app\service\AdminService;
use app\service\OrderService;
use app\service\ExpressService;
use app\service\ResourcesService;
use app\plugins\express\service\ExpressQueryService;

/**
 * 物流查询 - 快递处理服务层
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2021-11-18
 * @desc    description
 */
class ExpressHandleService
{
    /**
     * 快递数据映射
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]           $config [插件配置]
     * @param   [array]           $params [输入参数]
     */
    public static function ExpressRun($config, $params = [])
    {
        // 是否指定方法
        $action_type = empty($params['action_type']) ? '' : $params['action_type'];
        switch($action_type)
        {
            // 门店
            case 'realstore' :
            // 进销存里面的门店订单
            case 'realstore_erp' :
                $ret = self::RealStoreExpressData($config, $params);
                break;

            // 默认
            default :
                $ret = self::ExpressData($config, $params);
        }
        return $ret;
    }

    /**
     * 快递数据读取
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]           $config [插件配置]
     * @param   [array]           $params [输入参数]
     */
    public static function ExpressData($config, $params = [])
    {
        // 请求参数
        $p = [
            [
                'checked_type'      => 'empty',
                'key_name'          => 'oid',
                'error_msg'         => '数据oid为空',
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 获取订单数据
        $where = [
            ['id', '=', intval($params['oid'])],
            ['status', 'in', [3,4]],
            ['order_model', '=', 0],
        ];
        // 非管理员则加上用户id
        if(RequestModule() == 'admin')
        {
            $admin = AdminService::LoginInfo();
            if(empty($admin))
            {
                return DataReturn(MyLang('user_no_login_tips'), -1);
            }
        } else {
            $user = UserService::LoginUserInfo();
            if(empty($user))
            {
                return DataReturn(MyLang('user_no_login_tips'), -1);
            } else {
                $where[] = ['user_id', '=', $user['id']];
            }
        }
        $order = Db::name('Order')->where($where)->field('id,status,delivery_time')->find();
        if(empty($order))
        {
            return DataReturn('订单数据不存在', -1);
        }
        $express_list = OrderService::OrderExpressData($order['id']);
        if(empty($express_list) || empty($express_list[$order['id']]))
        {
            return DataReturn('快递数据不存在', -1);
        }
        $express_data = $express_list[$order['id']];

        // 数据处理
        foreach($express_data as $k=>&$v)
        {
            // 展示名称
            $v['show_name'] = '包裹'.($k+1).'('.$v['express_name'].')';

            // 编码
            $v['express_code'] = (!empty($config['express_codes']) && is_array($config['express_codes']) && !empty($config['express_codes'][$v['express_id']])) ? $config['express_codes'][$v['express_id']] : '';
        }

        // 默认查询第一条的物流数据
        $eid = (isset($params['eid']) && isset($express_data[$params['eid']])) ? $params['eid'] : 0;
        $express_data[$eid]['is_active'] = 1;

        // 收件人电话
        $order['tel'] = Db::name('OrderAddress')->where(['order_id'=>$order['id']])->value('tel');

        // 查询物流信息
        $express_info = ExpressQueryService::ExpressQuery($config, $order, $express_data[$eid]);

        // 返回固定格式数据
        return DataReturn('success', 0, [
            'order'         => $order,
            'express_data'  => $express_data,
            'express_info'  => $express_info['data'],
        ]);
    }

    /**
     * 快递数据 - 门店插件
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]           $config [插件配置]
     * @param   [array]           $params [输入参数]
     */
    public static function RealStoreExpressData($config, $params = [])
    {
        // 请求参数
        $p = [
            [
                'checked_type'      => 'empty',
                'key_name'          => 'oid',
                'error_msg'         => '订单id为空',
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 指定插件
        $action = empty($params['action_type']) ? '' :  $params['action_type'];
        if($action == 'realstore_erp')
        {
            $order_allo_table_name = 'PluginsErpRealstoreOrderAllot';
            $order_allo_express_table_name = 'PluginsErpRealstoreOrderAllotExpress';
        } else {
            $order_allo_table_name = 'PluginsRealstoreOrderAllot';
            $order_allo_express_table_name = 'PluginsRealstoreOrderAllotExpress';
        }

        // 获取订单数据
        $where = [
            ['id', '=', intval($params['oid'])],
            ['status', 'in', [3,4]],
            ['order_type', '=', 0],
        ];
        $order = Db::name($order_allo_table_name)->where($where)->find();
        if(empty($order))
        {
            return DataReturn('订单数据不存在', -1);
        }

        // 获取订单快递数据
        $express_data = Db::name($order_allo_express_table_name)->field('id,express_id,express_number,add_time')->where(['order_allot_id'=>$action == 'realstore_erp' ? $order['order_allot_id'] : $order['id']])->select()->toArray();
        if(empty($express_data))
        {
            return DataReturn('订单快递数据不存在', -1);
        }

        // 获取快递信息
        $express_list = ExpressService::ExpressData(array_unique(array_filter(array_column($express_data, 'express_id'))));
        if(empty($express_list))
        {
            return DataReturn('快递数据不存在', -1);
        }

        // 数据处理
        foreach($express_data as $k=>&$v)
        {
            // 快递信息处理
            $express = (!empty($express_list) && is_array($express_list) && array_key_exists($v['express_id'], $express_list)) ? $express_list[$v['express_id']] : null;
            if(empty($express))
            {
                $v['express_name'] = '';
                $v['express_icon'] = '';
                $v['express_website_url'] = '';
            } else {
                $v['express_name'] = $express['name'];
                $v['express_icon'] = $express['icon'];
                $v['express_website_url'] = $express['website_url'];
            }

            // 展示名称
            $v['show_name'] = '包裹'.($k+1).'('.$v['express_name'].')';

            // 编码
            $v['express_code'] = (!empty($config['express_codes']) && is_array($config['express_codes']) && !empty($config['express_codes'][$v['express_id']])) ? $config['express_codes'][$v['express_id']] : '';
        }

        // 默认查询第一条的物流数据
        $eid = (isset($params['eid']) && isset($express_data[$params['eid']])) ? $params['eid'] : 0;
        $express_data[$eid]['is_active'] = 1;

        // 收件人电话
        $order['tel'] = Db::name('PluginsRealstoreOrderAllotAddress')->where(['order_allot_id'=>$order['id']])->value('tel');

        // 查询物流信息
        $express_info = ExpressQueryService::ExpressQuery($config, $order, $express_data[$eid]);

        // 返回固定格式数据
        return DataReturn('success', 0, [
            'order'         => $order,
            'express_data'  => $express_data,
            'express_info'  => $express_info['data'],
        ]);
    }
}
?>