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
namespace app\plugins\express\admin;

use app\plugins\express\admin\Common;
use app\plugins\express\service\ExpressHandleService;

/**
 * 物流查询 - 调试
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2021-11-18
 * @desc    description
 */
class Debug extends Common
{
    /**
     * 菜鸟调试页面
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function Cainiao($params = [])
    {
        return MyView('../../../plugins/express/view/admin/debug/cainiao');
    }

    /**
     * 菜鸟调试
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-20
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function CainiaoDebug($params = [])
    {
        // 业务参数
        $content = json_encode(['arg0'=>[
            // 应用code
            'appCode'   => $params['name'],
            // 快递单号
            'mailNo'    => $params['express_number'],
            // 快递代码
            'cpCode'    => $params['express_code'],
        ]], JSON_UNESCAPED_UNICODE);
        $request_params = [
            // 接口标识
            'msg_type'              => 'CNTECH_LV_LOGISTICS_DETAIL_GET',
            // 资源code
            'logistic_provider_id'  => $params['code'],
            // CNTECH_LV
            'to_code'               => 'CNTECH_LV',
            'logistics_interface'   => $content,
        ];

        // 签名
        $request_params['data_digest'] = base64_encode(md5($content.$params['secret'], true));

        // 请求
        $res = CurlPost($params['url'], $request_params);
        if($res['code'] == 0)
        {
            if(!empty($res['data']))
            {
                // 数据处理
                if(!is_array($res['data']))
                {
                    $res['data'] = json_decode($res['data'], true);
                }

                // 存在快递数据
                if(!empty($res['data']['fullTraceDetail']))
                {
                    $res['msg'] = '联调成功';
                } else {
                    // 无数据则获取错误信息
                    if(!empty($res['data']['errorMsg']))
                    {
                        $res['msg'] = $res['data']['errorMsg'];
                    }
                    $res['code'] = -1;
                }
            } else {
                $res['code'] = -1;
            }
            // 状态错误、提示信息处理
            if($res['code'] == -1 && $res['msg'] == 'success')
            {
                $res['msg'] = '联调失败、请确认是否执行回传了';
            }
        }
        return $res;
    }
}
?>