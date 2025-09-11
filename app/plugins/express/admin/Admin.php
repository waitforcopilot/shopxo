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
use app\plugins\express\service\BaseService;

/**
 * 物流查询 - 管理
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2021-11-18
 * @desc    description
 */
class Admin extends Common
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
        MyViewAssign('express_type_list', array_column(BaseService::$base_express_type_list, 'name', 'value'));
        MyViewAssign('express_list', BaseService::ExpressList());
        MyViewAssign('data', $this->plugins_config);
        return MyView('../../../plugins/express/view/admin/admin/index');
    }

   /**
     * 编辑页面
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function SaveInfo($params = [])
    {
        MyViewAssign('express_type_list', BaseService::$base_express_type_list);
        MyViewAssign('express_list', BaseService::ExpressList());
        MyViewAssign('data', $this->plugins_config);
        return MyView('../../../plugins/express/view/admin/admin/saveinfo');
    }

    /**
     * 数据保存
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-18
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public function Save($params = [])
    {
        return BaseService::BaseConfigSave($params);
    }
}
?>