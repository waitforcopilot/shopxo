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
 * 物流查询 - 首页
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2021-11-18
 * @desc    description
 */
class Index extends Common
{
    /**
     * 首页
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function Index($params = [])
    {
        // 获取快递数据
        $data = ExpressHandleService::ExpressRun($this->plugins_config, $params);
        MyViewAssign('data', $data);
        return MyView('../../../plugins/express/view/admin/index/index');
    }

    /**
     * 查看所有快递编码
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-20
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Codes($params = [])
    {
        $key = 'plugins_express_cpcode_data';
        $data = MyCache($key);
        if(empty($data))
        {
            $res = CurlPost('http://detail.i56.taobao.com/xml/cpcode_detail_list.xml', []);
            if(!empty($res['data']))
            {
                $temp = json_decode(json_encode(simplexml_load_string($res['data'], 'SimpleXMLElement', LIBXML_NOCDATA)), true);
                $data = (empty($temp) || empty($temp['item'])) ? [] : $temp['item'];
                MyCache($key, $data, 24*15*60);
            }
        }
        MyViewAssign('data', $data);
        return MyView('../../../plugins/express/view/admin/index/codes');
    }
}
?>