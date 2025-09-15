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

use think\facade\Db;
use app\service\ResourcesService;
use app\service\OrderCurrencyService;
use app\service\GoodsService;
use app\plugins\ordergoodsform\service\BaseService;

/**
 * 订单商品表单 - 商品表单服务层
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2020-09-29
 * @desc    description
 */
class GoodsFormService
{
    /**
     * 数据列表
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-08-29
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function GoodsFormList($params = [])
    {
        $where = empty($params['where']) ? [] : $params['where'];
        $field = empty($params['field']) ? '*' : $params['field'];
        $m = isset($params['m']) ? intval($params['m']) : 0;
        $n = isset($params['n']) ? intval($params['n']) : 10;
        $order_by = empty($params['order_by']) ? 'id desc' : trim($params['order_by']);
        $data = Db::name('PluginsOrdergoodsform')->field($field)->where($where)->order($order_by)->limit($m, $n)->select()->toArray();

        return DataReturn(MyLang('handle_success'), 0, self::DataHandle($data));
    }

    /**
     * 数据处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-29
     * @desc    description
     * @param   [array]          $data [数据]
     */
    public static function DataHandle($data)
    {
        if(!empty($data))
        {
            foreach($data as &$v)
            {
                // 表单配置数据
                if(isset($v['config_data']))
                {
                    $v['config_data_arr'] = self::ConfigDataViewHandle($v['config_data']);
                }

                // 关联商品
                if(isset($v['goods_count']))
                {
                    $v['goods_list'] = [];
                    if($v['goods_count'] > 0)
                    {
                        $goods_ids = Db::name('PluginsOrdergoodsformGoods')->where(['form_id'=>$v['id']])->column('goods_id');
                        $goods = BaseService::GoodsList($goods_ids);
                        $v['goods_list'] = empty($goods['data']['goods']) ? [] : $goods['data']['goods'];
                    }
                }

                // 时间
                if(isset($v['add_time']))
                {
                    $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
                }
                if(isset($v['upd_time']))
                {
                    $v['upd_time'] = empty($v['upd_time']) ? '' : date('Y-m-d H:i:s', $v['upd_time']);
                }
            }
        }
        return $data;
    }

    /**
     * 配置数据展示处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2023-12-30
     * @desc    description
     * @param   [string|array]          $config [配置数据]
     */
    public static function ConfigDataViewHandle($config)
    {
        $arr = is_array($config) ? $config : json_decode($config, true);
        if(!empty($arr) && is_array($arr))
        {
            $document_type_list = BaseService::ConstData('document_type_list');
            foreach($arr as $ak=>&$av)
            {
                // 增加唯一ID
                if(empty($av['id']))
                {
                    $av['id'] = date('YmdHis').$ak.GetNumberCode(10);
                }

                // 元素类型
                $av['element_arr'] = empty($av['element']) ? '' : explode('/', $av['element']);
                if(!empty($av['element_arr']))
                {
                    if(isset($document_type_list[$av['element_arr'][0]]) && !empty($document_type_list[$av['element_arr'][0]]['pattern']))
                    {
                        $av['element_arr'][] = $document_type_list[$av['element_arr'][0]]['pattern'];
                    }
                }

                // 提示错误
                $av['error_message'] = '请输入'.$av['title'];
                if(!empty($av['minlength']) && !empty($av['maxlength']))
                {
                    if($av['minlength'] == $av['maxlength'])
                    {
                        $av['error_message'] = '请输入'.$av['title'].'、格式'.$av['minlength'];
                    } else {
                        $av['error_message'] = '请输入'.$av['title'].'、格式'.$av['minlength'].'~'.$av['maxlength'];
                    }
                } elseif(!empty($av['minlength']))
                {
                    $av['error_message'] = '请输入'.$av['title'].'、格式最低'.$av['minlength'];
                } elseif(!empty($av['maxlength']))
                {
                    $av['error_message'] = '请输入'.$av['title'].'、格式最大'.$av['maxlength'];
                }
                if(!empty($av['minlength']) || !empty($av['maxlength']))
                {
                    $av['error_message'] .= (!empty($av['element_arr']) && isset($av['element_arr'][0]) && $av['element_arr'][0] == 'number') ? '数值' : '位';
                }
            }
        }
        return $arr;
    }

    /**
     * 总数
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-29
     * @desc    description
     * @param   [array]          $where [条件]
     */
    public static function GoodsFormTotal($where = [])
    {
        return (int) Db::name('PluginsOrdergoodsform')->where($where)->count();
    }

    /**
     * 数据保存
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-12-19
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function GoodsFormSave($params = [])
    {
        // 请求参数
        $p = [
            [
                'checked_type'      => 'length',
                'key_name'          => 'title',
                'checked_data'      => '2,60',
                'error_msg'         => '名称长度 2~60 个字符',
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 表单配置
        $config_data = self::GoodsFormSaveRequestConfigHandle($params);

        // 关联商品
        $goods_ids = empty($params['goods_ids']) ? [] : explode(',', $params['goods_ids']);

        // 启动事务
        Db::startTrans();

        // 捕获异常
        try {
            // 主数据
            $data = [
                'title'         => $params['title'],
                'config_data'   => empty($config_data) ? '' : json_encode($config_data, JSON_UNESCAPED_UNICODE),
                'config_count'  => count($config_data),
                'goods_count'   => count($goods_ids),
                'is_enable'     => isset($params['is_enable']) ? intval($params['is_enable']) : 0,
            ];
            if(empty($params['id']))
            {
                $data['add_time'] = time();
                $form_id = Db::name('PluginsOrdergoodsform')->insertGetId($data);
                if($form_id <= 0)
                {
                    throw new \Exception('主数据添加失败');
                }
            } else {
                $data['upd_time'] = time();
                $form_id = intval($params['id']);
                if(!Db::name('PluginsOrdergoodsform')->where(['id'=>$form_id])->update($data))
                {
                    throw new \Exception('主数据编辑失败');
                }
            }

            // 关联商品
            // 先删除关联的商品,存在新数据则添加
            Db::name('PluginsOrdergoodsformGoods')->where(['form_id'=>$form_id])->delete();
            if(!empty($goods_ids))
            {
                $goods_ids_arr = [];
                foreach($goods_ids as $gid)
                {
                    $goods_ids_arr[] = [
                        'form_id'   => $form_id,
                        'goods_id'  => $gid,
                        'add_time'  => time(),
                    ];
                }
                $res = Db::name('PluginsOrdergoodsformGoods')->insertAll($goods_ids_arr);
                if($res < count($goods_ids_arr))
                {
                    throw new \Exception('关联商品数据添加失败');
                }
            }

            // 完成
            Db::commit();
            return DataReturn(MyLang('operate_success'), 0);
        } catch(\Exception $e) {
            Db::rollback();
            return DataReturn($e->getMessage(), -1);
        }
    }

    /**
     * 表单配置参数处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-07
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function GoodsFormSaveRequestConfigHandle($params)
    {
        $result = [];
        if(!empty($params) && is_array($params))
        {
            foreach($params as $k=>$v)
            {
                if(substr($k, 0, 11) == 'popup_name_')
                {
                    $result[] = json_decode(urldecode($v), true);
                }
            }
        }
        return $result;
    }

    /**
     * 删除
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-12-18
     * @desc    description
     * @param   [array]          $params [输入参数]
     */
    public static function GoodsFormDelete($params = [])
    {
        // 参数是否有误
        if(empty($params['ids']))
        {
            return DataReturn(MyLang('data_id_error_tips'), -1);
        }
        // 是否数组
        if(!is_array($params['ids']))
        {
            $params['ids'] = explode(',', $params['ids']);
        }

        // 启动事务
        Db::startTrans();

        // 捕获异常
        try {
            // 删除操作
            if(Db::name('PluginsOrdergoodsform')->where(['id'=>$params['ids']])->delete() === false)
            {
                throw new \Exception('删除失败');
            }

            if(Db::name('PluginsOrdergoodsformGoods')->where(['form_id'=>$params['ids']])->delete() === false)
            {
                throw new \Exception('关联商品删除失败');
            }

            // 完成
            Db::commit();
            return DataReturn(MyLang('delete_success'), 0);
        } catch(\Exception $e) {
            Db::rollback();
            return DataReturn($e->getMessage(), -1);
        }
    }

    /**
     * 状态更新
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  0.0.1
     * @datetime 2016-12-06T21:31:53+0800
     * @param    [array]          $params [输入参数]
     */
    public static function GoodsFormStatusUpdate($params = [])
    {
        // 请求参数
        $p = [
            [
                'checked_type'      => 'empty',
                'key_name'          => 'id',
                'error_msg'         => MyLang('data_id_error_tips'),
            ],
            [
                'checked_type'      => 'empty',
                'key_name'          => 'field',
                'error_msg'         => MyLang('operate_field_error_tips'),
            ],
            [
                'checked_type'      => 'in',
                'key_name'          => 'state',
                'checked_data'      => [0,1],
                'error_msg'         => MyLang('form_status_range_message'),
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 数据更新
        if(Db::name('PluginsOrdergoodsform')->where(['id'=>intval($params['id'])])->update([$params['field']=>intval($params['state']), 'upd_time'=>time()]))
        {
           return DataReturn(MyLang('edit_success'), 0);
        }
        return DataReturn(MyLang('edit_fail'), -100);
    }

    /**
     * 商品表单唯一key生成
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-12
     * @desc    description
     * @param   [int]          $form_id  [表单id]
     * @param   [int]          $goods_id [商品id]
     * @param   [string]       $name     [表单名称]
     */
    public static function GoodsFormMd5KeyCreated($form_id, $goods_id, $name)
    {
        return md5($form_id.$goods_id.$name);
    }

    /**
     * 商品数据保存
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-12
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public static function GoodsFormDataSave($params = [])
    {
        // 请求参数
        $p = [
            [
                'checked_type'      => 'empty',
                'key_name'          => 'form_id',
                'error_msg'         => '表单id为空',
            ],
            [
                'checked_type'      => 'empty',
                'key_name'          => 'goods_id',
                'error_msg'         => '商品id为空',
            ],
            [
                'checked_type'      => 'empty',
                'key_name'          => 'title',
                'error_msg'         => '数据名称为空',
            ],
            [
                'checked_type'      => 'empty',
                'key_name'          => 'user_id',
                'error_msg'         => MyLang('user_info_incorrect_tips'),
            ],
            [
                'checked_type'      => 'isset',
                'key_name'          => 'content',
                'error_msg'         => '数据值为空',
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 捕获异常
        try {
            // 数据
            $data = [
                'form_id'       => intval($params['form_id']),
                'goods_id'      => intval($params['goods_id']),
                'user_id'       => intval($params['user_id']),
                'title'         => $params['title'],
                'content'       => $params['content'],
            ];

            // 唯一key
            $data['md5_key'] = self::GoodsFormMd5KeyCreated($data['form_id'], $data['goods_id'], $data['title']);

            // 使用唯一key查询是否存在
            $where = ['md5_key'=>$data['md5_key'], 'user_id'=>$data['user_id']];
            $temp = Db::name('PluginsOrdergoodsformGoodsData')->where($where)->find();
            if(empty($temp))
            {
                $data['add_time'] = time();
                if(Db::name('PluginsOrdergoodsformGoodsData')->insertGetId($data) <= 0)
                {
                    throw new \Exception(MyLang('insert_fail'));
                }
            } else {
                $data['upd_time'] = time();
                if(Db::name('PluginsOrdergoodsformGoodsData')->where($where)->update($data) === false)
                {
                    throw new \Exception('编辑失败');
                }
            }

            return DataReturn(MyLang('operate_success'), 0);
        } catch(\Exception $e) {
            return DataReturn($e->getMessage(), -1);
        }
    }

    /**
     * 商品详情表单数据
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-08
     * @desc    description
     * @param   [int]           $goods_id [商品id]
     * @param   [int]           $user_id  [用户id]
     */
    public static function GoodsDetailNavTopForm($goods_id, $user_id = 0)
    {
        // 已绑定商品的表单、没有则读取全部的，则全部和绑定的表单
        $form_ids = Db::name('PluginsOrdergoodsformGoods')->where(['goods_id'=>$goods_id])->column('form_id');
        $where = [
            ['config_count', '>', 0],
            ['is_enable', '=', 1],
        ];
        if(empty($form_ids))
        {
            $where[] = ['goods_count', '=', 0];
            $data = Db::name('PluginsOrdergoodsform')->where($where)->select()->toArray();
        } else {

            $where1 = [
                ['id', 'in', $form_ids],
                ['goods_count', '>', 0],
            ];
            $where2 = [
                ['goods_count', '=', 0],
            ];
            $gg = [$where1, $where2];
            $data = Db::name('PluginsOrdergoodsform')->where($where)->where(function($query) use($gg) {
                $query->whereOr($gg);
            })->select()->toArray();
        }
        $result = [];
        if(!empty($data))
        {
            // 配置数据处理
            $md5_keys = [];
            foreach($data as $v)
            {
                if(!empty($v['config_data']))
                {
                    foreach(self::ConfigDataViewHandle($v['config_data']) as $vs)
                    {
                        $md5_key = self::GoodsFormMd5KeyCreated($v['id'], $goods_id, $vs['title']);
                        $vs['form_id'] = $v['id'];
                        $vs['md5_key'] = $md5_key;
                        $md5_keys[] = $md5_key;
                        $result[] = $vs;
                    }
                }
            }

            // 数据值获取
            if(!empty($result) && !empty($md5_keys) && !empty($user_id))
            {
                $md5_values = Db::name('PluginsOrdergoodsformGoodsData')->where(['md5_key'=>$md5_keys, 'user_id'=>$user_id])->column('content', 'md5_key');
                foreach($result as $k=>$v)
                {
                    if(array_key_exists($v['md5_key'], $md5_values))
                    {
                        $result[$k]['default_value'] = $md5_values[$v['md5_key']];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 订单数据添加
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-14
     * @desc    description
     * @param   [int]          $order_id  [订单id]
     * @param   [array]        $goods_ids [订单商品id]
     * @param   [int]          $user_id   [用户id]
     */
    public static function BuyOrderGoodsFormInsert($order_id, $goods_ids, $user_id)
    {
        if(!empty($order_id) && !empty($goods_ids) && is_array($goods_ids))
        {
            foreach($goods_ids as $goods_id)
            {
                if(!empty($goods_id))
                {
                    $form_data = self::GoodsDetailNavTopForm($goods_id, $user_id);
                    if(!empty($form_data))
                    {
                        foreach($form_data as $v)
                        {
                            $where = [
                                'order_id'  => $order_id,
                                'goods_id'  => $goods_id,
                                'md5_key'   => $v['md5_key'],
                            ];
                            $count = Db::name('PluginsOrdergoodsformOrderData')->where($where)->count();
                            if(empty($count))
                            {
                                $data = [
                                    'order_id'  => $order_id,
                                    'goods_id'  => $goods_id,
                                    'user_id'   => $user_id,
                                    'form_id'   => $v['form_id'],
                                    'md5_key'   => $v['md5_key'],
                                    'title'     => $v['title'],
                                    'content'   => $v['default_value'],
                                    'add_time'  => time(),
                                ];
                                if(Db::name('PluginsOrdergoodsformOrderData')->insertGetId($data) <= 0)
                                {
                                    return DataReturn('订单商品表单添加失败', -1);
                                }
                            }
                        }
                    }
                }
            }
        }
        return DataReturn('success', 0);
    }

    /**
     * 订单数据列表
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-11-12
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public static function GoodsFromOrderDataList($params = [])
    {
        $order_id = intval($params['id']);
        $where = ['order_id' => $order_id];
        if(!empty($params['user_id']))
        {
            $where['user_id'] = intval($params['user_id']);
        }
        $data = Db::name('OrderDetail')->where($where)->column('order_id,title,goods_id,images,price,spec,buy_number', 'goods_id');
        if(!empty($data))
        {
            // 查询商品订单表单
            $goods_ids = array_unique(array_column($data, 'goods_id'));
            $form_data = Db::name('PluginsOrdergoodsformOrderData')->where(['goods_id'=>$goods_ids, 'order_id'=>$order_id])->select()->toArray();
            $form_data_group = [];
            if(!empty($form_data))
            {
                foreach($form_data as $f)
                {
                    $form_data_group[$f['goods_id']][] = $f;
                }
            }

            // 默认货币
            $currency_default = ResourcesService::CurrencyData();

            // 订单货币
            $currency_data = OrderCurrencyService::OrderCurrencyGroupList(array_column($data, 'order_id'));

            // 数据集合
            foreach($data as &$v)
            {
                // 商品地址
                $v['goods_url'] = GoodsService::GoodsUrlCreate($v['goods_id']);

                // 规格
                $v['spec_text'] = null;
                if(!empty($v['spec']))
                {
                    $v['spec'] = json_decode($v['spec'], true);
                    if(!empty($v['spec']) && is_array($v['spec']))
                    {
                        $v['spec_text'] = implode('，', array_map(function($spec)
                        {
                            return $spec['type'].':'.$spec['value'];
                        }, $v['spec']));
                    }
                }

                // 图片
                $v['images'] = ResourcesService::AttachmentPathViewHandle($v['images']);

                // 货币
                $v['currency_data'] = array_key_exists($v['order_id'], $currency_data) ? $currency_data[$v['order_id']] : $currency_default;

                // 表单信息
                $v['form_data'] = isset($form_data_group[$v['goods_id']]) ? $form_data_group[$v['goods_id']] : [];
            }
            $data = array_values($data);
        }

        return DataReturn('success', 0, $data);
    }
}
?>