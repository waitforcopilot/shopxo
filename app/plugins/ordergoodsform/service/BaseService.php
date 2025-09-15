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
namespace app\plugins\ordergoodsform\service;

use app\service\PluginsService;
use app\service\GoodsService;
use app\service\GoodsCategoryService;

/**
 * 订单商品表单 - 基础服务层
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2020-09-04
 * @desc    description
 */
class BaseService
{
    // 基础数据附件字段
    public static $base_config_attachment_field = [];

    /**
     * 基础配置信息保存
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-12-24
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function BaseConfigSave($params = [])
    {
        return PluginsService::PluginsDataSave(['plugins'=>'ordergoodsform', 'data'=>$params]);
    }
    
    /**
     * 基础配置信息
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-12-24
     * @desc    description
     * 
     * @param   [boolean]          $is_cache [是否缓存中读取]
     */
    public static function BaseConfig($is_cache = true)
    {
        return PluginsService::PluginsData('ordergoodsform', self::$base_config_attachment_field, $is_cache);
    }

    /**
     * 静态数据
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2023-12-30
     * @desc    description
     * @param   [string]          $key [数据key]
     */
    public static function ConstData($key)
    {
        $data = [
            // 元素类型
            'document_type_list'    => [
                'text'    => ['type'=>'text', 'name'=>'文本', 'checked'=>true],
                'number'  => ['type'=>'number', 'name'=>'整数'],
                'mobile'  => ['type'=>'text', 'name'=>'手机', 'pattern'=>MyConst('common_regex_mobile')],
                'email'   => ['type'=>'text', 'name'=>'邮箱', 'pattern'=>MyConst('common_regex_email')],
            ],
        ];
        return array_key_exists($key, $data) ? $data[$key] : [];
    }

    /**
     * 商品搜索
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-29
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function GoodsSearchList($params = [])
    {
        // 条件
        $where = [
            ['g.is_delete_time', '=', 0],
            ['g.is_shelves', '=', 1]
        ];

        // 关键字
        if(!empty($params['keywords']))
        {
            $where[] = ['g.title', 'like', '%'.$params['keywords'].'%'];
        }

        // 分类id
        if(!empty($params['category_id']))
        {
            $category_ids = GoodsCategoryService::GoodsCategoryItemsIds([$params['category_id']], 1);
            $category_ids[] = $params['category_id'];
            $where[] = ['gci.category_id', 'in', $category_ids];
        }

        // 指定字段
        $field = 'g.id,g.title';

        // 获取数据
        return GoodsService::CategoryGoodsList(['where'=>$where, 'm'=>0, 'n'=>100, 'field'=>$field, 'is_admin_access'=>1]);
    }

    /**
     * 商品列表
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-29
     * @desc    description
     * @param   [array]         $params [商品id]
     * @param   [int]           $m      [分页起始值]
     * @param   [int]           $n      [分页数量]
     */
    public static function GoodsList($goods_ids = [], $m = 0, $n = 0)
    {
        // 获取推荐商品id
        if(empty($goods_ids))
        {
            return DataReturn('没有商品id', 0, ['goods'=>[], 'goods_ids'=>[]]);
        }
        if(!is_array($goods_ids))
        {
            $goods_ids = json_decode($goods_ids, true);
        }

        // 条件
        $where = [
            ['g.is_delete_time', '=', 0],
            ['g.is_shelves', '=', 1],
            ['g.id', 'in', $goods_ids],
        ];

        // 指定字段
        $field = 'g.id,g.title,g.images,g.min_price,g.price,g.original_price';

        // 获取数据
        $ret = GoodsService::CategoryGoodsList(['where'=>$where, 'm'=>$m, 'n'=>$n, 'field'=>$field]);
        return DataReturn(MyLang('operate_success'), 0, ['goods'=>$ret['data'], 'goods_ids'=>$goods_ids]);
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
                'name'      => '表单管理',
                'control'   => 'goodsform',
                'action'    => 'index',
            ],
        ];
    }
}
?>