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
 * 微信支付 (API v3)
 */
class Weixin
{
    /** @var array */
    private $config;

    /** @var string */
    private $request_mch_id = '';

    /** @var string */
    private const DEFAULT_MERCHANT_CATEGORY_CODE = '5977';

    /** @var int */
    private const MAXIMUM_CLOCK_OFFSET = 300;

    public function __construct($params = [])
    {
        $this->config = $params;
    }

    private function SetRequestMchId($mchid)
    {
        $this->request_mch_id = $mchid;
    }

    private function IsServiceProviderMode()
    {
        return !empty($this->config['sp_mch_id'])
            && !empty($this->config['sp_appid'])
            && !empty($this->config['sub_mch_id']);
    }

    private function writeLog($tag, $data = [])
    {
        try
        {
            if(defined('ROOT') && ROOT !== '')
            {
                $log_dir = rtrim(ROOT, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'log';
            }
            else
            {
                $log_dir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'log';
            }

            if(!is_dir($log_dir))
            {
                @mkdir($log_dir, 0777, true);
            }

            $micro = microtime(true);
            $timestamp = date('Y-m-d H:i:s', (int) $micro).sprintf('.%06d', ($micro - floor($micro)) * 1000000);

            $log_entry = [
                'timestamp'    => $timestamp,
                'tag'          => $tag,
                'data'         => $data,
                'memory_usage' => memory_get_usage(true),
            ];

            $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
            @file_put_contents($log_dir.DIRECTORY_SEPARATOR.'weixinhk_api.log', $log_line, FILE_APPEND | LOCK_EX);
        }
        catch(\Exception $e)
        {
            // ignore logging failures to avoid affecting payment flow
        }
    }

    public function Config()
    {
        $base = [
            'name'          => '微信',
            'version'       => '2.0.0',
            'apply_version' => '不限',
            'apply_terminal'=> ['pc', 'h5', 'ios', 'android', 'weixin', 'qq'],
            'desc'          => '适用公众号+PC+H5+APP+微信小程序，即时到帐支付方式。<a href="https://pay.weixin.qq.com/" target="_blank">立即申请</a>',
            'author'        => 'Devil',
            'author_url'    => 'http://shopxo.net/',
        ];

        $element = [
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'app_appid',
                'title'       => '开放平台AppID',
                'placeholder' => '开放平台AppID',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'appid',
                'title'       => '公众号/服务号AppID',
                'placeholder' => '公众号/服务号AppID',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'mini_appid',
                'title'       => '小程序AppID',
                'placeholder' => '小程序AppID',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'mch_id',
                'title'       => '微信支付商户号',
                'placeholder' => '微信支付商户号',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'currency',
                'title'       => '交易币种',
                'placeholder' => '如：HKD',
                'desc'        => '跨境接口需填写币种，默认建议使用HKD',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'sp_appid',
                'title'       => '机构AppID(sp_appid)',
                'placeholder' => '机构模式必填',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'sp_mch_id',
                'title'       => '机构商户号(sp_mchid)',
                'placeholder' => '机构模式必填',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'sub_mch_id',
                'title'       => '子商户号(sub_mchid)',
                'placeholder' => '机构模式必填',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'sub_appid',
                'title'       => '子商户AppID(sub_appid)',
                'placeholder' => '机构模式小程序/公众号AppID',
                'is_required' => 0,
            ],
            [
                'element'     => 'input',
                'type'        => 'text',
                'name'        => 'key',
                'title'       => 'API v3 密钥',
                'placeholder' => '32位API v3密钥',
                'desc'        => '商户平台-账户中心-API安全-APIv3密钥',
                'is_required' => 0,
            ],
            [
                'element'     => 'textarea',
                'name'        => 'apiclient_key',
                'title'       => '商户私钥(apiclient_key.pem)',
                'placeholder' => '商户私钥(apiclient_key.pem)',
                'rows'        => 6,
                'is_required' => 0,
            ],
            [
                'element'     => 'textarea',
                'name'        => 'apiclient_cert',
                'title'       => '商户证书(apiclient_cert.pem)',
                'placeholder' => '商户证书(apiclient_cert.pem)',
                'rows'        => 6,
                'is_required' => 0,
            ],
            [
                'element'     => 'textarea',
                'name'        => 'platform_certs',
                'title'       => '微信平台证书(PEM)',
                'placeholder' => '可粘贴一个或多个微信支付平台证书，换行分隔',
                'rows'        => 6,
                'is_required' => 0,
                'desc'        => '用于回调签名校验，建议定期更新，支持多个证书连续粘贴',
            ],
            [
                'element'       => 'select',
                'title'         => '异步通知协议',
                'name'          => 'agreement',
                'is_multiple'   => 0,
                'element_data'  => [
                    ['value'=>1, 'name'=>'默认当前协议'],
                    ['value'=>2, 'name'=>'强制https转http协议'],
                ],
            ],
            [
                'element'       => 'select',
                'title'         => 'h5跳转地址urlencode',
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
                'name'          => 'is_h5_pay_native_mode',
                'desc'          => '无H5支付权限时可开启，使用二维码方案',
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

    public function Pay($params = [])
    {
        if(empty($params))
        {
            return DataReturn('参数不能为空', -1);
        }
        if(empty($this->config))
        {
            return DataReturn('支付缺少配置', -1);
        }

        if(APPLICATION_CLIENT_TYPE == 'pc' && IsWeixinEnv() && (empty($params['user']) || empty($params['user']['weixin_web_openid'])))
        {
            exit(header('location:'.PluginsHomeUrl('weixinwebauthorization', 'pay', 'index', input())));
        }

        $context = $this->BuildPayContext($params);
        if($context['code'] != 0)
        {
            return $context;
        }
        $context = $context['data'];

        $api_url = $this->GetApiUrl($context['trade_type']);
        $payload_json = json_encode($context['payload'], JSON_UNESCAPED_UNICODE);

        $response = $this->HttpRequest($api_url, $payload_json);
        if(!isset($response['success']) || $response['success'] !== true)
        {
            $body = isset($response['body']) && is_array($response['body']) ? $response['body'] : [];
            $message = $response['message'] ?? '支付接口异常';
            return DataReturn($message, -1, $body);
        }

        if(!$this->VerifyResponseSignature($response['headers'] ?? [], $response['raw_body'] ?? '', parse_url($api_url, PHP_URL_PATH)))
        {
            return DataReturn('支付返回数据验签失败', -1);
        }

        if(empty($response['body']) || !is_array($response['body']))
        {
            return DataReturn('支付接口返回解析失败', -1, ['raw'=>$response['raw_body'] ?? '']);
        }

        return $this->HandlePayResponse($context, $response['body'], $params);
    }

    private function BuildPayContext($params)
    {
        $trade_type = empty($params['trade_type']) ? $this->GetTradeType() : $params['trade_type'];
        if(empty($trade_type))
        {
            return DataReturn('无法匹配支付场景', -1);
        }

        $client_type = $this->GetApplicationClientType();
        $appid = $this->PayAppID($client_type);

        $notify_url = $this->GetNotifyUrl($params);
        if(empty($notify_url))
        {
            return DataReturn('异步通知地址未配置', -1);
        }

        $description = trim(($params['site_name'] ?? '').'-'.($params['name'] ?? '订单'));        
        if($description === '-')
        {
            $description = $params['name'] ?? '订单支付';
        }
        if(function_exists('mb_strimwidth'))
        {
            $description = mb_strimwidth($description, 0, 120, '', 'UTF-8');
        }

        $currency = strtoupper($this->config['currency'] ?? 'HKD');
        $payload = [
            'description'            => $description,
            'out_trade_no'           => $params['order_no'],
            'notify_url'             => $notify_url,
            'trade_type'             => $trade_type,
            'merchant_category_code' => self::DEFAULT_MERCHANT_CATEGORY_CODE,
            'amount'                 => [
                'total'    => $this->AmountToCents($params['total_price']),
                'currency' => $currency,
            ],
        ];

        $use_service_provider = $this->IsServiceProviderMode();

        if($use_service_provider)
        {
            $payload['sp_appid'] = $this->config['sp_appid'];
            $payload['sp_mchid'] = $this->config['sp_mch_id'];
            $payload['sub_mchid'] = $this->config['sub_mch_id'];

            $sub_appid = $this->config['sub_appid'] ?? $appid;
            if(!empty($sub_appid))
            {
                $payload['sub_appid'] = $sub_appid;
            }
            if(empty($payload['sub_appid']))
            {
                return DataReturn('子商户AppID未配置', -1);
            }

            $auth_mchid = $this->config['sp_mch_id'];
        }
        else
        {
            if(empty($this->config['mch_id']))
            {
                return DataReturn('商户号未配置', -1);
            }
            if(empty($appid))
            {
                return DataReturn('AppID未配置', -1);
            }

            $payload['appid'] = $appid;
            $payload['mchid'] = $this->config['mch_id'];
            $auth_mchid = $this->config['mch_id'];
        }

        $this->SetRequestMchId($auth_mchid ?? '');

        if(!empty($params['attach']))
        {
            $payload['attach'] = $params['attach'];
        }

        $time_expire = $this->OrderAutoCloseTime();
        if(!empty($time_expire))
        {
            $payload['time_expire'] = $time_expire;
        }

        switch($trade_type)
        {
            case 'JSAPI':
                $openid = ($client_type == 'weixin')
                    ? ($params['user']['weixin_openid'] ?? '')
                    : ($params['user']['weixin_web_openid'] ?? '');
                if(empty($openid))
                {
                    return DataReturn('未获取到用户OpenId', -1);
                }
                $payer_key = $use_service_provider ? 'sub_openid' : 'openid';
                $payload['payer'] = [$payer_key => $openid];
                break;
            case 'MWEB':
                $payload['scene_info'] = [
                    'payer_client_ip' => GetClientIP(),
                    'h5_info' => [
                        'type' => 'Wap',
                    ],
                ];
                break;
            case 'NATIVE':
                // no extra fields
                break;
            case 'APP':
                // no extra fields
                break;
            default:
                return DataReturn('不支持的支付方式', -1);
        }

        $pay_appid = $use_service_provider ? ($payload['sub_appid'] ?? '') : ($payload['appid'] ?? '');

        return DataReturn('success', 0, [
            'trade_type'        => $trade_type,
            'payload'           => $payload,
            'pay_appid'         => $pay_appid,
            'pay_mchid'         => $auth_mchid ?? '',
            'service_provider'  => $use_service_provider,
        ]);
    }

    private function GetApiUrl($trade_type)
    {
        $base_url = 'https://apihk.mch.weixin.qq.com';
        $endpoint = [
            'JSAPI'  => '/v3/global/transactions/jsapi',
            'NATIVE' => '/v3/global/transactions/native',
            'APP'    => '/v3/global/transactions/app',
            'MWEB'   => '/v3/global/transactions/h5',
        ];
        return $base_url.($endpoint[$trade_type] ?? $endpoint['NATIVE']);
    }

    private function HandlePayResponse($context, $response, $params)
    {
        $trade_type = $context['trade_type'];
        $payload = $context['payload'];
        $pay_appid = $context['pay_appid'] ?? '';
        $pay_mchid = $context['pay_mchid'] ?? ($payload['mchid'] ?? '');
        $redirect_url = empty($params['redirect_url']) ? __MY_URL__ : $params['redirect_url'];

        switch($trade_type)
        {
            case 'NATIVE':
                if(empty($params['check_url']))
                {
                    return DataReturn('支付状态校验地址不能为空', -50);
                }
                if(empty($response['code_url']))
                {
                    return DataReturn($response['message'] ?? '支付接口异常', -1, $response);
                }
                if(APPLICATION == 'app')
                {
                    $return_data = [
                        'qrcode_url' => MyUrl('index/qrcode/index', ['content'=>urlencode(base64_encode($response['code_url']))]),
                        'order_no'   => $params['order_no'],
                        'name'       => '微信支付',
                        'msg'        => '打开微信APP扫一扫进行支付',
                        'check_url'  => $params['check_url'],
                    ];
                    return DataReturn('success', 0, $return_data);
                }
                $qr_params = [
                    'url'       => $response['code_url'],
                    'order_no'  => $params['order_no'],
                    'name'      => '微信支付',
                    'msg'       => '打开微信APP扫一扫进行支付',
                    'check_url' => $params['check_url'],
                ];
                MySession('payment_qrcode_data', $qr_params);
                return DataReturn('success', 0, MyUrl('index/pay/qrcode'));

            case 'MWEB':
                if(empty($response['h5_url']))
                {
                    return DataReturn($response['message'] ?? '支付接口异常', -1, $response);
                }
                if(!empty($params['order_id']))
                {
                    $redirect_url = (isset($this->config['is_h5_url_encode']) && $this->config['is_h5_url_encode'] == 1)
                        ? urlencode($redirect_url)
                        : $redirect_url;
                    $response['h5_url'] .= '&redirect_url='.$redirect_url;
                }
                return DataReturn('success', 0, $response['h5_url']);

            case 'JSAPI':
                if(empty($response['prepay_id']))
                {
                    return DataReturn($response['message'] ?? '支付接口异常', -1, $response);
                }
                if(empty($pay_appid))
                {
                    return DataReturn('调起支付AppID未配置', -1);
                }
                $pay_data = $this->BuildJsapiPayData($pay_appid, $response['prepay_id']);
                if($pay_data['code'] != 0)
                {
                    return $pay_data;
                }
                $pay_data = $pay_data['data'];
                if(APPLICATION == 'web' && IsWeixinEnv())
                {
                    die($this->PayHtml($pay_data, $redirect_url));
                }
                return DataReturn('success', 0, $pay_data);

            case 'APP':
                if(empty($response['prepay_id']))
                {
                    return DataReturn($response['message'] ?? '支付接口异常', -1, $response);
                }
                if(empty($pay_appid) || empty($pay_mchid))
                {
                    return DataReturn('调起支付参数缺失', -1);
                }
                $pay_data = $this->BuildAppPayData($pay_appid, $pay_mchid, $response['prepay_id']);
                if($pay_data['code'] != 0)
                {
                    return $pay_data;
                }
                return DataReturn('success', 0, $pay_data['data']);
        }

        return DataReturn('未处理的支付类型', -1);
    }

    private function BuildJsapiPayData($appid, $prepay_id)
    {
        $nonce_str = $this->GenerateNonceStr();
        $timestamp = (string) time();
        $package = 'prepay_id='.$prepay_id;
        $message = $appid."\n".$timestamp."\n".$nonce_str."\n".$package."\n";

        $private_key = $this->GetPrivateKey();
        if($private_key === false)
        {
            return DataReturn('商户私钥未配置', -1);
        }

        if(!openssl_sign($message, $signature, $private_key, OPENSSL_ALGO_SHA256))
        {
            return DataReturn('支付签名生成失败', -1);
        }

        return DataReturn('success', 0, [
            'appId'     => $appid,
            'timeStamp' => $timestamp,
            'nonceStr'  => $nonce_str,
            'package'   => $package,
            'signType'  => 'RSA',
            'paySign'   => base64_encode($signature),
        ]);
    }

    private function BuildAppPayData($appid, $mchid, $prepay_id)
    {
        $nonce_str = $this->GenerateNonceStr();
        $timestamp = (string) time();
        $message = $appid."\n".$timestamp."\n".$nonce_str."\n".$prepay_id."\n";

        $private_key = $this->GetPrivateKey();
        if($private_key === false)
        {
            return DataReturn('商户私钥未配置', -1);
        }

        if(!openssl_sign($message, $signature, $private_key, OPENSSL_ALGO_SHA256))
        {
            return DataReturn('支付签名生成失败', -1);
        }

        return DataReturn('success', 0, [
            'appid'     => $appid,
            'partnerid' => $mchid,
            'prepayid'  => $prepay_id,
            'package'   => 'Sign=WXPay',
            'noncestr'  => $nonce_str,
            'timestamp' => $timestamp,
            'sign'      => base64_encode($signature),
        ]);
    }

    private function PayHtml($pay_data, $redirect_url)
    {
        return '<html>
            <head>
                <meta http-equiv="content-type" content="text/html;charset=utf-8"/>
                <title>微信安全支付</title>
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1, maximum-scale=1">
            </head>
            <body></body>
            <script type="text/javascript">
                function onBridgeReady()
                {
                    WeixinJSBridge.invoke(
                        "getBrandWCPayRequest",
                        {
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
                    if(document.addEventListener)
                    {
                        document.addEventListener("WeixinJSBridgeReady", onBridgeReady, false);
                    }
                    else if(document.attachEvent)
                    {
                        document.attachEvent("WeixinJSBridgeReady", onBridgeReady);
                        document.attachEvent("onWeixinJSBridgeReady", onBridgeReady);
                    }
                }
                else
                {
                    onBridgeReady();
                }
            </script>
        </html>';
    }

    public function PayAppID($client_type)
    {
        $map = [
            'weixin'  => $this->config['mini_appid'] ?? '',
            'ios'     => $this->config['app_appid'] ?? '',
            'android' => $this->config['app_appid'] ?? '',
        ];
        return array_key_exists($client_type, $map) ? $map[$client_type] : ($this->config['appid'] ?? '');
    }

    public function OrderAutoCloseTime()
    {
        $minutes = intval(MyC('common_order_close_limit_time', 30, true));
        return date('c', time() + ($minutes * 60));
    }

    private function GetNotifyUrl($params)
    {
        if(empty($params['notify_url']))
        {
            return '';
        }
        $url = $params['notify_url'];
        if(stripos($url, 'https://') !== 0)
        {
            $url = 'https://'.ltrim(preg_replace('#^https?://#i', '', $url), '/');
        }
        return $url;
    }

    private function GetTradeType()
    {
        $client_type = $this->GetApplicationClientType();
        $h5_mode = (isset($this->config['is_h5_pay_native_mode']) && $this->config['is_h5_pay_native_mode'] == 1) ? 'NATIVE' : 'MWEB';
        $types = [
            'pc'      => 'NATIVE',
            'weixin'  => 'JSAPI',
            'h5'      => $h5_mode,
            'toutiao' => 'MWEB',
            'qq'      => 'MWEB',
            'app'     => 'APP',
            'ios'     => 'APP',
            'android' => 'APP',
        ];
        if($client_type == 'h5')
        {
            if(IsWeixinEnv())
            {
                $types['h5'] = $types['weixin'];
            }
            elseif(!IsMobile())
            {
                $types['h5'] = $types['pc'];
            }
        }
        return $types[$client_type] ?? '';
    }

    private function GetApplicationClientType()
    {
        $client_type = APPLICATION_CLIENT_TYPE;
        if($client_type == 'pc' && IsMobile())
        {
            $client_type = 'h5';
        }
        return $client_type;
    }

    public function Respond($params = [])
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if(!$data || !isset($data['resource']))
        {
            return DataReturn('回调数据格式错误', -1);
        }

        if(!$this->VerifyCallbackHeaders($input))
        {
            return DataReturn('回调签名验证失败', -1);
        }

        $decrypted = $this->DecryptV3Callback($data['resource']);
        if(!$decrypted)
        {
            return DataReturn('回调数据解密失败', -1);
        }

        if(isset($decrypted['trade_state']) && $decrypted['trade_state'] === 'SUCCESS')
        {
            return DataReturn('支付成功', 0, $this->ReturnData($decrypted));
        }

        return DataReturn('支付状态异常', -100, $decrypted);
    }

    private function DecryptV3Callback($resource)
    {
        if(empty($resource['ciphertext']) || empty($resource['nonce']) || !isset($resource['associated_data']))
        {
            return false;
        }

        $ciphertext = base64_decode($resource['ciphertext']);
        if($ciphertext === false)
        {
            return false;
        }

        $tag = substr($ciphertext, -16);
        $ciphertext = substr($ciphertext, 0, -16);

        $key = $this->config['key'] ?? '';
        if(strlen($key) !== 32)
        {
            return false;
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $resource['nonce'],
            $tag,
            $resource['associated_data']
        );

        if($plaintext === false)
        {
            return false;
        }

        return json_decode($plaintext, true);
    }

    private function VerifyCallbackHeaders($body)
    {
        $serial = $_SERVER['HTTP_WECHATPAY_SERIAL'] ?? '';
        $signature = $_SERVER['HTTP_WECHATPAY_SIGNATURE'] ?? '';
        $timestamp = $_SERVER['HTTP_WECHATPAY_TIMESTAMP'] ?? '';
        $nonce = $_SERVER['HTTP_WECHATPAY_NONCE'] ?? '';

        if($serial === '' || $signature === '' || $timestamp === '' || $nonce === '')
        {
            // 缺少验签头部时，仅在未配置平台证书的场景下放行
            return empty($this->config['platform_certs']);
        }

        return $this->VerifySignatureValue($serial, $signature, $timestamp, $nonce, $body);
    }

    private function VerifySignatureValue($serial, $signature, $timestamp, $nonce, $body)
    {
        $certs = $this->GetPlatformCertificates();
        if(empty($certs) || !isset($certs[$serial]))
        {
            return false;
        }

        $decoded_signature = base64_decode($signature, true);
        if($decoded_signature === false)
        {
            return false;
        }

        $public_key = openssl_pkey_get_public($certs[$serial]);
        if($public_key === false)
        {
            return false;
        }

        $message = $timestamp."\n".$nonce."\n".$body."\n";
        $verified = openssl_verify($message, $decoded_signature, $public_key, OPENSSL_ALGO_SHA256);

        if(is_resource($public_key) && PHP_VERSION_ID < 80000)
        {
            openssl_free_key($public_key);
        }

        if($verified !== 1)
        {
            return false;
        }

        if(!is_numeric($timestamp))
        {
            return false;
        }

        return abs(time() - (int)$timestamp) <= self::MAXIMUM_CLOCK_OFFSET;
    }

    private function VerifyResponseSignature(array $headers, string $body, ?string $path = null)
    {
        if(empty($this->config['platform_certs']))
        {
            return true;
        }

        $serial = $this->GetHeaderValue($headers, 'Wechatpay-Serial');
        $signature = $this->GetHeaderValue($headers, 'Wechatpay-Signature');
        $timestamp = $this->GetHeaderValue($headers, 'Wechatpay-Timestamp');
        $nonce = $this->GetHeaderValue($headers, 'Wechatpay-Nonce');

        if($serial === '' || $signature === '' || $timestamp === '' || $nonce === '')
        {
            return false;
        }

        return $this->VerifySignatureValue($serial, $signature, $timestamp, $nonce, $body);
    }

    private function GetHeaderValue(array $headers, string $name)
    {
        foreach($headers as $key=>$values)
        {
            if(strcasecmp($key, $name) === 0)
            {
                if(is_array($values))
                {
                    return reset($values);
                }
                return $values;
            }
        }
        return '';
    }

    private function ReturnData($data)
    {
        $data['trade_no'] = $data['transaction_id'] ?? '';
        $buyer = '';
        if(isset($data['payer']) && is_array($data['payer']))
        {
            $buyer = $data['payer']['openid'] ?? ($data['payer']['sub_openid'] ?? '');
        }
        $data['buyer_user'] = $buyer;
        $data['out_trade_no'] = $data['out_trade_no'] ?? '';
        $data['subject'] = $data['attach'] ?? '';
        $data['pay_price'] = isset($data['amount']['total']) ? $data['amount']['total'] / 100 : 0;
        $data['pay_currency'] = $data['amount']['currency'] ?? '';
        return $data;
    }

    public function Query($params = [])
    {
        $rules = [
            ['checked_type'=>'empty','key_name'=>'order_no','error_msg'=>'订单号不能为空'],
        ];
        $ret = ParamsChecked($params, $rules);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        $order_no = $params['order_no'];
        $use_service_provider = $this->IsServiceProviderMode();

        if($use_service_provider)
        {
            $sp_mchid = $this->config['sp_mch_id'] ?? '';
            $sub_mchid = $this->config['sub_mch_id'] ?? '';
            if(empty($sp_mchid) || empty($sub_mchid))
            {
                return DataReturn('机构商户号或子商户号未配置', -1);
            }
            $query = http_build_query([
                'sp_mchid' => $sp_mchid,
                'sub_mchid'=> $sub_mchid,
            ]);
            $auth_mchid = $sp_mchid;
        }
        else
        {
            if(empty($this->config['mch_id']))
            {
                return DataReturn('商户号未配置', -1);
            }
            $query = http_build_query(['mchid' => $this->config['mch_id']]);
            $auth_mchid = $this->config['mch_id'];
        }

        $this->SetRequestMchId($auth_mchid);

        $base_path = '/v3/global/transactions/out-trade-no/'.urlencode($order_no);
        $query_url = 'https://apihk.mch.weixin.qq.com'.$base_path.'?'.$query;

        $result = $this->HttpRequestV3($query_url, '', 'GET');
        if(!isset($result['success']) || $result['success'] !== true)
        {
            $body = isset($result['body']) && is_array($result['body']) ? $result['body'] : [];
            $message = $result['message'] ?? '订单查询接口异常';
            return DataReturn($message, -1, $body);
        }

        if(!$this->VerifyResponseSignature($result['headers'] ?? [], $result['raw_body'] ?? '', $base_path))
        {
            return DataReturn('订单查询返回数据验签失败', -1);
        }

        $body = isset($result['body']) && is_array($result['body']) ? $result['body'] : [];
        if(empty($body))
        {
            return DataReturn('订单查询返回数据为空', -1);
        }

        if(isset($body['trade_state']) && $body['trade_state'] === 'SUCCESS')
        {
            return DataReturn('支付成功', 0, $this->ReturnData($body));
        }

        return DataReturn('支付未完成', -100, $body);
    }

    public function Refund($params = [])
    {
        $rules = [
            ['checked_type'=>'empty','key_name'=>'order_no','error_msg'=>'订单号不能为空'],
            ['checked_type'=>'empty','key_name'=>'trade_no','error_msg'=>'交易平台订单号不能为空'],
            ['checked_type'=>'empty','key_name'=>'pay_price','error_msg'=>'支付金额不能为空'],
            ['checked_type'=>'empty','key_name'=>'refund_price','error_msg'=>'退款金额不能为空'],
        ];
        $ret = ParamsChecked($params, $rules);
        if($ret !== true)
        {
            return DataReturn($ret, -1);
        }

        if(empty($this->config['apiclient_key']) || empty($this->config['apiclient_cert']))
        {
            return DataReturn('请配置商户私钥和证书', -1);
        }

        $refund_reason = empty($params['refund_reason'])
            ? $params['order_no'].'订单退款'.$params['refund_price'].'元'
            : $params['refund_reason'];

        $use_service_provider = $this->IsServiceProviderMode();
        $currency = strtoupper($this->config['currency'] ?? 'HKD');
        $client_type = $params['client_type'] ?? $this->GetApplicationClientType();
        $appid = $this->PayAppID($client_type);

        if($use_service_provider)
        {
            $required = [
                'sp_appid' => $this->config['sp_appid'] ?? '',
                'sp_mchid' => $this->config['sp_mch_id'] ?? '',
                'sub_mchid'=> $this->config['sub_mch_id'] ?? '',
            ];
            foreach($required as $label=>$value)
            {
                if(empty($value))
                {
                    return DataReturn($label.'未配置', -1);
                }
            }
            $data = [
                'sp_appid'       => $required['sp_appid'],
                'sp_mchid'       => $required['sp_mchid'],
                'sub_mchid'      => $required['sub_mchid'],
                'transaction_id' => $params['trade_no'],
                'out_refund_no'  => $params['order_no'].GetNumberCode(),
                'reason'         => $refund_reason,
                'amount'         => [
                    'refund'   => $this->AmountToCents($params['refund_price']),
                    'total'    => $this->AmountToCents($params['pay_price']),
                    'currency' => $currency,
                ],
            ];

            if(!empty($params['order_no']))
            {
                $data['out_trade_no'] = $params['order_no'];
            }

            if(!empty($this->config['sub_appid']))
            {
                $data['sub_appid'] = $this->config['sub_appid'];
            }

            $auth_mchid = $required['sp_mchid'];
        }
        else
        {
            if(empty($this->config['mch_id']))
            {
                return DataReturn('商户号未配置', -1);
            }
            if(empty($appid))
            {
                return DataReturn('AppID未配置', -1);
            }

            $data = [
                'appid'         => $appid,
                'mchid'         => $this->config['mch_id'],
                'transaction_id' => $params['trade_no'],
                'out_refund_no'  => $params['order_no'].GetNumberCode(),
                'reason'         => $refund_reason,
                'amount'         => [
                    'refund'   => $this->AmountToCents($params['refund_price']),
                    'total'    => $this->AmountToCents($params['pay_price']),
                    'currency' => $currency,
                ],
            ];

            if(!empty($params['order_no']))
            {
                $data['out_trade_no'] = $params['order_no'];
            }

            $auth_mchid = $this->config['mch_id'];
        }

        $this->SetRequestMchId($auth_mchid);

        if(!empty($params['refund_source']))
        {
            $data['source'] = $params['refund_source'];
        }

        if(!empty($params['refund_notify_url']))
        {
            $data['notify_url'] = $params['refund_notify_url'];
        }

        if(!empty($params['refund_from']) && is_array($params['refund_from']))
        {
            $data['amount']['from'] = $params['refund_from'];
        }

        $refund_url = 'https://apihk.mch.weixin.qq.com/v3/global/refunds';
        $result = $this->HttpRequest($refund_url, json_encode($data, JSON_UNESCAPED_UNICODE));
        if(!isset($result['success']) || $result['success'] !== true)
        {
            $body = isset($result['body']) && is_array($result['body']) ? $result['body'] : [];
            $message = $result['message'] ?? '退款接口异常';
            return DataReturn($message, -1, $body);
        }

        if(!$this->VerifyResponseSignature($result['headers'] ?? [], $result['raw_body'] ?? '', '/v3/global/refunds'))
        {
            return DataReturn('退款返回数据验签失败', -1);
        }

        $body = isset($result['body']) && is_array($result['body']) ? $result['body'] : [];
        if(isset($body['status']))
        {
            $refund_data = [
                'out_trade_no'  => $body['out_trade_no'] ?? '',
                'trade_no'      => $body['transaction_id'] ?? '',
                'buyer_user'    => $body['refund_id'] ?? '',
                'refund_price'  => isset($body['amount']['refund']) ? $body['amount']['refund']/100 : 0.00,
                'refund_currency'=> $body['amount']['currency'] ?? '',
                'status'        => $body['status'],
                'return_params' => $body,
            ];
            return DataReturn('退款申请已受理', 0, $refund_data);
        }

        return DataReturn('退款接口异常', -1, $body);
    }

    private function AmountToCents($amount)
    {
        return (int) round((float) $amount * 100);
    }

    private function GenerateSignatureV3($method, $url, $body, $timestamp, $nonce)
    {
        $canonical_url = $url;
        if(($query = parse_url($url, PHP_URL_QUERY)))
        {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $canonical_url = $path.'?'.$query;
        }
        else
        {
            $canonical_url = parse_url($url, PHP_URL_PATH) ?: $url;
        }

        $message = implode("\n", [
            strtoupper($method),
            $canonical_url,
            $timestamp,
            $nonce,
            $body,
        ])."\n";

        $private_key = $this->GetPrivateKey();
        if($private_key === false)
        {
            return false;
        }

        $signature = '';
        if(!openssl_sign($message, $signature, $private_key, OPENSSL_ALGO_SHA256))
        {
            return false;
        }
        return base64_encode($signature);
    }

    private function BuildAuthorizationHeader($method, $url, $body)
    {
        $timestamp = time();
        $nonce = $this->GenerateNonceStr();
        $signature = $this->GenerateSignatureV3($method, $url, $body, $timestamp, $nonce);
        if($signature === false)
        {
            return false;
        }

        $serial = $this->GetMerchantSerialNumber();
        if(empty($serial))
        {
            return false;
        }

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%d",serial_no="%s"',
            $this->request_mch_id ?: ($this->config['mch_id'] ?? ''),
            $nonce,
            $signature,
            $timestamp,
            $serial
        );
    }

    private function GetPrivateKey()
    {
        if(empty($this->config['apiclient_key']))
        {
            return false;
        }

        $pem = $this->FormatPem($this->config['apiclient_key'], 'PRIVATE KEY');
        return openssl_pkey_get_private($pem);
    }

    private function GetMerchantSerialNumber()
    {
        if(empty($this->config['apiclient_cert']))
        {
            return '';
        }

        $pem = $this->FormatPem($this->config['apiclient_cert'], 'CERTIFICATE');
        $cert = openssl_x509_read($pem);
        if($cert === false)
        {
            return '';
        }
        $info = openssl_x509_parse($cert);
        if(is_resource($cert) && PHP_VERSION_ID < 80000)
        {
            openssl_x509_free($cert);
        }
        if(isset($info['serialNumberHex']))
        {
            return strtoupper($info['serialNumberHex']);
        }
        if(isset($info['serialNumber']))
        {
            return strtoupper(dechex($info['serialNumber']));
        }
        return '';
    }

    private function GetPlatformCertificates()
    {
        if(empty($this->config['platform_certs']))
        {
            return [];
        }

        $raw = trim($this->config['platform_certs']);
        if($raw === '')
        {
            return [];
        }

        $splits = preg_split('/(?=-----BEGIN CERTIFICATE-----)/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $certs = [];
        foreach($splits as $block)
        {
            $pem = $this->FormatPem($block, 'CERTIFICATE');
            $resource = openssl_x509_read($pem);
            if($resource === false)
            {
                continue;
            }

            $info = openssl_x509_parse($resource);
            if(is_resource($resource) && PHP_VERSION_ID < 80000)
            {
                openssl_x509_free($resource);
            }

            if(!isset($info['serialNumber']) && !isset($info['serialNumberHex']))
            {
                continue;
            }

            $serial = isset($info['serialNumberHex']) ? strtoupper($info['serialNumberHex']) : strtoupper(dechex($info['serialNumber']));
            $certs[$serial] = $pem;
        }

        return $certs;
    }

    private function FormatPem($value, $type)
    {
        $value = trim($value);
        if(strpos($value, '-----BEGIN') !== false)
        {
            return $value;
        }
        $header = "-----BEGIN {$type}-----\n";
        $footer = "\n-----END {$type}-----";
        $body = chunk_split(str_replace(["\r", "\n", ' '], '', $value), 64, "\n");
        return $header.$body.$footer;
    }

    private function GenerateNonceStr($length = 32)
    {
        try
        {
            return substr(bin2hex(random_bytes($length)), 0, $length);
        }
        catch(\Exception $e)
        {
            return md5(uniqid('', true));
        }
    }

    private function HttpRequestV3($url, $data = '', $method = 'POST', $second = 30)
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ShopXO-WeChatPay-v3/2.0.0',
        ];

        $this->writeLog('request', [
            'method' => strtoupper($method),
            'url'    => $url,
            'body'   => $data,
        ]);

        $auth = $this->BuildAuthorizationHeader($method, $url, $data);
        if($auth === false)
        {
            return [
                'success'   => false,
                'message'   => '签名或证书配置异常',
                'http_code' => 0,
                'headers'   => [],
                'body'      => [],
                'raw_body'  => '',
            ];
        }
        $headers[] = 'Authorization: '.$auth;

        $response_headers = [];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => $second,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADERFUNCTION => function($ch, $header_line) use (&$response_headers) {
                $length = strlen($header_line);
                $header = trim($header_line);
                if($header === '' || strpos($header, ':') === false)
                {
                    return $length;
                }
                [$name, $value] = explode(':', $header, 2);
                $name = trim($name);
                $value = trim($value);
                if(isset($response_headers[$name]))
                {
                    if(is_array($response_headers[$name]))
                    {
                        $response_headers[$name][] = $value;
                    } else {
                        $response_headers[$name] = [$response_headers[$name], $value];
                    }
                } else {
                    $response_headers[$name] = $value;
                }
                return $length;
            },
        ];

        if(strtoupper($method) === 'POST')
        {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if($result === false)
        {
            $error = curl_error($ch);
            curl_close($ch);
            $this->writeLog('curl_failed', [
                'method' => strtoupper($method),
                'url'    => $url,
                'error'  => $error,
            ]);
            return [
                'success'   => false,
                'message'   => 'curl出错: '.$error,
                'http_code' => 0,
                'headers'   => $response_headers,
                'body'      => [],
                'raw_body'  => '',
            ];
        }

        curl_close($ch);

        $decoded = json_decode($result, true);
        $this->writeLog('response', [
            'method'    => strtoupper($method),
            'url'       => $url,
            'http_code' => $http_code,
            'body'      => $decoded ?? $result,
        ]);
        if($http_code >= 400)
        {
            $message = is_array($decoded) ? ($decoded['message'] ?? '微信支付返回异常['.$http_code.']') : '微信支付返回异常['.$http_code.']';
            return [
                'success'   => false,
                'message'   => $message,
                'http_code' => $http_code,
                'headers'   => $response_headers,
                'body'      => is_array($decoded) ? $decoded : [],
                'raw_body'  => $result,
            ];
        }

        return [
            'success'   => true,
            'http_code' => $http_code,
            'headers'   => $response_headers,
            'body'      => is_array($decoded) ? $decoded : [],
            'raw_body'  => $result,
        ];
    }

    private function HttpRequest($url, $data, $use_cert = false, $second = 30)
    {
        if(strpos($url, '/v3/') !== false)
        {
            return $this->HttpRequestV3($url, $data, 'POST', $second);
        }

        $response_headers = [];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => $second,
            CURLOPT_HEADERFUNCTION => function($ch, $header_line) use (&$response_headers) {
                $length = strlen($header_line);
                $header = trim($header_line);
                if($header === '' || strpos($header, ':') === false)
                {
                    return $length;
                }
                [$name, $value] = explode(':', $header, 2);
                $name = trim($name);
                $value = trim($value);
                if(isset($response_headers[$name]))
                {
                    if(is_array($response_headers[$name]))
                    {
                        $response_headers[$name][] = $value;
                    } else {
                        $response_headers[$name] = [$response_headers[$name], $value];
                    }
                } else {
                    $response_headers[$name] = $value;
                }
                return $length;
            },
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $this->writeLog('request', [
            'method' => 'POST',
            'url'    => $url,
            'body'   => $data,
        ]);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($result === false)
        {
            $error = curl_error($ch);
            curl_close($ch);
            $this->writeLog('curl_failed', [
                'method' => 'POST',
                'url'    => $url,
                'error'  => $error,
            ]);
            return [
                'success'   => false,
                'message'   => 'curl出错: '.$error,
                'http_code' => 0,
                'headers'   => $response_headers,
                'body'      => [],
                'raw_body'  => '',
            ];
        }
        curl_close($ch);
        $decoded = json_decode($result, true);
        $this->writeLog('response', [
            'method'    => 'POST',
            'url'       => $url,
            'http_code' => $http_code,
            'body'      => $decoded ?? $result,
        ]);
        if($http_code >= 400)
        {
            return [
                'success'   => false,
                'message'   => '请求失败['.$http_code.']',
                'http_code' => $http_code,
                'headers'   => $response_headers,
                'body'      => is_array($decoded) ? $decoded : [],
                'raw_body'  => $result,
            ];
        }

        return [
            'success'   => true,
            'http_code' => $http_code,
            'headers'   => $response_headers,
            'body'      => is_array($decoded) ? $decoded : [],
            'raw_body'  => $result,
        ];
    }
}
?>
