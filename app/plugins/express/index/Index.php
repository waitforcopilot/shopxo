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
namespace app\plugins\express\index;

use app\plugins\express\index\Common;
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
        MyViewAssign('is_header', 0);
        MyViewAssign('is_footer', 0);
        return MyView('../../../plugins/express/view/index/index/index');
    }
}
?>