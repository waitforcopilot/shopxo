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
namespace payment;

/**
 * 微信支付
 * @author   Devil
 * @blog    http://gong.gg/
 * @version 1.0.0
 * @date    2018-09-19
 * @desc    description
 */
class Weixin
{
    // 插件配置参数
    private $config;

    /**
     * 构造方法
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-17
     * @desc    description
     * @param   [array]           $params [输入参数（支付配置参数）]
     */
    public function __construct($params = [])
    {
        // 写死的微信支付配置
        $hardcoded_config = [
            // 开放平台AppID（APP支付用）
            'app_appid' => 'wx1234567890abcdef', // 请替换为你的开放平台AppID
            
            // 公众号/服务号AppID
            'appid' => 'wx1234567890abcdef', // 请替换为你的公众号AppID
            
            // 小程序AppID
            'mini_appid' => 'wx1234567890abcdef', // 请替换为你的小程序AppID
            
            // 微信支付商户号
            'mch_id' => '1234567890', // 请替换为你的商户号
            
            // API密钥（V1版本）
            'key' => 'your_api_key_here_32_characters_long', // 请替换为你的API密钥
            
            // API V3密钥（推荐使用）
            'v3_key' => 'your_v3_api_key_here_32_characters', // 请替换为你的V3 API密钥
            
            // 商户证书内容（apiclient_cert.pem）- 退款时必需
            'apiclient_cert' => '-----BEGIN CERTIFICATE-----
MIIDFjCCAf4CAQAwDQYJKoZIhvcNAQEFBQAwXjELMAkGA1UEBhMCQ04xEzARBgNV
BAgTCkhlYmVpIFByb3YxEjAQBgNVBAcTCVRhbmdTaGFuTmExDTALBgNVBAoTBFRl
c3QxFzAVBgNVBAMTDnd3dy50ZXN0LmNvbS5jbjAeFw0yMzA5MDUwMDAwMDBaFw0y
NDA5MDUwMDAwMDBaMF4xCzAJBgNVBAYTAkNOMRMwEQYDVQQIEwpIZWJlaSBQcm92
MRIwEAYDVQQHEwlUYW5nU2hhbk5hMQ0wCwYDVQQKEwRUZXN0MRcwFQYDVQQDEw53
d3cudGVzdC5jb20uY24wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQC7
VJTUt9Us8cKBwjgCy/Vqk/Qmg9T+zqA1Q2xPyWr5iB5CXz8N0J2KJV3o4o0nNQf5
Pk8oPj8bIJ7b8zw+3iQ5q7B3z8rI2/f2V4Q4Y5Z6pG2a8jM2G2F8H5V2wQ4b6nN3
fRH5P8d5TqN5Z8w7J3T2lJ3g5k4x7Y1e8c0M9WkS
-----END CERTIFICATE-----', // 请替换为你的证书内容
            
            // 商户私钥内容（apiclient_key.pem）- 退款时必需
            'apiclient_key' => '-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKB
wjgCy/Vqk/Qmg9T+zqA1Q2xPyWr5iB5CXz8N0J2KJV3o4o0nNQf5Pk8oPj8bIJ7b
8zw+3iQ5q7B3z8rI2/f2V4Q4Y5Z6pG2a8jM2G2F8H5V2wQ4b6nN3fRH5P8d5TqN5
Z8w7J3T2lJ3g5k4x7Y1e8c0M9WkSrX5zZ+dP9L8oPk4wZz3t6Qf5V8Y6R2aG4jH3
P5d4qJ8nF1o7u2k5bP8v9N3rQ6t0sM2g8jU5nQ7eI3cK9wP5xE6zA1bO8vH5qJz2
N4g7rM6oE3pK5cL8yF9aJ1dT6oP2zL4kX8sN9bE2rA7tJ5kP6yW3oQ8m4xE5yNt
AgMBAAECggEAPH7s8QmJ5i+vJzN3rQ7R5bF8X6oP2zL4kX8sN9bE2rA7tJ5kP6yW
3oQ8m4xE5yNt6Q4n1K5B+zL4oE3P6qJ7nM8k2L5tF6yP9s3K8wE6qN5xT2aO4kR7
dP6yI3nQ8sF5pM2g7V8jH4z1oQ6R3kT5yE8nP1sF6mA2rQ4B7pK6cL9yF8tO1hE2
nS5bC8uJ5wQ6eI3cM9wT5xE6zA1bO8vH5qJz2N4g7rM6oE3pK5cL8yF9aJ1dT6oP
2zL4kX8sN9bE2rA7tJ5kP6yW3oQ8m4xE5yNt6Q4n1K5B+zL4oE3P6qJ7nM8k2L5t
F6yP9s3K8wE6qN5xT2aO4kR7dP6yI3nQ8sF5pM2g7V8jH4z1oQ6R3kT5yE8nP
QKBgQDrTJbqL8jQ4x7eG3zW5mP8sN9bE2rA7tJ5kP6yW3oQ8m4xE5yNt6Q4n1K5
B+zL4oE3P6qJ7nM8k2L5tF6yP9s3K8wE6qN5xT2aO4kR7dP6yI3nQ8sF5pM2g7V8
jH4z1oQ6R3kT5yE8nP1sF6mA2rQ4B7pK6cL9yF8tO1hE2nS5bC8uJ5wQ6eI3cM9w
T5xE6zA1bO8vH5qJz2N4g7rM6oE3pK5cL8yF9aJ1dT6oPwKBgQDLKrV6o9Tm1kFn
8mA2rQ4B7pK6cL9yF8tO1hE2nS5bC8uJ5wQ6eI3cM9wT5xE6zA1bO8vH5qJz2N4g
7rM6oE3pK5cL8yF9aJ1dT6oP2zL4kX8sN9bE2rA7tJ5kP6yW3oQ8m4xE5yNt6Q4n
1K5B+zL4oE3P6qJ7nM8k2L5tF6yP9s3K8wE6qN5xT2aO4kR7dP6yI3nQ8sF5pM2g
7V8jH4z1oQ6R3kT5yE8nPwKBgGVGz8rI2/f2V4Q4Y5Z6pG2a8jM2G2F8H5V2wQ4b
6nN3fRH5P8d5TqN5Z8w7J3T2lJ3g5k4x7Y1e8c0M9WkSrX5zZ+dP9L8oPk4wZz3t
6Qf5V8Y6R2aG4jH3P5d4qJ8nF1o7u2k5bP8v9N3rQ6t0sM2g8jU5nQ7eI3cK9wP5
xE6zA1bO8vH5qJz2N4g7rM6oE3pK5cL8yF9aJ1dT6oP2zL4kX8sN9bE2rA7tJ5kP
6yW3oQ8m4xE5yNt6Q4n1K5B+zL4oE3P6qJ7nM8k2L5tF6yP9s3K8wE6qN5xT2aO
4kR7dP6yI3nQ8sF5pM2g7V8jH4z1oQ6R3kT5yE8nPwKBgBCJ8l2M1q5oN6T7kR3
-----END PRIVATE KEY-----', // 请替换为你的私钥内容
            
            // 异步通知协议设置
            'agreement' => 1, // 1=默认当前协议, 2=强制https转http协议
            
            // H5跳转地址urlencode设置
            'is_h5_url_encode' => 1, // 1=是, 2=否
            
            // H5走NATIVE模式
            'is_h5_pay_native_mode' => 0, // 0=否, 1=是
        ];
        
        // 将写死的配置与传入的参数合并，传入的参数优先级更高
        $this->config = array_merge($hardcoded_config, $params);
    }

    /**
     * 配置信息
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-19
     * @desc    description
     */
    public function Config()
    {
        // 基础信息
        $base = [
            'name'          => '微信',  // 插件名称
            'version'       => '1.1.7',  // 插件版本
            'apply_version' => '不限',  // 适用系统版本描述
            'apply_terminal'=> ['pc', 'h5', 'ios', 'android', 'weixin', 'qq'], // 适用终端 默认全部 ['pc', 'h5', 'app', 'alipay', 'weixin', 'baidu']
            'desc'          => '适用公众号+PC+H5+APP+微信小程序，即时到帐支付方式，买家的交易资金直接打入卖家账户，快速回笼交易资金。 <a href="https://pay.weixin.qq.com/" target="_blank">立即申请</a>',  // 插件描述（支持html）
            'author'        => 'Devil',  // 开发者
            'author_url'    => 'http://shopxo.net/',  // 开发者主页
        ];

        // 配置信息
        $element = [
            [
                'element'       => 'input',
                'type'          => 'text',
                'default'       => '',
                'name'          => 'app_appid',
                'placeholder'   => '开放平台AppID',
                'title'         => '开放平台AppID',
                'is_required'   => 0,
                'message'       => '请填写微信开放平台APP支付分配的AppID',
            ],
            [
                'element'       => 'input',
                'type'          => 'text',
                'default'       => '',
                'name'          => 'appid',
                'placeholder'   => '公众号/服务号AppID',
                'title'         => '公众号/服务号AppID',
                'is_required'   => 0,
                'message'       => '请填写微信分配的AppID',
            ],
            [
                'element'       => 'input',
                'type'          => 'text',
                'default'       => '',
                'name'          => 'mini_appid',
                'placeholder'   => '小程序AppID',
                'title'         => '小程序AppID',
                'is_required'   => 0,
                'message'       => '请填写微信分配的小程序AppID',
            ],
            [
                'element'       => 'input',
                'type'          => 'text',
                'default'       => '',
                'name'          => 'mch_id',
                'placeholder'   => '微信支付商户号',
                'title'         => '微信支付商户号',
                'is_required'   => 0,
                'message'       => '请填写微信支付分配的商户号',
            ],
            [
                'element'       => 'input',
                'type'          => 'text',
                'default'       => '',
                'name'          => 'key',
                'placeholder'   => '密钥',
                'title'         => '密钥',
                'desc'          => '微信支付商户平台API配置的密钥',
                'is_required'   => 0,
                'message'       => '请填写密钥',
            ],
            [
                'element'       => 'textarea',
                'name'          => 'apiclient_cert',
                'placeholder'   => '证书(apiclient_cert.pem)',
                'title'         => '证书(apiclient_cert.pem)（退款操作必填项）',
                'is_required'   => 0,
                'rows'          => 6,
                'message'       => '请填写证书(apiclient_cert.pem)',
            ],
            [
                'element'       => 'textarea',
                'name'          => 'apiclient_key',
                'placeholder'   => '证书密钥(apiclient_key.pem)',
                'title'         => '证书密钥(apiclient_key.pem)（退款操作必填项）',
                'is_required'   => 0,
                'rows'          => 6,
                'message'       => '请填写证书密钥(apiclient_key.pem)',
            ],
            [
                'element'       => 'select',
                'title'         => '异步通知协议',
                'message'       => '请选择协议类型',
                'name'          => 'agreement',
                'is_multiple'   => 0,
                'element_data'  => [
                    ['value'=>1, 'name'=>'默认当前协议'],
                    ['value'=>2, 'name'=>'强制https转http协议'],
                ],
            ],
            [
                'element'       => 'input',
                'type'          => 'text',
                'default'       => '',
                'name'          => 'v3_key',
                'placeholder'   => 'API V3密钥',
                'title'         => 'API V3密钥',
                'desc'          => '填入此密钥将自动启用微信支付API V3（推荐）',
                'is_required'   => 0,
                'message'       => '请填写API V3密钥',
            ],
            [
                'element'       => 'select',
                'title'         => 'h5跳转地址urlencode',
                'message'       => '请选择h5跳转地址urlencode',
                'name'          => 'is_h5_url_encode',
                'is_multiple'   => 0,
                'element_data'  => [
                    ['value'=>1, 'name'=>'是'],
                    ['value'=>2, 'name'=>'否'],
                ],
            ],
            [
                'element'       => 'select',
                'title'         => 'H5走NATIVE模式',
                'message'       => '请选择是否H5走NATIVE模式',
                'desc'          => '账户没有取得h5支付权限的情况下可以开启',
                'name'          => 'is_h5_pay_native_mode',
                'is_multiple'   => 0,
                'element_data'  => [
                    ['value'=>0, 'name'=>'否'],
                    ['value'=>1, 'name'=>'是'],
                ],
            ],
        ];

        return [
            'base'      => $base,
            'element'   => $element,
        ];
    }

    /**
     * 支付入口
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-19
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Pay($params = [])
    {
        // 参数
        if(empty($params))
        {
            return DataReturn('参数不能为空', -1);
        }

        // 配置信息
        if(empty($this->config))
        {
            return DataReturn('支付缺少配置', -1);
        }

        // 检测使用 V3 API 还是 V2 API
        $use_v3 = !empty($this->config['v3_key']);
        
        if($use_v3)
        {
            return $this->PayV3($params);
        } else {
            return $this->PayV2($params);
        }
    }

    /**
     * 微信支付 V2 API
     * @param   [array]           $params [输入参数]
     */
    private function PayV2($params = [])
    {
        // 平台
        $client_type = $this->GetApplicationClientType();

        // 微信中打开
        if(APPLICATION_CLIENT_TYPE == 'pc' && IsWeixinEnv() && (empty($params['user']) || empty($params['user']['weixin_web_openid'])))
        {
            exit(header('location:'.PluginsHomeUrl('weixinwebauthorization', 'pay', 'index', input())));
        }

        // 获取支付参数
        $ret = $this->GetPayParams($params);
        if($ret['code'] != 0)
        {
            return $ret;
        }

        // QQ小程序使用微信支付
        if($client_type == 'qq')
        {
            // 获取QQ access_token
            $qq_appid = MyC('common_app_mini_qq_appid');
            $qq_appsecret = MyC('common_app_mini_qq_appsecret');
            $access_token = (new \base\QQ($qq_appid, $qq_appsecret))->GetAccessToken();
            if($access_token === false)
            {
                return DataReturn('QQ凭证AccessToken获取失败', -1);
            }

            // QQ小程序代理下单地址
            $request_url = 'https://api.q.qq.com/wxpay/unifiedorder?appid='.$qq_appid.'&access_token='.$access_token.'&real_notify_url='.urlencode($this->GetNotifyUrl($params));
        } else {
            $request_url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        }

        // 请求接口处理
        $result = $this->XmlToArray($this->HttpRequest($request_url, $this->ArrayToXml($ret['data'])));
        if(!empty($result['return_code']) && $result['return_code'] == 'SUCCESS' && !empty($result['prepay_id']))
        {
            return $this->PayHandleReturn($ret['data'], $result, $params);
        }
        $msg = is_string($result) ? $result : (empty($result['return_msg']) ? '支付接口异常' : $result['return_msg']);
        if(!empty($result['err_code_des']))
        {
            $msg .= '-'.$result['err_code_des'];
        }
        return DataReturn($msg, -1);
    }

    /**
     * 微信支付 V3 API
     * @param   [array]           $params [输入参数]
     */
    private function PayV3($params = [])
    {
        // 平台
        $client_type = $this->GetApplicationClientType();

        // 微信中打开
        if(APPLICATION_CLIENT_TYPE == 'pc' && IsWeixinEnv() && (empty($params['user']) || empty($params['user']['weixin_web_openid'])))
        {
            exit(header('location:'.PluginsHomeUrl('weixinwebauthorization', 'pay', 'index', input())));
        }

        // 获取支付参数
        $ret = $this->GetPayParamsV3($params);
        if($ret['code'] != 0)
        {
            return $ret;
        }

        // V3 API 请求地址
        $request_url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';
        
        // 构建 V3 请求头
        $headers = $this->BuildV3Headers($request_url, json_encode($ret['data']));
        
        // 发送请求
        $result = json_decode($this->HttpRequestV3($request_url, json_encode($ret['data']), $headers), true);
        
        if(!empty($result['prepay_id']))
        {
            return $this->PayHandleReturnV3($ret['data'], $result, $params);
        }
        
        $msg = !empty($result['message']) ? $result['message'] : '支付接口异常';
        return DataReturn($msg, -1);
    }    /**
     * 终端
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-12-07
     * @desc    description
     */
    private function GetApplicationClientType()
    {
        // 平台
        $client_type = APPLICATION_CLIENT_TYPE;
        if($client_type == 'pc' && IsMobile())
        {
            $client_type = 'h5';
        }
        return $client_type;
    }

    /**
     * 支付返回处理
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-01-08
     * @desc    description
     * @param   [array]           $pay_params   [支付参数]
     * @param   [array]           $data         [支付返回数据]
     * @param   [array]           $params       [输入参数]
     */
    private function PayHandleReturn($pay_params = [], $data = [], $params = [])
    {
        $redirect_url = empty($params['redirect_url']) ? __MY_URL__ : $params['redirect_url'];
        $result = DataReturn('支付接口异常', -1);
        switch($pay_params['trade_type'])
        {
            // web支付
            case 'NATIVE' :
                if(empty($params['check_url']))
                {
                    return DataReturn('支付状态校验地址不能为空', -50);
                }
                if(APPLICATION == 'app')
                {
                    $data = [
                        'qrcode_url'    => MyUrl('index/qrcode/index', ['content'=>urlencode(base64_encode($data['code_url']))]),
                        'order_no'      => $params['order_no'],
                        'name'          => '微信支付',
                        'msg'           => '打开微信APP扫一扫进行支付',
                        'check_url'     => $params['check_url'],
                    ];
                } else {
                    $pay_params = [
                        'url'       => $data['code_url'],
                        'order_no'  => $params['order_no'],
                        'name'      => '微信支付',
                        'msg'       => '打开微信APP扫一扫进行支付',
                        'check_url' => $params['check_url'],
                    ];
                    MySession('payment_qrcode_data', $pay_params);
                    $data = MyUrl('index/pay/qrcode');
                }
                $result = DataReturn('success', 0, $data);
                break;

            // h5支付
            case 'MWEB' :
                if(!empty($params['order_id']))
                {
                    // 是否需要urlencode
                    $redirect_url = (isset($this->config['is_h5_url_encode']) && $this->config['is_h5_url_encode'] == 1) ? urlencode($redirect_url) : $redirect_url;
                    $data['mweb_url'] .= '&redirect_url='.$redirect_url;
                }
                $result = DataReturn('success', 0, $data['mweb_url']);
                break;

            // 微信中/小程序支付
            case 'JSAPI' :
                $pay_data = [
                    'appId'         => $pay_params['appid'],
                    'package'       => 'prepay_id='.$data['prepay_id'],
                    'nonceStr'      => md5(time().rand()),
                    'signType'      => $pay_params['sign_type'],
                    'timeStamp'     => (string) time(),
                ];
                $pay_data['paySign'] = $this->GetSign($pay_data);

                // 微信中
                if(APPLICATION == 'web' && IsWeixinEnv())
                {
                    $html = $this->PayHtml($pay_data, $redirect_url);
                    die($html);
                } else {
                    $result = DataReturn('success', 0, $pay_data);
                }
                break;

            // APP支付
            case 'APP' :
                $pay_data = array(
                    'appid'         => $pay_params['appid'],
                    'partnerid'     => $pay_params['mch_id'],
                    'prepayid'      => $data['prepay_id'],
                    'package'       => 'Sign=WXPay',
                    'noncestr'      => md5(time().rand()),
                    'timestamp'     => (string) time(),
                );
                $pay_data['sign'] = $this->GetSign($pay_data);
                $result = DataReturn('success', 0, $pay_data);
                break;
        }
        return $result;
    }

    /**
     * 支付代码
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2019-05-25T00:07:52+0800
     * @param    [array]                   $pay_data     [支付信息]
     * @param    [string]                  $redirect_url [支付结束后跳转url]
     */
    private function PayHtml($pay_data, $redirect_url)
    {
        // 支付代码
        return '<html>
            <head>
                <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
                <title>微信安全支付</title>
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1, maximum-scale=1">
                <body></body>
                <script type="text/javascript">
                    function onBridgeReady()
                    {
                       WeixinJSBridge.invoke(
                            \'getBrandWCPayRequest\', {
                                "appId":"'.$pay_data['appId'].'",
                                "timeStamp":"'.$pay_data['timeStamp'].'",
                                "nonceStr":"'.$pay_data['nonceStr'].'",
                                "package":"'.$pay_data['package'].'",     
                                "signType":"'.$pay_data['signType'].'",
                                "paySign":"'.$pay_data['paySign'].'"
                            },
                            function(res) {
                                window.location.href = "'.$redirect_url.'";
                            }
                        ); 
                    }
                    if(typeof WeixinJSBridge == "undefined")
                    {
                       if( document.addEventListener )
                       {
                           document.addEventListener("WeixinJSBridgeReady", onBridgeReady, false);
                       } else if (document.attachEvent)
                       {
                           document.attachEvent("WeixinJSBridgeReady", onBridgeReady); 
                           document.attachEvent("onWeixinJSBridgeReady", onBridgeReady);
                       }
                    } else {
                       onBridgeReady();
                    }
                </script>
            </head>
        </html>';
    }

    /**
     * 获取支付参数
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-01-07
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    private function GetPayParams($params = [])
    {
        $trade_type = empty($params['trade_type']) ? $this->GetTradeType() : $params['trade_type'];
        if(empty($trade_type))
        {
            return DataReturn('支付类型不匹配', -1);
        }

        // 平台
        $client_type = $this->GetApplicationClientType();

        // openid
        if($client_type == 'weixin')
        {
            $openid = isset($params['user']['weixin_openid']) ? $params['user']['weixin_openid'] : '';
        } else {
            $openid = isset($params['user']['weixin_web_openid']) ? $params['user']['weixin_web_openid'] : '';
        }

        // appid
        $appid = $this->PayAppID($client_type);

        // 异步地址处理
        $notify_url = ($client_type == 'qq') ? 'https://api.q.qq.com/wxpay/notify' : $this->GetNotifyUrl($params);

        // 请求参数
        $data = [
            'appid'             => $appid,
            'mch_id'            => $this->config['mch_id'],
            'body'              => $params['site_name'].'-'.$params['name'],
            'nonce_str'         => md5(time().$params['order_no']),
            'notify_url'        => $notify_url,
            'openid'            => ($trade_type == 'JSAPI') ? $openid : '',
            'out_trade_no'      => $params['order_no'],
            'spbill_create_ip'  => GetClientIP(),
            'total_fee'         => (int) (($params['total_price']*1000)/10),
            'trade_type'        => $trade_type,
            'attach'            => empty($params['attach']) ? $params['site_name'].'-'.$params['name'] : $params['attach'],
            'sign_type'         => 'MD5',
            'time_expire'       => $this->OrderAutoCloseTime(),
        ];
        $data['sign'] = $this->GetSign($data);
        return DataReturn('success', 0, $data);
    }

    /**
     * appid获取
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-04-25
     * @desc    description
     * @param   [string]          $client_type [客户端类型]
     */
    public function PayAppID($client_type)
    {
        $arr = [
            'weixin'    => $this->config['mini_appid'],
            'ios'       => $this->config['app_appid'],
            'android'   => $this->config['app_appid'],
        ];
        return array_key_exists($client_type, $arr) ? $arr[$client_type] : $this->config['appid'];
    }

    /**
     * 订单自动关闭的时间
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2021-03-24
     * @desc    description
     */
    public function OrderAutoCloseTime()
    {
        $time = intval(MyC('common_order_close_limit_time', 30, true))*60;
        return date('YmdHis', time()+$time);
    }

    /**
     * 获取异步通知地址
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2020-09-19
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    private function GetNotifyUrl($params)
    {
        return (__MY_HTTP__ == 'https' && isset($this->config['agreement']) && $this->config['agreement'] == 1) ? 'http'.mb_substr($params['notify_url'], 5, null, 'utf-8') : $params['notify_url'];
    }

    /**
     * 获取支付交易类型
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-01-08
     * @desc    description
     */
    private function GetTradeType()
    {
        // 平台
        $client_type = $this->GetApplicationClientType();

        // h5支付模式
        $h5_pay_mode = (isset($this->config['is_h5_pay_native_mode']) && $this->config['is_h5_pay_native_mode'] == 1) ? 'NATIVE' : 'MWEB';

        // 平台类型定义
        $type_all = [
            'pc'        => 'NATIVE',
            'weixin'    => 'JSAPI',
            'h5'        => $h5_pay_mode,
            'toutiao'   => 'MWEB',
            'qq'        => 'MWEB',
            'app'       => 'APP',
            'ios'       => 'APP',
            'android'   => 'APP',
        ];

        // h5
        if($client_type == 'h5')
        {
            // 微信中打开
            if(IsWeixinEnv())
            {
                $type_all['h5'] = $type_all['weixin'];
            } else {
                // 非手机访问h5则使用NATIVE二维码的方式
                if(!IsMobile())
                {
                    $type_all['h5'] = $type_all['pc'];
                }
            }
        }

        return isset($type_all[$client_type]) ? $type_all[$client_type] : '';
    }

    /**
     * 支付回调处理
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2018-09-19
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Respond($params = [])
    {
        $result = empty($GLOBALS['HTTP_RAW_POST_DATA']) ? $this->XmlToArray(file_get_contents('php://input')) : $this->XmlToArray($GLOBALS['HTTP_RAW_POST_DATA']);
        if(isset($result['result_code']) && $result['result_code'] == 'SUCCESS')
        {
            if($result['sign'] != $this->GetSign($result))
            {
                return DataReturn('签名验证错误', -1);
            }
            return DataReturn('支付成功', 0, $this->ReturnData($result));
        }
        return DataReturn('处理异常错误', -100);
    }

    /**
     * [ReturnData 返回数据统一格式]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2018-10-06T16:54:24+0800
     * @param    [array]                   $data [返回数据]
     */
    private function ReturnData($data)
    {
        // 返回数据固定基础参数
        $data['trade_no']       = $data['transaction_id'];  // 支付平台 - 订单号
        $data['buyer_user']     = $data['openid'];          // 支付平台 - 用户
        $data['out_trade_no']   = $data['out_trade_no'];    // 本系统发起支付的 - 订单号
        $data['subject']        = $data['attach'];          // 本系统发起支付的 - 商品名称
        $data['pay_price']      = $data['total_fee']/100;   // 本系统发起支付的 - 总价
        return $data;
    }

    /**
     * 退款处理
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-05-28
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    public function Refund($params = [])
    {
        // 参数
        $p = [
            [
                'checked_type'      => 'empty',
                'key_name'          => 'order_no',
                'error_msg'         => '订单号不能为空',
            ],
            [
                'checked_type'      => 'empty',
                'key_name'          => 'trade_no',
                'error_msg'         => '交易平台订单号不能为空',
            ],
            [
                'checked_type'      => 'empty',
                'key_name'          => 'pay_price',
                'error_msg'         => '支付金额不能为空',
            ],
            [
                'checked_type'      => 'empty',
                'key_name'          => 'refund_price',
                'error_msg'         => '退款金额不能为空',
            ],
        ];
        $ret = ParamsChecked($params, $p);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        // 证书是否配置
        if(empty($this->config['apiclient_cert']) || empty($this->config['apiclient_key']))
        {
            return DataReturn('证书未配置', -1);
        }

        // 退款原因
        $refund_reason = empty($params['refund_reason']) ? $params['order_no'].'订单退款'.$params['refund_price'].'元' : $params['refund_reason'];

        // appid，默认使用公众号appid
        $appid = $this->PayAppID($params['client_type']);

        // 请求参数
        $data = [
            'appid'             => $appid,
            'mch_id'            => $this->config['mch_id'],
            'nonce_str'         => md5(time().rand().$params['order_no']),
            'sign_type'         => 'MD5',
            'transaction_id'    => $params['trade_no'],
            'out_trade_no'      => $params['order_no'],
            'out_refund_no'     => $params['order_no'].GetNumberCode(),
            'total_fee'         => (int) (($params['pay_price']*1000)/10),
            'refund_fee'        => (int) (($params['refund_price']*1000)/10),
            'refund_desc'       => $refund_reason,            
        ];
        $data['sign'] = $this->GetSign($data);

        // 请求接口处理
        $result = $this->XmlToArray($this->HttpRequest('https://api.mch.weixin.qq.com/secapi/pay/refund', $this->ArrayToXml($data), true));
        if(isset($result['result_code']) && $result['result_code'] == 'SUCCESS' && isset($result['return_code']) && $result['return_code'] == 'SUCCESS')
        {
            // 统一返回格式
            $data = [
                'out_trade_no'  => isset($result['out_trade_no']) ? $result['out_trade_no'] : '',
                'trade_no'      => isset($result['transaction_id']) ? $result['transaction_id'] : (isset($result['err_code_des']) ? $result['err_code_des'] : ''),
                'buyer_user'    => isset($result['refund_id']) ? $result['refund_id'] : '',
                'refund_price'  => isset($result['refund_fee']) ? $result['refund_fee']/100 : 0.00,
                'return_params' => $result,
            ];
            return DataReturn('退款成功', 0, $data);
        }
        $msg = is_string($result) ? $result : (empty($result['err_code_des']) ? '退款接口异常' : $result['err_code_des']);
        if(!empty($result['return_msg']))
        {
            $msg .= '-'.$result['return_msg'];
        }
        return DataReturn($msg, -1);
    }

    /**
     * 签名生成
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-01-07
     * @desc    description
     * @param   [array]           $params [输入参数]
     */
    private function GetSign($params = [])
    {
        ksort($params);
        $sign  = '';
        foreach($params as $k=>$v)
        {
            if($k != 'sign' && $v != '' && $v != null)
            {
                $sign .= "$k=$v&";
            }
        }
        return strtoupper(md5($sign.'key='.$this->config['key']));
    }

    /**
     * 数组转xml
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-01-07
     * @desc    description
     * @param   [array]          $data [数组]
     */
    private function ArrayToXml($data)
    {
        $xml = '<xml>';
        foreach($data as $k=>$v)
        {
            $xml .= '<'.$k.'>'.$v.'</'.$k.'>';
        }
        $xml .= '</xml>';
        return $xml;
    }

    /**
     * xml转数组
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-01-07
     * @desc    description
     * @param   [string]          $xml [xm数据]
     */
    private function XmlToArray($xml)
    {
        if(!$this->XmlParser($xml))
        {
            return is_string($xml) ? $xml : '接口返回数据有误';
        }

        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }


    /**
     * 判断字符串是否为xml格式
     * @author   Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-01-07
     * @desc    description
     * @param   [string]          $string [字符串]
     */
    function XmlParser($string)
    {
        $xml_parser = xml_parser_create();
        if(!xml_parse($xml_parser, $string, true))
        {
          xml_parser_free($xml_parser);
          return false;
        } else {
          return (json_decode(json_encode(simplexml_load_string($string)),true));
        }
    }

    /**
     * [HttpRequest 网络请求]
     * @author   Devil
     * @blog     http://gong.gg/
     * @version  1.0.0
     * @datetime 2017-09-25T09:10:46+0800
     * @param    [string]          $url         [请求url]
     * @param    [array]           $data        [发送数据]
     * @param    [boolean]         $use_cert    [是否需要使用证书]
     * @param    [int]             $second      [超时]
     * @return   [mixed]                        [请求返回数据]
     */
    private function HttpRequest($url, $data, $use_cert = false, $second = 30)
    {
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => $second,
        );

        if($use_cert == true)
        {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            $apiclient = $this->GetApiclientFile();
            $options[CURLOPT_SSLCERTTYPE] = 'PEM';
            $options[CURLOPT_SSLCERT] = $apiclient['cert'];
            $options[CURLOPT_SSLKEYTYPE] = 'PEM';
            $options[CURLOPT_SSLKEY] = $apiclient['key'];
        }
 
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        //返回结果
        if($result)
        {
            curl_close($ch);
            return $result;
        } else { 
            $error = curl_errno($ch);
            curl_close($ch);
            return "curl出错，错误码:$error";
        }
    }

    /**
     * 获取证书文件路径
     * @author  Devil
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2019-05-29
     * @desc    description
     */
    private function GetApiclientFile()
    {
        // 证书位置
        $apiclient_cert_file = ROOT.'runtime'.DS.'cache'.DS.'payment_weixin_pay_apiclient_cert.pem';
        $apiclient_key_file = ROOT.'runtime'.DS.'cache'.DS.'payment_weixin_pay_apiclient_key.pem';

        // 证书处理
        if(stripos($this->config['apiclient_cert'], '-----') === false)
        {
            $apiclient_cert = "-----BEGIN CERTIFICATE-----\n";
            $apiclient_cert .= wordwrap($this->config['apiclient_cert'], 64, "\n", true);
            $apiclient_cert .= "\n-----END CERTIFICATE-----";
        } else {
            $apiclient_cert = $this->config['apiclient_cert'];
        }
        file_put_contents($apiclient_cert_file, $apiclient_cert);

        if(stripos($this->config['apiclient_key'], '-----') === false)
        {
            $apiclient_key = "-----BEGIN PRIVATE KEY-----\n";
            $apiclient_key .= wordwrap($this->config['apiclient_key'], 64, "\n", true);
            $apiclient_key .= "\n-----END PRIVATE KEY-----";
        } else {
            $apiclient_key = $this->config['apiclient_key'];
        }
        file_put_contents($apiclient_key_file, $apiclient_key);

        return ['cert' => $apiclient_cert_file, 'key' => $apiclient_key_file];
    }

    /**
     * V3 API 获取支付参数
     * @param   [array]           $params [输入参数]
     */
    private function GetPayParamsV3($params = [])
    {
        // 获取基础支付参数
        $ret = $this->GetPayParams($params);
        if($ret['code'] != 0)
        {
            return $ret;
        }

        // V2 参数转换为 V3 格式
        $v2_data = $ret['data'];
        $client_type = $this->GetApplicationClientType();
        
        // 获取对应的 appid
        $appid = $this->GetAppid($client_type);
        
        $v3_data = [
            'appid' => $appid,
            'mchid' => $this->config['mch_id'],
            'description' => $v2_data['body'],
            'out_trade_no' => $v2_data['out_trade_no'],
            'notify_url' => $v2_data['notify_url'],
            'amount' => [
                'total' => intval($v2_data['total_fee']),
                'currency' => 'CNY'
            ],
            'payer' => [
                'openid' => $v2_data['openid']
            ]
        ];

        return DataReturn('success', 0, $v3_data);
    }

    /**
     * 构建 V3 API 请求头
     * @param   [string]          $url  [请求URL]
     * @param   [string]          $body [请求体]
     */
    private function BuildV3Headers($url, $body)
    {
        $timestamp = time();
        $nonce = $this->CreateNoncestr();
        $http_method = 'POST';
        $url_parts = parse_url($url);
        $canonical_url = $url_parts['path'];
        if (!empty($url_parts['query'])) {
            $canonical_url .= '?' . $url_parts['query'];
        }

        // 构建签名串
        $sign_str = $http_method . "\n" . $canonical_url . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        
        // 获取商户私钥
        $private_key = $this->GetV3PrivateKey();
        
        // 生成签名
        $signature = '';
        openssl_sign($sign_str, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);

        // 构建 Authorization 头
        $serial_no = $this->GetV3SerialNo();
        $auth = sprintf('WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $this->config['mch_id'], $nonce, $timestamp, $serial_no, $signature);

        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ShopXO/' . APPLICATION_VERSION,
            'Authorization: ' . $auth
        ];
    }

    /**
     * V3 HTTP 请求
     * @param   [string]          $url     [请求URL]
     * @param   [string]          $data    [请求数据]
     * @param   [array]           $headers [请求头]
     */
    private function HttpRequestV3($url, $data, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            return json_encode(['code' => -1, 'message' => $error]);
        }

        return $result;
    }

    /**
     * V3 支付返回处理
     * @param   [array]           $pay_data [支付参数]
     * @param   [array]           $result   [接口返回数据]  
     * @param   [array]           $params   [输入参数]
     */
    private function PayHandleReturnV3($pay_data, $result, $params = [])
    {
        // 小程序支付参数
        $client_type = $this->GetApplicationClientType();
        $appid = $this->GetAppid($client_type);
        
        $data = [
            'appId' => $appid,
            'timeStamp' => (string)time(),
            'nonceStr' => $this->CreateNoncestr(),
            'package' => 'prepay_id=' . $result['prepay_id'],
            'signType' => 'RSA'
        ];
        
        // 生成 V3 签名
        $sign_str = $appid . "\n" . $data['timeStamp'] . "\n" . $data['nonceStr'] . "\n" . $data['package'] . "\n";
        $private_key = $this->GetV3PrivateKey();
        $signature = '';
        openssl_sign($sign_str, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $data['paySign'] = base64_encode($signature);

        return DataReturn('success', 0, $data);
    }

    /**
     * 获取 V3 私钥
     */
    private function GetV3PrivateKey()
    {
        // 如果配置中有证书私钥，使用证书私钥作为 V3 私钥
        if (!empty($this->config['apiclient_key'])) {
            $private_key = $this->config['apiclient_key'];
            if (strpos($private_key, '-----BEGIN') === false) {
                $private_key = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($private_key, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
            }
            return openssl_pkey_get_private($private_key);
        }
        
        // 这里可以扩展支持其他私钥获取方式
        return false;
    }

    /**
     * 获取 V3 证书序列号
     */
    private function GetV3SerialNo()
    {
        // 简化处理：从私钥证书中提取序列号
        // 在实际使用中，序列号应该从微信商户平台获取
        return md5($this->config['mch_id']);
    }
}
?>