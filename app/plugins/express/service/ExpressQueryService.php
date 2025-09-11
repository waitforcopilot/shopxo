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

/**
 * 物流查询 - 快递处理服务层
 * @author  Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2021-11-18
 * @desc    description
 */
class ExpressQueryService
{
    /**
     * 快递查询
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $config           [插件配置]
     * @param   [array]           $order            [订单数据]
     * @param   [array]           $express          [快递数据]
     */
    public static function ExpressQuery($config, $order, $express)
    {
        // 获取日志记录
        $where = [
            ['express_number', '=', $express['express_number']],
            ['express_code', '=', $express['express_code']],
        ];
        $info = Db::name('PluginsExpressLog')->where($where)->field('id,response_data,add_time')->order('id desc')->find();

        // 是否存在数据、是否已签收
        $data = [];
        if(!empty($info) && !empty($info['response_data']))
        {
            $temp = json_decode($info['response_data'], true);
            if(!empty($temp['data']) && is_array($temp['data']))
            {
                $data = $temp['data'];
            }
        }

        // 数据完成检测关键字
        $is_success = false;
        if(!empty($data) && !empty($data[0]) && !empty($data[0]['desc']) && !empty($config['success_check_keywords']))
        {
            $arr = explode(',', $config['success_check_keywords']);
            foreach($arr as $kd)
            {
                if(stripos($data[0]['desc'], $kd))
                {
                    $is_success = true;
                    break;
                }
            }
        }

        // 返回数据格式
        // data => [['time'=>'时间', 'desc'=>'描述']]
        $result = [
            'name'         => $express['express_name'],
            'code'         => $express['express_code'],
            'icon'         => $express['express_icon'],
            'website_url'  => $express['express_website_url'],
            'number'       => $express['express_number'],
            'note'         => empty($express['note']) ? '' : $express['note'],
            'msg'          => empty($config['default_msg']) ? '' : $config['default_msg'],
            'data'         => $data,
        ];

        // 已完成则不再查询数据
        if(!$is_success)
        {
            // 不存在记录
            if(empty($info))
            {
                // 订单未完成 获取数据
                // 订单完成（未设置首次是否需要请求数据）
                if($order['status'] == 3 || ($order['status'] == 4 && (!isset($config['is_order_success_first_not_request']) || $config['is_order_success_first_not_request'] != 1)))
                {
                    $ret = self::RequestData($config, $order, $express);
                    if($ret['code'] == 0)
                    {
                        $result['data'] = $ret['data'];
                    }
                }
            } else {
                // 间隔时间小于当前时间、并且订单未完成 或 已完成（未开启完成不请求数据）
                $request_interval_time = (empty($config['request_interval_time']) ? 300 : intval($config['request_interval_time']))*60;
                if($request_interval_time+$info['add_time'] < time() && ($order['status'] == 3 || ($order['status'] == 4 && (!isset($config['is_order_success_not_request']) || $config['is_order_success_not_request'] != 1))))
                {
                    $ret = self::RequestData($config, $order, $express);
                    if($ret['code'] == 0)
                    {
                        $result['data'] = $ret['data'];
                    }
                }
            }
        }

        // 数据为空则使用提示信息
        if(empty($result['data']))
        {
            if(!is_numeric($express['add_time']))
            {
                $express['add_time'] = strtotime($express['add_time']);
            }
            // 提示信息处理、前置后置
            $first_max_time = (empty($config['first_max_time']) ? 1440 : intval($config['first_max_time']))*60;
            if($express['add_time']+$first_max_time > time())
            {
                if(!empty($config['first_msg']))
                {
                    $result['msg'] = $config['first_msg'];
                }
            } else {
                if(!empty($config['last_msg']))
                {
                    $result['msg'] = $config['last_msg'];
                }
            }
        } else {
            // 存在数据提示信息
            if(!empty($config['exist_msg']))
            {
                $result['msg'] = $config['exist_msg'];
            }
            // 物流数据格式是否正确
            if(!is_array($result['data']))
            {
                $result['msg'] = '物流数据格式有误['.$ret['data'].']';
                $result['data'] = [];
            }
        }

        // 订单完成不再展示占位信息
        if($order['status'] == 4 && isset($config['is_order_success_not_show_msg']) && $config['is_order_success_not_show_msg'] == 1)
        {
            $result['msg'] = '';
        }
        return DataReturn('success', 0, $result);
    }

    /**
     * 远程请求数据
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $config           [插件配置]
     * @param   [array]           $order            [订单数据]
     * @param   [array]           $express          [快递数据]
     */
    public static function RequestData($config, $order, $express)
    {
        // 快递类型
        if(empty($config['express_type']))
        {
            return DataReturn('请先设置快递类型', -1);
        }

        // 根据快递类型调用不同平台接口
        switch($config['express_type'])
        {
            // 菜鸟
            case 'cainiao' :
                $res = self::CainiaoQuery($config, $order, $express);
                break;

            // 快递100
            case 'kuaidi100' :
                $res = self::Kuaidi100Query($config, $order, $express);
                break;

            // 快递鸟
            case 'kuaidiniao' :
                $res = self::KuaidiniaoQuery($config, $order, $express);
                break;

            // 阿里云全国快递
            case 'aliyun' :
                $res = self::AliyunQuery($config, $order, $express);
                break;

            default :
                return DataReturn('快递类型未定义['.$config['express_type'].']', -1);
        }

        // 数据添加
        $insert_data = [
            'express_type'      => $config['express_type'],
            'express_name'      => $express['express_name'],
            'express_number'    => $express['express_number'],
            'express_code'      => $express['express_code'],
            'request_params'    => json_encode($res['request_params'], JSON_UNESCAPED_UNICODE),
            'response_data'     => json_encode($res['result_data'], JSON_UNESCAPED_UNICODE),
            'add_time'          => time(),
        ];
        if(Db::name('PluginsExpressLog')->insertGetId($insert_data) < 0)
        {
            return DataReturn('日志数据添加失败', -1);
        }
        return DataReturn('success', 0, $res['result_data']['data']);
    }

    /**
     * 菜鸟数据查询
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $config           [插件配置]
     * @param   [array]           $order            [订单数据]
     * @param   [array]           $express          [快递数据]
     */
    public static function CainiaoQuery($config, $order, $express)
    {
        // 业务参数
        $content = json_encode(['arg0'=>[
            // 应用code
            'appCode'       => $config['cainiao_app_name'],
            // 快递单号
            'mailNo'        => $express['express_number'],
            // 快递代码
            'cpCode'        => $express['express_code'],
            // 收件人手机
            'receiverPhone' => $order['tel'],
        ]], JSON_UNESCAPED_UNICODE);
        $request_params = [
            // 接口标识
            'msg_type'              => 'CNTECH_LV_LOGISTICS_DETAIL_GET',
            // 资源code
            'logistic_provider_id'  => $config['cainiao_app_code'],
            // CNTECH_LV
            'to_code'               => 'CNTECH_LV',
            'logistics_interface'   => $content,
        ];

        // 签名
        $request_params['data_digest'] = base64_encode(md5($content.$config['cainiao_app_secret'], true));

        // 请求
        $url = 'http://link.cainiao.com/gateway/link.do';
        $res = CurlPost($url, $request_params);
        if($res['code'] == 0 && !empty($res['data']))
        {
            // 数据处理
            $response = is_array($res['data']) ? $res['data'] : json_decode($res['data'], true);
            if(!empty($response) && is_array($response))
            {
                // 存在快递数据
                $data = [];
                if(!empty($response['result']) && !empty($response['result']['data']))
                {
                    if(!is_array($response['result']['data']))
                    {
                        $response['result']['data'] = json_decode($response['result']['data'], true);
                    }
                    if(!empty($response['result']['data']['detail']))
                    {
                        foreach($response['result']['data']['detail'] as $k=>$v)
                        {
                            $desc = empty($v['opMessage']) ? '' : $v['opMessage'];
                            if(count($response['result']['data']['detail'])-1 == $k)
                            {
                                $desc = '【'.$v['opDesc'].'】'.$desc;
                            }
                            $data[] = [
                                'time'  => empty($v['opTime']) ? '' : $v['opTime'],
                                'desc'  => $desc,
                            ];
                        }
                    }
                } else {
                    // 无数据则获取错误信息
                    if(!empty($response['errorMsg']))
                    {
                        $res['msg'] = $response['errorMsg'];
                    }
                }
                $res['data'] = array_reverse($data);
            }
        }
        return [
            'result_data'       => $res,
            'request_params'    => $request_params,
        ];
    }

    /**
     * 快递100数据查询
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $config           [插件配置]
     * @param   [array]           $order            [订单数据]
     * @param   [array]           $express          [快递数据]
     */
    public static function Kuaidi100Query($config, $order, $express)
    {
        // 业务参数
        $content = [
            // 快递单号
            'num'    => $express['express_number'],
            // 快递代码
            'com'    => $express['express_code'],
        ];
        // 顺丰快递则读取收件人联系电话
        if(!empty($order['tel']) && in_array(strtolower($express['express_code']), ['sf', 'shunfeng']) || stripos($express['express_name'], '顺丰'))
        {
            $content['phone'] = $order['tel'];
        }
        $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        $request_params = [
            'customer'  => $config['kuaidi100_customer'],
            'param'     => $content,
        ];

        // 签名
        $request_params['sign'] = strtoupper(md5($content.$config['kuaidi100_key'].$config['kuaidi100_customer']));

        // 请求
        $url = 'http://poll.kuaidi100.com/poll/query.do';
        $res = CurlPost($url, $request_params);
        if($res['code'] == 0 && !empty($res['data']))
        {
            // 数据处理
            if(!is_array($res['data']))
            {
                $res['data'] = json_decode($res['data'], true);
            }

            // 存在快递数据
            $data = [];
            if(!empty($res['data']['data']))
            {
                foreach($res['data']['data'] as $v)
                {
                    $data[] = [
                        'time'  => empty($v['time']) ? '' : $v['time'],
                        'desc'  => empty($v['context']) ? '' : $v['context'],
                    ];
                }
            } else {
                // 无数据则获取错误信息
                if(!empty($res['data']['message']))
                {
                    $res['msg'] = $res['data']['message'];
                }
            }
            $res['data'] = $data;
        }
        return [
            'result_data'       => $res,
            'request_params'    => $request_params,
        ];
    }

    /**
     * 快递鸟数据查询
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $config           [插件配置]
     * @param   [array]           $order            [订单数据]
     * @param   [array]           $express          [快递数据]
     */
    public static function KuaidiniaoQuery($config, $order, $express)
    {
        // 业务参数
        $content = [
            // 快递单号
            'LogisticCode'  => $express['express_number'],
            // 快递代码
            'ShipperCode'   => $express['express_code'],
        ];
        $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        $request_params = [
            'EBusinessID'   => $config['kuaidiniao_userid'],
            'RequestType'   => empty($config['kuaidiniao_request_type']) ? 1002 : $config['kuaidiniao_request_type'],
            'DataType'      => '2',
            'RequestData'   => $content,
        ];

        // 签名
        $request_params['DataSign'] = urlencode(base64_encode(md5($content.$config['kuaidiniao_apikey'])));

        // 请求
        $url = 'https://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx';
        $res = CurlPost($url, $request_params);
        if($res['code'] == 0 && !empty($res['data']))
        {
            // 数据处理
            if(!is_array($res['data']))
            {
                $res['data'] = json_decode($res['data'], true);
            }

            // 存在快递数据
            $data = [];
            if(!empty($res['data']['Traces']))
            {
                foreach($res['data']['Traces'] as $v)
                {
                    $data[] = [
                        'time'  => empty($v['AcceptTime']) ? '' : date('Y-m-d H:i:s', strtotime($v['AcceptTime'])),
                        'desc'  => empty($v['AcceptStation']) ? '' : $v['AcceptStation'],
                    ];
                }
            } else {
                // 无数据则获取错误信息
                if(!empty($res['data']['Reason']))
                {
                    $res['msg'] = $res['data']['Reason'];
                }
            }
            $res['data'] = array_reverse($data);
        }
        return [
            'result_data'       => $res,
            'request_params'    => $request_params,
        ];
    }

    /**
     * 阿里云全国快递数据查询
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-11-19
     * @desc    description
     * @param   [array]           $config           [插件配置]
     * @param   [array]           $order            [订单数据]
     * @param   [array]           $express          [快递数据]
     */
    public static function AliyunQuery($config, $order, $express)
    {
        // 增加手机号码后四位
        if(!empty($order['tel']))
        {
            $express['express_number'] .= ':'.substr($order['tel'], -4);
        }
        // 请求
        $url = 'https://wuliu.market.alicloudapi.com/kdi?no='.$express['express_number'].'&type='.$express['express_code'];
        $header = ['Authorization:APPCODE '.$config['aliyun_appcode']];
        $res = CurlPost($url, [], 0, 30, 'GET', $header);
        if($res['code'] == 0 && !empty($res['data']))
        {
            // 数据处理
            if(!is_array($res['data']))
            {
                $res['data'] = json_decode($res['data'], true);
            }

            // 存在快递数据
            $data = [];
            if(isset($res['data']['status']) && $res['data']['status'] == 0 && !empty($res['data']['result']) && !empty($res['data']['result']['list']))
            {
                foreach($res['data']['result']['list'] as $v)
                {
                    $data[] = [
                        'time'  => empty($v['time']) ? '' : $v['time'],
                        'desc'  => empty($v['status']) ? '' : $v['status'],
                    ];
                }
            } else {
                // 无数据则获取错误信息
                if(!empty($res['data']['msg']))
                {
                    $res['msg'] = $res['data']['msg'];
                }
            }
            $res['data'] = $data;
        }
        return [
            'result_data'       => $res,
            'request_params'    => [
                'url'     => $url,
                'header'  => $header
            ],
        ];
    }
}
?>