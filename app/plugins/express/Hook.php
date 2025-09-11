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
namespace app\plugins\express;

use app\plugins\express\service\BaseService;
use app\plugins\express\service\ExpressHandleService;

/**
 * 物流查询 - 钩子入口
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2021-11-18
 * @desc    description
 */
class Hook
{
    // 模块、控制器、方法
    private $module_name;
    private $controller_name;
    private $action_name;
    private $mca;

    // 配置信息
    private $plugins_config;

    /**
     * 应用响应入口
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function handle($params = [])
    {
        $ret = '';
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

            switch($params['hook_name'])
            {
                case 'plugins_css' :
                    if(isset($this->plugins_config['is_user_web_order']) && $this->plugins_config['is_user_web_order'] == 1 && $this->mca == 'indexorderdetail')
                    {
                        $ret = 'static/plugins/express/css/index/style.css';
                    }
                    break;

                // 操作按钮 - 后台
                case 'plugins_view_admin_order_list_operate' :
                    if(isset($this->plugins_config['is_admin_order']) && $this->plugins_config['is_admin_order'] == 1)
                    {
                        $ret = $this->OperateButton($params);
                    }
                    break;

                // 操作按钮 - 前端
                case 'plugins_view_index_order_list_operate' :
                case 'plugins_view_index_order_detail_operate' :
                    if(isset($this->plugins_config['is_user_web_order']) && $this->plugins_config['is_user_web_order'] == 1)
                    {
                        $ret = $this->OperateButton($params);
                    }
                    break;

                // 订单详情操作顶部 - 前端
                case 'plugins_view_index_order_detail_operate_top' :
                    if(isset($this->plugins_config['is_user_web_order']) && $this->plugins_config['is_user_web_order'] == 1)
                    {
                        $ret = $this->OperateTop($params);
                    }
                    break;

                // 订单列表接口数据 - 手机
                case 'plugins_service_base_data_return_api_order_index' :
                    if(isset($this->plugins_config['is_user_app_order']) && $this->plugins_config['is_user_app_order'] == 1)
                    {
                        if(!empty($params['data']['data']))
                        {
                            $params['data']['data'] = $this->OrderResultHandle($params['data']['data']);
                        }
                    }
                    break;

                // 订单详情接口数据 - 手机
                case 'plugins_service_base_data_return_api_order_detail' :
                    if(isset($this->plugins_config['is_user_app_order']) && $this->plugins_config['is_user_app_order'] == 1)
                    {
                        if(!empty($params['data']['data']))
                        {
                            $res = $this->OrderResultHandle([$params['data']['data']]);
                            $params['data']['data'] = $res[0];
                        }
                    }
                    break;
            }
        }
        return $ret;
    }

    /**
     * 订单数据处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $data [订单数据]
     */
    public function OrderResultHandle($data = [])
    {
        foreach($data as &$v)
        {
            if(BaseService::IsShowButton($this->plugins_config, $v))
            {
                $v['plugins_express_data'] = 1;
            }
        }
        return $data;
    }

    /**
     * 操作顶部
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function OperateTop($params = [])
    {
        // 是否可以展示物流信息
        if(!empty($params['data']) && !empty($params['data']['express_data']) && BaseService::IsShowButton($this->plugins_config, $params['data']))
        {
            // 查询物流信息
            $ret = ExpressHandleService::ExpressRun($this->plugins_config, ['oid'=>$params['data']['id']]);
            $express_data = ($ret['code'] == 0 && !empty($ret['data']) && !empty($ret['data']['express_info']) && !empty($ret['data']['express_info']['data']) && !empty($ret['data']['express_info']['data'][0]) && isset($ret['data']['express_info']['data'][0]['time']) && isset($ret['data']['express_info']['data'][0]['desc'])) ? $ret['data']['express_info']['data'][0] : null;
            return MyView('../../../plugins/express/view/index/public/order_detail_operate_top', ['order_data'=>$params['data'], 'express_data'=>$express_data]);
        }
    }

    /**
     * 操作按钮
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function OperateButton($params = [])
    {
        // 是否可以展示入口
        if(!empty($params['data']) && !empty($params['data']['express_data']) && BaseService::IsShowButton($this->plugins_config, $params['data']))
        {
            // 请求url地址
            if($this->module_name == 'admin')
            {
                return '<button type="button" class="am-btn am-btn-primary am-btn-xs am-radius am-btn-block submit-popup" data-url="'.PluginsAdminUrl('express', 'index', 'index', ['oid'=>$params['data']['id']]).'" data-title="物流信息"><i class="am-icon-cube"></i> <span>物流</span></button>';
            } else {
                $url = PluginsHomeUrl('express', 'index', 'index', ['oid'=>$params['data']['id']]);
                if($params['hook_name'] == 'plugins_view_index_order_detail_operate')
                {
                    return '<button type="button" class="am-btn am-btn-secondary am-btn-xs am-radius submit-popup" data-url="'.$url.'" data-title="物流信息">查看物流</button>';
                } else {
                    return '<button type="button" class="am-btn am-btn-primary-plain am-btn-xs am-radius am-btn-block submit-popup" data-url="'.$url.'" data-title="物流信息"><i class="am-icon-cube"></i> <span>物流</span></button>';
                }
            }
        }
        return '';
    }
}
?>