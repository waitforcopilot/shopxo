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
namespace app\plugins\ordergoodsform\api;

use app\plugins\ordergoodsform\api\Common;
use app\plugins\ordergoodsform\service\GoodsFormService;

/**
 * 订单商品表单
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2020-09-28
 * @desc    description
 */
class Goods extends Common
{
    /**
     * 数据保存
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-28
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Save($params = [])
    {
        $params['user_id'] = $this->user['id'];
        return GoodsFormService::GoodsFormDataSave($params);
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
        $params['user_id'] = $this->user['id'];
        return GoodsFormService::GoodsFromOrderDataList($params);
    }
}
?>