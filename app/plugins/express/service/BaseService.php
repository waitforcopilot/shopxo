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

use app\service\PluginsService;
use app\service\ExpressService;

/**
 * 物流查询 - 基础服务层
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2021-11-18
 * @desc    description
 */
class BaseService
{
    // 基础数据附件字段
    public static $base_config_attachment_field = [];

    // 基础私有字段
    public static $base_config_private_field = [
        // 菜鸟
        'cainiao_app_name',
        'cainiao_app_code',
        'cainiao_app_secret',
        // 快递100
        'kuaidi100_key',
        'kuaidi100_customer',
        // 快递鸟
        'kuaidiniao_userid',
        'kuaidiniao_apikey',
        'kuaidiniao_request_type',
        // 阿里云全国快递
        'aliyun_appcode',
    ];

    // 快递类型
    public static $base_express_type_list = [
        ['value'=>'cainiao', 'name'=>'菜鸟'],
        ['value'=>'kuaidi100', 'name'=>'快递100'],
        ['value'=>'kuaidiniao', 'name'=>'快递鸟'],
        ['value'=>'aliyun', 'name'=>'阿里云全国快递'],
    ];

    /**
     * 基础配置信息保存
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function BaseConfigSave($params = [])
    {
        return PluginsService::PluginsDataSave(['plugins'=>'express', 'data'=>$params], self::$base_config_attachment_field);
    }
    
    /**
     * 基础配置信息
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [boolean]          $is_cache [是否缓存中读取]
     */
    public static function BaseConfig($is_cache = true)
    {
        return PluginsService::PluginsData('express', self::$base_config_attachment_field, $is_cache);
    }

    /**
     * 快递列表
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public static function ExpressList($params = [])
    {
        return ExpressService::ExpressList(['is_enable'=>1]);
    }

    /**
     * 是否可以展示入口
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-20
     * @desc    description
     * @param   [array]          $config [插件配置信息]
     * @param   [array]          $params [输入参数]
     */
    public static function IsShowButton($config, $params = [])
    {
        // 仅已发货、已完成，销售模式展示
        if(isset($params['status']) && in_array($params['status'], [3,4]) && isset($params['order_model']) && $params['order_model'] == 0)
        {
            // 订单已完成、是否可以满足限定时
            if($params['status'] == 4 && isset($params['collect_time']))
            {
                // 时间格式则转成时间戳
                if(stripos($params['collect_time'], '-') !== false)
                {
                    $params['collect_time'] = strtotime($params['collect_time']);
                }
                $button_time = (empty($config['order_success_show_button_time']) ? 43200 : intval($config['order_success_show_button_time']))*60;
                if($params['collect_time']+$button_time < time())
                {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * 后台导航
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2022-12-06
     * @desc    description
     */
    public static function AdminNavMenuList()
    {
        return [
            [
                'name'      => '基础配置',
                'control'   => 'admin',
                'action'    => 'index',
            ],
            [
                'name'      => '请求日志',
                'control'   => 'log',
                'action'    => 'index',
            ],
        ];
    }
}
?>