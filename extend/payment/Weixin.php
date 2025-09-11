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
 * 微信支付（带签名与请求日志）
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
    
    // 当前账号
    private $current_account;
    
    // API版本
    private $api_version;
    
    // 多账号配置
    private $multi_configs;
    
    // 故障转移历史
    private $failover_history;

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
        // 加载外部配置文件
        $config_file = __DIR__ . '/wxconfig.php';
        $external_config = [];
        
        if (file_exists($config_file)) {
            $external_config = include($config_file);
        }
        
        // 使用主账号配置
        $main_config = $external_config['main'] ?? [];
        
        // 从配置文件加载V3私钥
        $v3_private_key = $main_config['v3']['private_key'] ?? '';
        
        // 构建默认配置
        $default_config = [
            // 基础配置
            'appid' => $main_config['appid'] ?? '',
            'mini_appid' => $main_config['mini_appid'] ?? '',
            'app_appid' => $main_config['app_appid'] ?? '',
            
            // V2 API配置
            'mch_id' => $main_config['v2']['mch_id'] ?? '',
            'key' => $main_config['v2']['key'] ?? '',
            
            // V3 API配置
            'v3_mch_id' => $main_config['v3']['mch_id'] ?? '',
            'v3_key' => $main_config['v3']['key'] ?? '',
            'v3_id' => $main_config['v3']['serial_no'] ?? '',
            'v3_appid' => $main_config['v3']['appid'] ?? '',
            'v3_mini_appid' => $main_config['v3']['mini_appid'] ?? '',
            'apiclient_cert' => $main_config['v3']['cert'] ?? '',
            'apiclient_key' => $v3_private_key,
            
            // 其他配置
            'agreement' => $main_config['agreement'] ?? 1,
            'is_h5_url_encode' => $main_config['is_h5_url_encode'] ?? 1,
            'is_h5_pay_native_mode' => $main_config['is_h5_pay_native_mode'] ?? 0,
            
            // 菜鸟配置
            'cainiao_enabled' => $main_config['cainiao']['enabled'] ?? false,
            'cainiao_environment' => $main_config['cainiao']['environment'] ?? 'sandbox',
            'cainiao_app_code' => $main_config['cainiao']['app_code'] ?? '',
            'cainiao_resource_code' => $main_config['cainiao']['resource_code'] ?? '',
            'cainiao_app_secret' => $main_config['cainiao']['app_secret'] ?? '',
            'cainiao_owner_user_id' => $main_config['cainiao']['owner_user_id'] ?? '14544',
            'cainiao_business_unit_id' => $main_config['cainiao']['business_unit_id'] ?? '',
            'cainiao_order_type' => $main_config['cainiao']['order_type'] ?? 'BONDED_WHS',
            'cainiao_order_source' => $main_config['cainiao']['order_source'] ?? '201',
            'cainiao_store_code' => $main_config['cainiao']['store_code'] ?? 'JIX230',
            'cainiao_shop_name' => $main_config['cainiao']['shop_name'] ?? 'ShopXO商城',
            'cainiao_shop_id' => $main_config['cainiao']['shop_id'] ?? '',
            'cainiao_log_to_db' => $main_config['cainiao']['log_to_db'] ?? true,
            'cainiao_warehouse_keywords' => $main_config['cainiao']['warehouse_keywords'] ?? ['菜鸟', 'cainiao'],
        ];
        
        // 合并用户传入的参数，但保护重要的V3私钥不被空值覆盖
        $this->config = array_merge($default_config, $params);
        
        // 确保V3私钥不被空值覆盖
        if (empty($this->config['apiclient_key']) && !empty($v3_private_key)) {
            $this->config['apiclient_key'] = $v3_private_key;
        }
        
        // 记录配置加载状态
        $this->WriteLog('WeChat_config_loaded', [
            'config_file_exists' => file_exists($config_file),
            'v3_private_key_loaded' => !empty($v3_private_key),
            'v3_private_key_length' => strlen($v3_private_key),
            'api_config_status' => [
                'v2_ready' => !empty($this->config['key']) && !empty($this->config['mch_id']),
                'v3_ready' => !empty($this->config['v3_key']) && !empty($this->config['apiclient_key']) && !empty($this->config['v3_mch_id'])
            ],
            'wechat_payment_config' => [
                'v2_api' => [
                    'mini_program_appid' => $this->config['mini_appid'] ?? '',
                    'wechat_pay_mchid' => $this->MaskKey($this->config['mch_id'] ?? '')
                ],
                'v3_api' => [
                    'mini_program_appid' => $this->config['v3_mini_appid'] ?? '',
                    'wechat_pay_mchid' => $this->MaskKey($this->config['v3_mch_id'] ?? '')
                ]
            ]
        ]);
        
        // 初始化类属性
        $this->current_account = 'default';
        $this->api_version = 'auto';
        $this->multi_configs = [];
        $this->failover_history = [];
    }

    /**
     * 微信支付日志记录
     */
    private function WxLog($tag, $data = [])
    {
        // 统一使用 WriteLog 方法，保持向后兼容
        $this->WriteLog('WeChat_' . $tag, $data);
    }

    /** 密钥脱敏显示（前4后4） */
    private function MaskKey($key)
    {
        if(!$key) return '';
        $len = strlen($key);
        if($len <= 8) return '****';
        return substr($key, 0, 4).'****'.substr($key, -4);
    }

    /**
     * 检测强制指定的API版本（基于仓库）
     * @param   [array]           $params [支付参数]
     * @return  [string|null]     [强制的API版本 v2/v3 或 null]
     */
    private function DetectForceApiVersion($params)
    {
        // 检查商品仓库
        $warehouse_api_version = $this->DetectApiVersionByWarehouse($params);
        if ($warehouse_api_version !== null) {
            return $warehouse_api_version;
        }
        
        return null;
    }
    
    /**
     * 根据商品仓库检测API版本
     * @param   [array]           $params [支付参数]
     * @return  [string|null]     [API版本 v2/v3 或 null]
     */
    private function DetectApiVersionByWarehouse($params)
    {
        // 解析商品IDs
        $goods_ids = $this->ExtractGoodsIds($params);
        if (empty($goods_ids)) {
            $this->WriteLog('WeChat_Warehouse_Query', [
                'action' => '商品ID解析失败，默认使用V2 API',
                'params' => $params,
                'result' => 'no_goods_ids_fallback_v2'
            ]);
            return 'v2'; // 查不到仓库时默认使用V2
        }
        
        // 记录开始查询仓库的日志
        $this->WriteLog('WeChat_Warehouse_Query', [
            'action' => '开始查询商品仓库',
            'goods_ids' => $goods_ids,
            'goods_count' => count($goods_ids)
        ]);
        
        // 查询商品对应的仓库
        try {
            if (class_exists('\\think\\Db')) {
                // 获取商品的仓库ID
                $sql1 = "SELECT warehouse_id FROM sxo_warehouse_goods WHERE goods_id IN (" . implode(',', $goods_ids) . ") AND is_enable = 1";
                $warehouse_ids = \think\facade\Db::name('WarehouseGoods')
                    ->where(['goods_id' => $goods_ids, 'is_enable' => 1])
                    ->column('warehouse_id');
                
                // 记录商品仓库关联查询结果
                $this->WriteLog('WeChat_Warehouse_Query', [
                    'action' => '商品仓库关联查询',
                    'goods_ids' => $goods_ids,
                    'sql_1' => $sql1,
                    'warehouse_ids' => $warehouse_ids,
                    'found_relations' => count($warehouse_ids)
                ]);
                    
                if (empty($warehouse_ids)) {
                    $this->WriteLog('WeChat_Warehouse_Query', [
                        'action' => '未找到商品仓库关联，默认使用V2 API',
                        'goods_ids' => $goods_ids,
                        'result' => 'no_warehouse_relations_fallback_v2'
                    ]);
                    return 'v2'; // 查不到仓库时默认使用V2
                }
                
                // 获取仓库信息
                $sql2 = "SELECT id,name FROM sxo_warehouse WHERE id IN (" . implode(',', $warehouse_ids) . ") AND is_enable = 1 AND is_delete_time = 0";
                $warehouses = \think\facade\Db::name('Warehouse')
                    ->where(['id' => $warehouse_ids, 'is_enable' => 1, 'is_delete_time' => 0])
                    ->column('name', 'id');
                
                // 记录仓库信息查询结果
                $this->WriteLog('WeChat_Warehouse_Query', [
                    'action' => '仓库信息查询',
                    'warehouse_ids' => $warehouse_ids,
                    'sql_2' => $sql2,
                    'warehouses' => $warehouses,
                    'active_warehouses' => count($warehouses)
                ]);
                
                // 检查是否有菜鸟仓
                foreach ($warehouses as $warehouse_id => $warehouse_name) {
                    if (strpos($warehouse_name, '菜鸟') !== false) {
                        $this->WriteLog('WeChat_Warehouse_API_Select', [
                            'action' => '仓库API版本选择',
                            'warehouse_id' => $warehouse_id,
                            'warehouse_name' => $warehouse_name,
                            'api_version' => 'v3',
                            'reason' => '菜鸟仓使用V3 API',
                            'match_keyword' => '菜鸟'
                        ]);
                        return 'v3';
                    }
                }
                
                // 其他仓库使用V2
                if (!empty($warehouses)) {
                    $this->WriteLog('WeChat_Warehouse_API_Select', [
                        'action' => '仓库API版本选择',
                        'warehouses' => $warehouses,
                        'api_version' => 'v2', 
                        'reason' => '非菜鸟仓使用V2 API',
                        'warehouse_count' => count($warehouses)
                    ]);
                    return 'v2';
                }
            } else {
                $this->WriteLog('WeChat_Warehouse_Query', [
                    'action' => '数据库查询失败',
                    'error' => 'ThinkPHP Db class not exists',
                    'goods_ids' => $goods_ids
                ]);
            }
        } catch (\Exception $e) {
            $this->WriteLog('WeChat_Warehouse_Query_Error', [
                'action' => '仓库查询异常',
                'error' => $e->getMessage(),
                'goods_ids' => $goods_ids,
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
        }
        
        return null;
    }
    
    /**
     * 从支付参数中提取商品ID
     * @param   [array]           $params [支付参数]
     * @return  [array]           [商品ID数组]
     */
    private function ExtractGoodsIds($params)
    {
        $goods_ids = [];
        
        // 处理ShopXO订单的business_data格式
        if (!empty($params['business_data']) && is_array($params['business_data'])) {
            foreach ($params['business_data'] as $order) {
                if (!empty($order['detail']) && is_array($order['detail'])) {
                    foreach ($order['detail'] as $detail) {
                        if (isset($detail['goods_id'])) {
                            $goods_ids[] = $detail['goods_id'];
                        }
                    }
                }
            }
        }
        
        // 处理ShopXO格式的goods_data
        if (empty($goods_ids) && !empty($params['goods_data'])) {
            try {
                if (is_string($params['goods_data'])) {
                    $goods_data_decoded = base64_decode(urldecode($params['goods_data']));
                    $goods_data = json_decode($goods_data_decoded, true);
                } else {
                    $goods_data = $params['goods_data'];
                }
                
                if (is_array($goods_data)) {
                    foreach ($goods_data as $item) {
                        if (isset($item['goods_id'])) {
                            $goods_ids[] = $item['goods_id'];
                        }
                    }
                }
            } catch (\Exception $e) {
                // 解析失败，尝试其他方式
            }
        }
        
        // 处理购物车格式的ids
        if (empty($goods_ids) && !empty($params['ids'])) {
            try {
                if (class_exists('\\think\\Db')) {
                    $cart_ids = is_array($params['ids']) ? $params['ids'] : explode(',', $params['ids']);
                    $goods_ids = \think\facade\Db::name('Cart')
                        ->where(['id' => $cart_ids])
                        ->column('goods_id');
                }
            } catch (\Exception $e) {
                // 查询失败
            }
        }
        
        return array_unique($goods_ids);
    }
    
    /**
     * 设置当前使用的账号
     * @author  Devil
     * @param   [string]          $account_name [账号名称]
     */
    public function SetAccount($account_name)
    {
        if (empty($this->multi_configs) || !isset($this->multi_configs['accounts'][$account_name])) {
            throw new \Exception('账号配置不存在: ' . $account_name);
        }
        
        $account_config = $this->multi_configs['accounts'][$account_name];
        
        // 检查账号状态
        if ($account_config['status'] !== 'active') {
            throw new \Exception('账号状态异常: ' . $account_name . ' (' . $account_config['status'] . ')');
        }
        
        $this->current_account = $account_name;
        
        // 构建配置数组
        $this->config = $this->BuildConfigFromAccount($account_config);
        
        // 记录账号切换日志
        $this->LogAccountSwitch($account_name, $account_config);
    }
    
    /**
     * 从账号配置构建config数组
     * @author  Devil
     * @param   [array]           $account_config [账号配置]
     */
    private function BuildConfigFromAccount($account_config)
    {
        $config = [];
        
        // 基础配置
        $config = array_merge($config, $account_config['basic']);
        
        // V2配置
        if ($account_config['v2']['enabled']) {
            $config['key'] = $account_config['v2']['key'];
            $config['sign_type'] = $account_config['v2']['sign_type'] ?? 'MD5';
        }
        
        // V3配置
        if ($account_config['v3']['enabled']) {
            $config['v3_key'] = $account_config['v3']['key'];
            $config['v3_id'] = $account_config['v3']['serial_no'];
            $config['apiclient_cert'] = $account_config['v3']['cert'];
            $config['apiclient_key'] = $account_config['v3']['private_key'];
        }
        
        // 额外配置
        $config = array_merge($config, $account_config['extra']);
        
        return $config;
    }
    
    /**
     * 初始化旧版本配置（兼容性）
     * @author  Devil
     * @param   [array]           $params [配置参数]
     */
    private function InitLegacyConfig($params)
    {
        // 只写死 API V3 相关的密钥和证书配置
        // 其他配置（AppID、商户号、V2密钥等）从现有微信支付插件配置中获取
        $hardcoded_config = [
            // API V3密钥（启用 V3 API）
            'v3_id' => '你的32位V3API ID', // 请替换为你的真实V3 API序列号
            'v3_key' => '你的32位V3API密钥', // 请替换为你的真实V3 API密钥
            
            // 商户证书内容（apiclient_cert.pem）- V3 API 和退款操作必需
            'apiclient_cert' => '-----BEGIN CERTIFICATE-----
你的证书内容
-----END CERTIFICATE-----', // 请替换为你的真实证书内容
            
            // 商户私钥内容（apiclient_key.pem）- V3 API 和退款操作必需
            'apiclient_key' => '-----BEGIN PRIVATE KEY-----
你的私钥内容
-----END PRIVATE KEY-----', // 请替换为你的真实私钥内容
        ];
        
        // 将写死的V3配置与传入的插件配置参数合并
        // 传入的参数（如 appid、mini_appid、mch_id、key 等）优先级更高
        $this->config = array_merge($hardcoded_config, $params);
        $this->current_account = 'legacy';
    }
    
    /**
     * 记录密钥配置日志
     * @author  Devil
     * @version 1.0.0
     */
    private function LogConfig()
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => '密钥配置初始化',
            'current_account' => $this->current_account ?? 'unknown',
            'multi_config_enabled' => !empty($this->multi_configs),
            'config_info' => [
                'mch_id' => $this->config['mch_id'] ?? '未配置',
                'appid' => $this->config['appid'] ?? '未配置', 
                'mini_appid' => $this->config['mini_appid'] ?? '未配置',
                'has_v2_key' => !empty($this->config['key']),
                'v2_key_masked' => !empty($this->config['key']) ? substr($this->config['key'], 0, 8) . '***' : '未配置',
                'has_v3_key' => !empty($this->config['v3_key']),
                'v3_key_masked' => !empty($this->config['v3_key']) ? substr($this->config['v3_key'], 0, 8) . '***' : '未配置',
                'v3_id' => $this->config['v3_id'] ?? '未配置',
                'has_cert' => !empty($this->config['apiclient_cert']),
                'has_private_key' => !empty($this->config['apiclient_key'])
            ]
        ];
        $this->WriteLog('WeChat_Config', $log_data);
    }
    
    /**
     * 记录账号切换日志
     * @author  Devil
     * @param   [string]          $account_name [账号名称]
     * @param   [array]           $account_config [账号配置]
     */
    private function LogAccountSwitch($account_name, $account_config)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => '账号切换',
            'account_name' => $account_name,
            'account_info' => [
                'name' => $account_config['name'],
                'description' => $account_config['description'],
                'priority' => $account_config['priority'],
                'status' => $account_config['status'],
                'v2_enabled' => $account_config['v2']['enabled'] ?? false,
                'v3_enabled' => $account_config['v3']['enabled'] ?? false,
                'scenarios' => $account_config['scenarios'] ?? []
            ]
        ];
        $this->WriteLog('WeChat_Account_Switch', $log_data);
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

        // 根据用户备注强制选择API版本
        $force_api_version = $this->DetectForceApiVersion($params);
        
        // 检测使用 V3 API 还是 V2 API
        $use_v3 = false;
        if ($force_api_version === 'v3') {
            $use_v3 = true;
            $this->WriteLog('WeChat_force_api_v3', ['reason' => 'warehouse_based', 'api_version' => 'v3']);
        } elseif ($force_api_version === 'v2') {
            $use_v3 = false;
            $this->WriteLog('WeChat_force_api_v2', ['reason' => 'warehouse_based', 'api_version' => 'v2']);
        } else {
            // 默认逻辑：通过ShouldUseV3Api判断
            $use_v3 = $this->ShouldUseV3Api();
            $this->WriteLog('WeChat_auto_api_select', ['use_v3' => $use_v3, 'has_v3_key' => !empty($this->config['v3_key'])]);
        }
        
        // 记录最终API选择结果
        $this->WriteLog('WeChat_final_api_choice', [
            'selected_api_version' => $use_v3 ? 'V3' : 'V2',
            'reason' => $force_api_version ? 'warehouse_based' : 'auto_detect',
            'payment_summary' => [
                'wechat_api_version' => $use_v3 ? 'V3' : 'V2',
                'mini_program_appid' => $use_v3 ? ($this->config['v3_mini_appid'] ?? '') : ($this->config['mini_appid'] ?? ''),
                'wechat_pay_mchid' => $use_v3 ? ($this->config['v3_mch_id'] ?? '') : ($this->config['mch_id'] ?? '')
            ]
        ]);
        
        if($use_v3)
        {
            return $this->PayV3($params);
        } else {
            return $this->PayV2($params);
        }
    }
    
    /**
     * 根据业务场景自动选择账号
     * @author  Devil
     * @param   [array]           $params [支付参数]
     */
    private function AutoSelectAccount($params)
    {
        if (empty($this->multi_configs)) {
            return; // 未启用多账号配置
        }
        
        // 如果已经指定了账号，不再自动选择
        if (isset($params['force_account'])) {
            return;
        }
        
        // 优先根据商品特性选择账号和API版本
        $product_result = $this->SelectAccountByProduct($params);
        if ($product_result) {
            return;
        }
        
        // 其次根据业务场景选择账号
        $scene = $this->DetectPayScene($params);
        $scene_mapping = $this->multi_configs['scene_mapping'] ?? [];
        
        if (isset($scene_mapping[$scene])) {
            $target_account = $scene_mapping[$scene];
            if ($target_account !== $this->current_account) {
                try {
                    $this->SetAccount($target_account);
                } catch (\Exception $e) {
                    // 切换失败，记录日志但继续使用当前账号
                    $this->WriteLog('WeChat_Account_Switch_Failed', [
                        'time' => date('Y-m-d H:i:s'),
                        'scene' => $scene,
                        'target_account' => $target_account,
                        'current_account' => $this->current_account,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
    
    /**
     * 根据商品特性选择账号和API版本
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @return  [boolean]         [是否成功选择]
     */
    private function SelectAccountByProduct($params)
    {
        if (empty($this->multi_configs['product_api_rules']['enabled'])) {
            return false;
        }
        
        // 先丰富商品信息
        $enriched_params = $this->EnrichProductInfo($params);
        
        // 检查是否有强制API版本指定（最高优先级）
        if (!empty($enriched_params['force_api_version'])) {
            $product_rule = [
                'api_version' => $enriched_params['force_api_version'],
                'account_preference' => $enriched_params['force_account_preference'] ?? 'main',
                'match_type' => 'force_override',
                'match_reason' => $enriched_params['force_reason'] ?? '强制指定API版本'
            ];
        } else {
            // 使用常规规则分析
            $rules = $this->multi_configs['product_api_rules'];
            $product_rule = $this->AnalyzeProductRule($enriched_params, $rules);
            
            if (!$product_rule) {
                return false;
            }
        }
        
        try {
            // 根据商品规则选择账号
            $target_account = $product_rule['account_preference'];
            if ($target_account !== $this->current_account) {
                $this->SetAccount($target_account);
            }
            
            // 设置API版本偏好
            $this->SetApiVersionPreference($product_rule['api_version']);
            
            // 记录商品规则匹配日志
            $this->LogProductRuleMatch($enriched_params, $product_rule);
            
            return true;
            
        } catch (\Exception $e) {
            $this->WriteLog('WeChat_Product_Rule_Failed', [
                'time' => date('Y-m-d H:i:s'),
                'original_params' => $params,
                'enriched_params' => $enriched_params,
                'product_rule' => $product_rule,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 丰富商品信息（从数据库或其他数据源获取）
     * @author  Devil
     * @param   [array]           $params [原始参数]
     * @return  [array]           [丰富后的参数]
     */
    private function EnrichProductInfo($params)
    {
        $enriched = $params;
        
        try {
            // 如果是ShopXO请求格式，先解析goods_data
            if (isset($params['goods_data']) && isset($params['application_client_type'])) {
                $shopxo_info = $this->ParseShopXORequest($params);
                $enriched = array_merge($enriched, $shopxo_info);
            }
            
            // 如果有商品ID，从数据库获取更多信息
            if (!empty($enriched['product_id']) && class_exists('\\think\\Db')) {
                $product_info = $this->GetProductFromDatabase($enriched['product_id']);
                if ($product_info) {
                    $enriched = array_merge($enriched, $product_info);
                }
            }
            
            // 从商品名称推断标签
            $enriched['auto_tags'] = $this->ExtractTagsFromName($enriched['name'] ?? '');
            if (!empty($enriched['auto_tags'])) {
                $existing_tags = $enriched['tags'] ?? [];
                $enriched['tags'] = array_unique(array_merge($existing_tags, $enriched['auto_tags']));
            }
            
            // 根据价格推断风险等级
            if (empty($enriched['risk_level']) && !empty($enriched['total_price'])) {
                $enriched['risk_level'] = $this->InferRiskLevel($enriched['total_price'], $enriched);
            }
            
            // 标准化分类名称
            if (!empty($enriched['category'])) {
                $enriched['category'] = $this->NormalizeCategory($enriched['category']);
            }
            
        } catch (\Exception $e) {
            // 信息丰富失败不影响主流程，记录日志即可
            $this->WriteLog('WeChat_Product_Enrich_Failed', [
                'time' => date('Y-m-d H:i:s'),
                'params' => $params,
                'error' => $e->getMessage()
            ]);
        }
        
        return $enriched;
    }
    
    /**
     * 从数据库获取商品信息
     * @author  Devil
     * @param   [string]          $product_id [商品ID]
     * @return  [array|null]      [商品信息]
     */
    private function GetProductFromDatabase($product_id)
    {
        try {
            // 尝试使用ThinkPHP的Db类
            if (class_exists('\\think\\Db')) {
                $db = \think\Db::name('goods');
                $product = $db->where('id', $product_id)->find();
                
                if ($product) {
                    $result = [
                        'sku' => $product['model'] ?? '',
                        'category_id' => $product['category_id'] ?? 0,
                        'brand_id' => $product['brand_id'] ?? 0,
                        'inventory_type' => $product['inventory_type'] ?? 0, // 0实物 1虚拟
                    ];
                    
                    // 获取分类信息
                    if (!empty($product['category_id'])) {
                        $category = \think\Db::name('goods_category')
                                      ->where('id', $product['category_id'])
                                      ->find();
                        if ($category) {
                            $result['category'] = $category['name'];
                            $result['category_path'] = $category['bg_color'] ?? ''; // 假设这里存储分类路径
                        }
                    }
                    
                    // 获取商品标签
                    $tags = \think\Db::name('goods_content_app')
                              ->where('goods_id', $product_id)
                              ->column('content');
                    if ($tags) {
                        $result['db_tags'] = $tags;
                    }
                    
                    return $result;
                }
            }
        } catch (\Exception $e) {
            // 数据库查询失败，返回null
        }
        
        return null;
    }
    
    /**
     * 从商品名称中提取标签
     * @author  Devil
     * @param   [string]          $product_name [商品名称]
     * @return  [array]           [标签数组]
     */
    private function ExtractTagsFromName($product_name)
    {
        if (empty($product_name)) {
            return [];
        }
        
        $tags = [];
        
        // 从配置中获取前端验证规则的关键词
        $frontend_rules = $this->multi_configs['frontend_config']['validation_rules'] ?? [];
        
        // 检查数字商品关键词
        foreach ($frontend_rules['digital_keywords'] ?? [] as $keyword) {
            if (strpos($product_name, $keyword) !== false) {
                $tags[] = '数字商品';
                break;
            }
        }
        
        // 检查奢侈品关键词
        foreach ($frontend_rules['luxury_keywords'] ?? [] as $keyword) {
            if (strpos($product_name, $keyword) !== false) {
                $tags[] = '奢侈品';
                break;
            }
        }
        
        // 检查金融关键词
        foreach ($frontend_rules['financial_keywords'] ?? [] as $keyword) {
            if (strpos($product_name, $keyword) !== false) {
                $tags[] = '金融产品';
                break;
            }
        }
        
        // 检查促销关键词
        foreach ($frontend_rules['promotional_keywords'] ?? [] as $keyword) {
            if (strpos($product_name, $keyword) !== false) {
                $tags[] = '促销商品';
                break;
            }
        }
        
        // 检查VIP关键词
        foreach ($frontend_rules['vip_keywords'] ?? [] as $keyword) {
            if (strpos($product_name, $keyword) !== false) {
                $tags[] = 'VIP商品';
                break;
            }
        }
        
        return array_unique($tags);
    }
    
    /**
     * 推断风险等级
     * @author  Devil
     * @param   [float]           $price [价格]
     * @param   [array]           $product_info [商品信息]
     * @return  [string]          [风险等级]
     */
    private function InferRiskLevel($price, $product_info)
    {
        // 金融产品必然高风险
        if (in_array('金融产品', $product_info['tags'] ?? []) || 
            in_array($product_info['category'] ?? '', ['理财', '保险', '基金'])) {
            return 'high_risk';
        }
        
        // 数字商品中等风险
        if (in_array('数字商品', $product_info['tags'] ?? []) || 
            $product_info['inventory_type'] == 1) {
            return 'medium_risk';
        }
        
        // 按价格区间判断
        if ($price >= 5000) {
            return 'high_risk';
        } elseif ($price >= 500) {
            return 'medium_risk';
        } else {
            return 'low_risk';
        }
    }
    
    /**
     * 标准化分类名称
     * @author  Devil
     * @param   [string]          $category [分类名称]
     * @return  [string]          [标准化后的分类]
     */
    private function NormalizeCategory($category)
    {
        // 定义分类映射表
        $category_mapping = [
            '服装鞋帽' => '服装',
            '男装' => '服装',
            '女装' => '服装',
            '童装' => '服装',
            '大家电' => '家电',
            '小家电' => '家电',
            '数码产品' => '电子产品',
            '手机' => '电子产品',
            '电脑' => '电子产品',
        ];
        
        return $category_mapping[$category] ?? $category;
    }
    
    /**
     * 解析ShopXO请求格式
     * @author  Devil
     * @param   [array]           $params [请求参数]
     * @return  [array]           [解析后的商品信息]
     */
    private function ParseShopXORequest($params)
    {
        $result = [];
        
        try {
            // 解码goods_data
            if (!empty($params['goods_data'])) {
                $goods_data_decoded = base64_decode(urldecode($params['goods_data']));
                $goods_data = json_decode($goods_data_decoded, true);
                
                if (!empty($goods_data) && is_array($goods_data)) {
                    $first_goods = $goods_data[0]; // 取第一个商品
                    
                    $result['product_id'] = $first_goods['goods_id'] ?? '';
                    $result['quantity'] = $first_goods['stock'] ?? 1;
                    $result['specs'] = $first_goods['spec'] ?? [];
                }
            }
            
            // 获取客户端类型
            if (!empty($params['application_client_type'])) {
                $result['client_type'] = $params['application_client_type'];
                
                // 根据客户端类型设置初始偏好
                if ($params['application_client_type'] === 'weixin') {
                    $result['client_preference'] = [
                        'api_version' => 'v3',
                        'account_preference' => 'main',
                        'reason' => '微信小程序优先使用V3 API'
                    ];
                }
            }
            
            // 获取业务类型
            if (!empty($params['buy_type'])) {
                $result['buy_type'] = $params['buy_type'];
                
                // 根据业务类型设置策略
                switch ($params['buy_type']) {
                    case 'goods':
                        // 商品购买自动判断
                        break;
                    case 'recharge':
                        $result['force_api_version'] = 'v3';
                        $result['force_reason'] = '充值类交易强制使用V3';
                        break;
                    case 'membership':
                        $result['force_api_version'] = 'v3';
                        $result['force_reason'] = '会员类交易强制使用V3';
                        break;
                }
            }
            
            // 获取支付方式ID
            if (!empty($params['payment_id'])) {
                $result['payment_method_id'] = $params['payment_id'];
                
                // 根据支付方式设置偏好（可配置）
                $payment_preferences = $this->GetPaymentMethodPreferences();
                if (isset($payment_preferences[$params['payment_id']])) {
                    $result['payment_preference'] = $payment_preferences[$params['payment_id']];
                }
            }
            
            
            // 添加其他辅助信息
            $result['is_points_payment'] = !empty($params['is_points']);
            $result['has_address'] = !empty($params['address_id']);
            $result['is_shopxo_request'] = true;
            
            // 记录ShopXO请求解析日志
            $this->LogShopXORequestParsing($params, $result);
            
        } catch (\Exception $e) {
            $this->WriteLog('WeChat_ShopXO_Parse_Failed', [
                'time' => date('Y-m-d H:i:s'),
                'params' => $params,
                'error' => $e->getMessage()
            ]);
        }
        
        return $result;
    }
    
    /**
     * 获取支付方式偏好配置
     * @author  Devil
     * @return  [array]           [支付方式偏好映射]
     */
    private function GetPaymentMethodPreferences()
    {
        // 可以从配置文件或数据库获取
        return [
            '1' => ['api_version' => 'v2', 'account_preference' => 'backup', 'reason' => '普通支付'],
            '2' => ['api_version' => 'v3', 'account_preference' => 'main', 'reason' => '高级支付'],
            '3' => ['api_version' => 'v3', 'account_preference' => 'vip', 'reason' => 'VIP支付'],
            '4' => ['api_version' => 'v3', 'account_preference' => 'main', 'reason' => '企业支付'],
            '5' => ['api_version' => 'v3', 'account_preference' => 'main', 'reason' => '大额支付'],
        ];
    }
    
    /**
     * 记录ShopXO请求解析日志
     * @author  Devil
     * @param   [array]           $params [原始参数]
     * @param   [array]           $result [解析结果]
     */
    private function LogShopXORequestParsing($params, $result)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => 'ShopXO请求解析',
            'original_request' => [
                'buy_type' => $params['buy_type'] ?? '',
                'payment_id' => $params['payment_id'] ?? '',
                'application_client_type' => $params['application_client_type'] ?? '',
                'goods_data_raw' => substr($params['goods_data'] ?? '', 0, 100) . '...',
            ],
            'parsed_result' => [
                'product_id' => $result['product_id'] ?? '',
                'client_type' => $result['client_type'] ?? '',
                'buy_type' => $result['buy_type'] ?? '',
                'quantity' => $result['quantity'] ?? 0,
                'client_preference' => $result['client_preference'] ?? null,
                'payment_preference' => $result['payment_preference'] ?? null,
            ]
        ];
        $this->WriteLog('WeChat_ShopXO_Request_Parse', $log_data);
    }
    
    /**
     * 分析商品规则
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [array]           $rules [规则配置]
     * @return  [array|false]     [匹配的规则或false]
     */
    private function AnalyzeProductRule($params, $rules)
    {
        // 1. 精确匹配特定商品
        if ($specific_rule = $this->MatchSpecificProduct($params, $rules['specific_products'] ?? [])) {
            return $specific_rule;
        }
        
        // 2. 商品分类匹配
        if ($category_rule = $this->MatchProductCategory($params, $rules['category_rules'] ?? [])) {
            return $category_rule;
        }
        
        // 3. 商品属性匹配
        if ($attribute_rule = $this->MatchProductAttribute($params, $rules['attribute_rules'] ?? [])) {
            return $attribute_rule;
        }
        
        // 4. 默认规则
        return $rules['default_rule'] ?? false;
    }
    
    /**
     * 匹配特定商品
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [array]           $specific_rules [特定商品规则]
     * @return  [array|false]     [匹配的规则或false]
     */
    private function MatchSpecificProduct($params, $specific_rules)
    {
        // 匹配商品ID
        if (isset($params['product_id']) && isset($specific_rules['product_ids'][$params['product_id']])) {
            return array_merge($specific_rules['product_ids'][$params['product_id']], [
                'match_type' => 'product_id',
                'match_value' => $params['product_id']
            ]);
        }
        
        // 匹配SKU
        if (isset($params['sku']) && isset($specific_rules['skus'][$params['sku']])) {
            return array_merge($specific_rules['skus'][$params['sku']], [
                'match_type' => 'sku',
                'match_value' => $params['sku']
            ]);
        }
        
        // 匹配关键词
        $product_name = $params['name'] ?? '';
        foreach ($specific_rules['keywords'] ?? [] as $keyword => $rule) {
            if (strpos($product_name, $keyword) !== false) {
                return array_merge($rule, [
                    'match_type' => 'keyword',
                    'match_value' => $keyword
                ]);
            }
        }
        
        return false;
    }
    
    /**
     * 匹配商品分类
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [array]           $category_rules [分类规则]
     * @return  [array|false]     [匹配的规则或false]
     */
    private function MatchProductCategory($params, $category_rules)
    {
        $product_category = $params['category'] ?? $params['product_category'] ?? '';
        
        foreach ($category_rules as $rule_name => $rule) {
            if (in_array($product_category, $rule['categories'])) {
                return array_merge($rule, [
                    'match_type' => 'category',
                    'match_value' => $product_category,
                    'rule_name' => $rule_name
                ]);
            }
        }
        
        return false;
    }
    
    /**
     * 匹配商品属性
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [array]           $attribute_rules [属性规则]
     * @return  [array|false]     [匹配的规则或false]
     */
    private function MatchProductAttribute($params, $attribute_rules)
    {
        // 价格规则
        if ($price_rule = $this->MatchPriceRule($params, $attribute_rules['price_based'] ?? [])) {
            return $price_rule;
        }
        
        // 标签规则
        if ($tag_rule = $this->MatchTagRule($params, $attribute_rules['tag_based'] ?? [])) {
            return $tag_rule;
        }
        
        // 风险等级规则
        if ($risk_rule = $this->MatchRiskRule($params, $attribute_rules['risk_based'] ?? [])) {
            return $risk_rule;
        }
        
        return false;
    }
    
    /**
     * 匹配价格规则
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [array]           $price_rules [价格规则]
     * @return  [array|false]     [匹配的规则或false]
     */
    private function MatchPriceRule($params, $price_rules)
    {
        $price = $params['total_price'] ?? 0;
        
        foreach ($price_rules as $rule_name => $rule) {
            $min_price = $rule['min_price'] ?? 0;
            $max_price = $rule['max_price'] ?? PHP_INT_MAX;
            
            if ($price >= $min_price && $price <= $max_price) {
                return array_merge($rule, [
                    'match_type' => 'price',
                    'match_value' => $price,
                    'rule_name' => $rule_name
                ]);
            }
        }
        
        return false;
    }
    
    /**
     * 匹配标签规则
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [array]           $tag_rules [标签规则]
     * @return  [array|false]     [匹配的规则或false]
     */
    private function MatchTagRule($params, $tag_rules)
    {
        $product_tags = $params['tags'] ?? [];
        $product_name = $params['name'] ?? '';
        
        // 如果没有标签，尝试从商品名称中提取
        if (empty($product_tags) && !empty($product_name)) {
            foreach ($tag_rules as $rule_name => $rule) {
                foreach ($rule['tags'] as $tag) {
                    if (strpos($product_name, $tag) !== false) {
                        return array_merge($rule, [
                            'match_type' => 'tag_from_name',
                            'match_value' => $tag,
                            'rule_name' => $rule_name
                        ]);
                    }
                }
            }
        }
        
        // 匹配商品标签
        foreach ($tag_rules as $rule_name => $rule) {
            $rule_tags = $rule['tags'];
            if (array_intersect($product_tags, $rule_tags)) {
                return array_merge($rule, [
                    'match_type' => 'tag',
                    'match_value' => array_intersect($product_tags, $rule_tags),
                    'rule_name' => $rule_name
                ]);
            }
        }
        
        return false;
    }
    
    /**
     * 匹配风险规则
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [array]           $risk_rules [风险规则]
     * @return  [array|false]     [匹配的规则或false]
     */
    private function MatchRiskRule($params, $risk_rules)
    {
        $risk_level = $params['risk_level'] ?? 'low_risk';
        
        if (isset($risk_rules[$risk_level])) {
            return array_merge($risk_rules[$risk_level], [
                'match_type' => 'risk_level',
                'match_value' => $risk_level
            ]);
        }
        
        return false;
    }
    
    /**
     * 设置API版本偏好
     * @author  Devil
     * @param   [string]          $api_version [API版本]
     */
    private function SetApiVersionPreference($api_version)
    {
        // 在配置中添加API版本偏好
        $this->config['preferred_api_version'] = $api_version;
    }
    
    /**
     * 记录商品规则匹配日志
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [array]           $product_rule [匹配的规则]
     */
    private function LogProductRuleMatch($params, $product_rule)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => '商品规则匹配',
            'product_info' => [
                'name' => $params['name'] ?? '',
                'product_id' => $params['product_id'] ?? '',
                'sku' => $params['sku'] ?? '',
                'category' => $params['category'] ?? '',
                'total_price' => $params['total_price'] ?? 0,
                'tags' => $params['tags'] ?? []
            ],
            'matched_rule' => [
                'match_type' => $product_rule['match_type'] ?? 'default',
                'match_value' => $product_rule['match_value'] ?? '',
                'rule_name' => $product_rule['rule_name'] ?? 'default',
                'api_version' => $product_rule['api_version'],
                'account_preference' => $product_rule['account_preference']
            ],
            'selected_account' => $this->current_account
        ];
        $this->WriteLog('WeChat_Product_Rule_Match', $log_data);
    }
    
    /**
     * 检测支付场景
     * @author  Devil
     * @param   [array]           $params [支付参数]
     */
    private function DetectPayScene($params)
    {
        // 检测VIP用户
        if (isset($params['user_type']) && $params['user_type'] === 'vip') {
            return 'vip';
        }
        
        // 检测订单金额
        $amount = $params['total_price'] ?? 0;
        if ($amount >= 1000) { // 大于等于1000元为大额订单
            return 'large_amount';
        } elseif ($amount < 100) { // 小于100元为小额订单
            return 'small_amount';
        }
        
        // 检测终端类型
        $client_type = $this->GetApplicationClientType();
        if (in_array($client_type, ['ios', 'android'])) {
            return 'app_pay';
        } elseif ($client_type === 'weixin') {
            return 'mini_program';
        } elseif ($client_type === 'pc') {
            return 'pc_qrcode';
        } elseif ($client_type === 'h5') {
            return 'h5_pay';
        }
        
        return 'normal';
    }
    
    /**
     * 带故障切换的支付
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [boolean]         $use_v3 [是否使用V3]
     */
    private function PayWithFailover($params, $use_v3)
    {
        $max_attempts = 3;
        $attempt = 0;
        $original_account = $this->current_account;
        
        while ($attempt < $max_attempts) {
            $attempt++;
            
            try {
                // 执行支付
                if ($use_v3) {
                    $result = $this->PayV3($params);
                } else {
                    $result = $this->PayV2($params);
                }
                
                // 支付成功，记录日志
                if ($result['code'] === 0) {
                    $this->LogPaymentSuccess($params, $attempt, $this->current_account);
                    return $result;
                }
                
                // 支付失败，尝试故障切换
                if ($attempt < $max_attempts && $this->ShouldFailover($result)) {
                    $this->TryFailover();
                    continue;
                }
                
                return $result;
                
            } catch (\Exception $e) {
                // 异常处理，尝试故障切换
                if ($attempt < $max_attempts) {
                    $this->LogPaymentError($params, $attempt, $e->getMessage());
                    $this->TryFailover();
                    continue;
                }
                
                return DataReturn('支付异常: ' . $e->getMessage(), -1);
            }
        }
        
        return DataReturn('支付失败，已尝试所有可用账号', -1);
    }
    
    /**
     * 判断是否需要故障切换
     * @author  Devil
     * @param   [array]           $result [支付结果]
     */
    private function ShouldFailover($result)
    {
        if (empty($this->multi_configs['failover']['enabled'])) {
            return false;
        }
        
        // 根据错误码判断是否需要切换
        $failover_codes = [
            -1,   // 通用错误
            -100, // 接口异常
            // 可以添加更多需要切换的错误码
        ];
        
        return in_array($result['code'], $failover_codes);
    }
    
    /**
     * 尝试故障切换
     * @author  Devil
     */
    private function TryFailover()
    {
        if (empty($this->multi_configs['failover']['enabled'])) {
            return false;
        }
        
        $failover_rules = $this->multi_configs['failover']['rules'] ?? [];
        $current_rule = $failover_rules[$this->current_account] ?? [];
        
        foreach ($current_rule as $backup_account) {
            // 检查是否已经尝试过这个账号
            if (in_array($backup_account, $this->failover_history)) {
                continue;
            }
            
            try {
                $this->failover_history[] = $this->current_account;
                $this->SetAccount($backup_account);
                
                $this->LogFailover($this->failover_history[count($this->failover_history) - 1], $backup_account);
                return true;
            } catch (\Exception $e) {
                // 切换失败，继续尝试下一个
                continue;
            }
        }
        
        return false;
    }
    
    /**
     * 记录故障切换日志
     * @author  Devil
     * @param   [string]          $from_account [源账号]
     * @param   [string]          $to_account [目标账号]
     */
    private function LogFailover($from_account, $to_account)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => '故障切换',
            'from_account' => $from_account,
            'to_account' => $to_account,
            'failover_history' => $this->failover_history
        ];
        $this->WriteLog('WeChat_Failover', $log_data);
    }
    
    /**
     * 记录支付成功日志
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [int]             $attempt [尝试次数]
     * @param   [string]          $account [使用的账号]
     */
    private function LogPaymentSuccess($params, $attempt, $account)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => '支付成功',
            'order_no' => $params['order_no'] ?? '',
            'amount' => $params['total_price'] ?? 0,
            'account_used' => $account,
            'attempt' => $attempt,
            'failover_history' => $this->failover_history
        ];
        $this->WriteLog('WeChat_Payment_Success', $log_data);
    }
    
    /**
     * 记录支付错误日志
     * @author  Devil
     * @param   [array]           $params [支付参数]
     * @param   [int]             $attempt [尝试次数]
     * @param   [string]          $error [错误信息]
     */
    private function LogPaymentError($params, $attempt, $error)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => '支付错误',
            'order_no' => $params['order_no'] ?? '',
            'amount' => $params['total_price'] ?? 0,
            'account_used' => $this->current_account,
            'attempt' => $attempt,
            'error' => $error,
            'failover_history' => $this->failover_history
        ];
        $this->WriteLog('WeChat_Payment_Error', $log_data);
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
     * 根据客户端类型获取AppID
     * @author  GitHub Copilot
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2025-09-05
     * @desc    针对微信小程序支付获取AppID
     * @param   [string]    $client_type    [客户端类型]
     */
    private function GetAppid($client_type)
    {
        // 对于微信小程序，优先使用 mini_appid，否则使用 appid
        if($client_type == 'weixin' && !empty($this->config['mini_appid']))
        {
            return $this->config['mini_appid'];
        }
        
        // 其他情况使用通用 appid
        return $this->config['appid'];
    }

    /**
     * 生成随机字符串
     * @author  GitHub Copilot
     * @blog    http://gong.gg/
     * @version 1.0.0
     * @date    2025-09-05
     * @desc    生成指定长度的随机字符串
     * @param   [int]    $length    [字符串长度，默认32位]
     */
    private function CreateNoncestr($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
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
        
        // 记录V2参数构建
        $this->WriteLog('WeChat_V2_Params_Built', [
            'api_version' => 'V2',
            'trade_type' => $trade_type,
            'payment_info' => [
                'wechat_api_version' => 'V2',
                'mini_program_appid' => $appid,
                'wechat_pay_mchid' => $this->config['mch_id']
            ]
        ]);
        
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
            
            // 菜鸟订单下发处理
            $this->CainiaoOrderNotify($result);
            
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
        
        $sign_string = $sign.'key='.$this->config['key'];
        $signature = strtoupper(md5($sign_string));
        
        // 记录V2签名日志
        $this->LogV2Sign($params, $sign_string, $signature);
        
        return $signature;
    }

    /**
     * 菜鸟订单下发通知
     * @param array $payment_result 微信支付结果
     */
    private function CainiaoOrderNotify($payment_result)
    {
        try {
            // 检查是否启用菜鸟集成
            if (empty($this->config['cainiao_enabled']) || !$this->config['cainiao_enabled']) {
                return;
            }
            
            // 检查是否配置菜鸟参数
            if (empty($this->config['cainiao_resource_code']) || empty($this->config['cainiao_app_secret'])) {
                return;
            }

            // 根据订单号获取订单信息
            $order_no = $payment_result['out_trade_no'];
            if (empty($order_no)) {
                return;
            }

            // 获取订单详情
            $order = \think\facade\Db::name('Order')->where(['order_no' => $order_no])->find();
            if (empty($order)) {
                return;
            }

            // 获取订单商品信息
            $order_goods = \think\facade\Db::name('OrderDetail')->where(['order_id' => $order['id']])->select()->toArray();
            if (empty($order_goods)) {
                return;
            }

            // 检查是否为菜鸟仓库订单
            $is_cainiao_warehouse = $this->CheckCainiaoWarehouse($order_goods);
            if (!$is_cainiao_warehouse) {
                return;
            }

            // 构建菜鸟订单数据
            $cainiao_data = $this->BuildCainiaoOrderData($order, $order_goods);
            
            // 调用菜鸟API
            $result = $this->SendToCainiao($cainiao_data);
            
            // 记录日志
            $this->LogCainiaoRequest($order_no, $cainiao_data, $result);

        } catch (\Exception $e) {
            // 记录错误日志但不影响支付流程
            error_log('菜鸟订单下发异常: ' . $e->getMessage() . ' 订单号: ' . ($order_no ?? ''));
        }
    }

    /**
     * 检查是否为菜鸟仓库
     * @param array $order_goods 订单商品
     * @return bool
     */
    private function CheckCainiaoWarehouse($order_goods)
    {
        // 获取商品的仓库信息
        foreach ($order_goods as $goods) {
            // 查询商品仓库信息
            $warehouse_goods = \think\facade\Db::name('WarehouseGoods')
                ->alias('wg')
                ->join('Warehouse w', 'wg.warehouse_id = w.id')
                ->where(['wg.goods_id' => $goods['goods_id']])
                ->field('w.name, w.alias, w.is_enable')
                ->find();
            
            // 检查仓库名称或别名是否包含"菜鸟"关键词
            if (!empty($warehouse_goods) && $warehouse_goods['is_enable'] == 1) {
                $warehouse_name = strtolower($warehouse_goods['name'] . $warehouse_goods['alias']);
                if (strpos($warehouse_name, '菜鸟') !== false || strpos($warehouse_name, 'cainiao') !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 构建菜鸟订单数据
     * @param array $order 订单信息
     * @param array $order_goods 订单商品
     * @return array
     */
    private function BuildCainiaoOrderData($order, $order_goods)
    {
        // 构建商品列表
        $items = [];
        foreach ($order_goods as $goods) {
            $items[] = [
                'itemId' => (string)$goods['goods_id'],
                'itemName' => $goods['title'],
                'itemCode' => $goods['spec'],
                'quantity' => (int)$goods['buy_number'],
                'itemPrice' => (float)$goods['price'],
            ];
        }

        // 构建收货人信息
        $receiver = [
            'name' => $order['receive_name'],
            'phone' => $order['receive_tel'],
            'mobile' => $order['receive_tel'],
            'province' => $order['receive_province_name'] ?? '',
            'city' => $order['receive_city_name'] ?? '',
            'area' => $order['receive_county_name'] ?? '',
            'town' => '',
            'address' => $order['receive_address'],
            'zip' => $order['receive_zip'] ?? '',
        ];

        // 构建菜鸟订单数据
        $order_data = [
            'ownerUserId' => $this->config['cainiao_owner_user_id'] ?? '14544',
            'businessUnitId' => $this->config['cainiao_business_unit_id'] ?? '',
            'externalOrderCode' => $order['order_no'],
            'externalTradeCode' => $order['order_no'],
            'orderType' => $this->config['cainiao_order_type'] ?? 'BONDED_WHS',
            'storeCode' => $this->config['cainiao_store_code'] ?? 'JIX230',
            'externalShopId' => $this->config['cainiao_shop_id'] ?? '',
            'externalShopName' => $this->config['cainiao_shop_name'] ?? 'ShopXO商城',
            'orderSource' => $this->config['cainiao_order_source'] ?? '201',
            'orderSubSource' => $this->config['cainiao_order_sub_source'] ?? '',
            'orderCreateTime' => date('Y-m-d H:i:s', $order['add_time']),
            'payTime' => date('Y-m-d H:i:s'),
            'orderFlag' => 'NORMAL',
            'consigneeName' => $receiver['name'],
            'consigneePhone' => $receiver['phone'],
            'consigneeMobile' => $receiver['mobile'],
            'consigneeAddress' => [
                'province' => $receiver['province'],
                'city' => $receiver['city'],
                'area' => $receiver['area'],
                'town' => $receiver['town'],
                'detail' => $receiver['address'],
            ],
            'items' => $items,
            'totalAmount' => (float)$order['total_price'],
            'actualAmount' => (float)$order['pay_price'],
        ];

        return $order_data;
    }

    /**
     * 发送数据到菜鸟
     * @param array $order_data 订单数据
     * @return array
     */
    private function SendToCainiao($order_data)
    {
        // 构建请求内容
        $content = json_encode($order_data, JSON_UNESCAPED_UNICODE);
        
        // 构建请求参数
        $request_params = [
            'msg_type' => 'GLOBAL_SALE_ORDER_NOTIFY',
            'logistic_provider_id' => $this->config['cainiao_resource_code'],
            'to_code' => 'GLOBAL_SALE',
            'logistics_interface' => $content,
        ];

        // 生成签名
        $request_params['data_digest'] = base64_encode(md5($content . $this->config['cainiao_app_secret'], true));

        // 发送请求
        $url = $this->config['cainiao_environment'] === 'sandbox' 
            ? 'http://linkdaily.tbsandbox.com/gateway/link.do'
            : 'https://link.cainiao.com/gateway/link.do';

        return $this->HttpRequest($url, http_build_query($request_params));
    }

    /**
     * 记录菜鸟请求日志
     * @param string $order_no 订单号
     * @param array $request_data 请求数据
     * @param string $response 响应结果
     */
    private function LogCainiaoRequest($order_no, $request_data, $response)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'order_no' => $order_no,
            'request' => $request_data,
            'response' => $response,
        ];
        
        // 记录到日志文件
        error_log('菜鸟订单下发: ' . json_encode($log_data, JSON_UNESCAPED_UNICODE));
        
        // 可选：记录到数据库
        if (!empty($this->config['cainiao_log_to_db'])) {
            try {
                \think\facade\Db::name('CainiaoOrderLog')->insert([
                    'order_no' => $order_no,
                    'request_data' => json_encode($request_data, JSON_UNESCAPED_UNICODE),
                    'response_data' => $response,
                    'add_time' => time(),
                ]);
            } catch (\Exception $e) {
                // 忽略数据库错误
            }
        }
    }
    
    /**
     * 记录V2签名日志
     * @param   [array]           $params [原始参数]
     * @param   [string]          $sign_string [待签名字符串]
     * @param   [string]          $signature [签名结果]
     */
    private function LogV2Sign($params, $sign_string, $signature)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => 'V2签名生成',
            'params' => $params,
            'sign_string' => substr($sign_string, 0, strlen($sign_string) - strlen($this->config['key'])) . 'key=***',
            'signature' => $signature,
            'key_used' => !empty($this->config['key']) ? substr($this->config['key'], 0, 8) . '***' : '未配置'
        ];
        $this->WriteLog('WeChat_V2_Sign', $log_data);
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
        // 直接构建V3支付参数，避免调用V2的GetPayParams
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

        // appid - V3 API使用专用的appid
        if($client_type == 'weixin' && !empty($this->config['v3_mini_appid']))
        {
            $appid = $this->config['v3_mini_appid'];
        } elseif(!empty($this->config['v3_appid'])) {
            $appid = $this->config['v3_appid'];
        } else {
            $appid = $this->PayAppID($client_type);
        }

        // 异步地址处理
        $notify_url = ($client_type == 'qq') ? 'https://api.q.qq.com/wxpay/notify' : $this->GetNotifyUrl($params);

        // V2格式的基础参数（仅用于后续转换，不生成签名）
        $v2_data = [
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
            'time_expire'       => $this->OrderAutoCloseTime(),
        ];
        
        // 记录V3参数构建
        $this->WriteLog('WeChat_V3_Params_Built', [
            'api_version' => 'V3',
            'trade_type' => $trade_type,
            'client_type' => $client_type,
            'payment_info' => [
                'wechat_api_version' => 'V3',
                'mini_program_appid' => $appid,
                'wechat_pay_mchid' => $this->config['v3_mch_id']
            ]
        ]);
        
        $v3_data = [
            'appid' => $appid,
            'mchid' => $this->config['v3_mch_id'],
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
        if ($private_key === false) {
            throw new \Exception('V3私钥解析失败，无法生成V3签名');
        }
        
        // 生成签名
        $signature = '';
        if (!openssl_sign($sign_str, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            throw new \Exception('V3签名生成失败');
        }
        $signature = base64_encode($signature);

        // 构建 Authorization 头
        $serial_no = $this->GetV3SerialNo();
        $auth = sprintf('WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $this->config['v3_mch_id'], $nonce, $timestamp, $serial_no, $signature);

        // 记录V3签名日志
        $this->LogV3Sign($url, $body, $sign_str, $signature, $serial_no, $timestamp, $nonce);

        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ShopXO/' . APPLICATION_VERSION,
            'Authorization: ' . $auth
        ];
    }
    
    /**
     * 记录V3签名日志
     * @param   [string]          $url [请求URL]
     * @param   [string]          $body [请求体]
     * @param   [string]          $sign_str [待签名字符串]
     * @param   [string]          $signature [签名结果]
     * @param   [string]          $serial_no [证书序列号]
     * @param   [int]             $timestamp [时间戳]
     * @param   [string]          $nonce [随机字符串]
     */
    private function LogV3Sign($url, $body, $sign_str, $signature, $serial_no, $timestamp, $nonce)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => 'V3签名生成',
            'request_url' => $url,
            'request_body' => $body,
            'sign_string' => $sign_str,
            'signature' => $signature,
            'serial_no' => $serial_no,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'mchid' => $this->config['mch_id'],
            'has_private_key' => !empty($this->config['apiclient_key'])
        ];
        $this->WriteLog('WeChat_V3_Sign', $log_data);
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
        if ($private_key === false) {
            throw new \Exception('V3私钥解析失败，无法生成支付签名');
        }
        $signature = '';
        if (!openssl_sign($sign_str, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            throw new \Exception('V3支付签名生成失败');
        }
        $data['paySign'] = base64_encode($signature);
        
        // 记录V3支付签名日志
        $this->LogV3PaySign($data, $sign_str, $data['paySign']);

        return DataReturn('success', 0, $data);
    }
    
    /**
     * 记录V3支付签名日志
     * @param   [array]           $pay_data [支付数据]
     * @param   [string]          $sign_str [待签名字符串] 
     * @param   [string]          $signature [签名结果]
     */
    private function LogV3PaySign($pay_data, $sign_str, $signature)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => 'V3支付签名生成',
            'pay_data' => $pay_data,
            'sign_string' => $sign_str,
            'signature' => $signature
        ];
        $this->WriteLog('WeChat_V3_Pay_Sign', $log_data);
    }

    /**
     * 获取 V3 私钥
     */
    private function GetV3PrivateKey()
    {
        // 如果配置中有证书私钥，使用证书私钥作为 V3 私钥
        if (!empty($this->config['apiclient_key'])) {
            $private_key_content = $this->config['apiclient_key'];
            
            // 格式化私钥内容
            if (strpos($private_key_content, '-----BEGIN') === false) {
                $private_key_content = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($private_key_content, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
            }
            
            // 尝试解析私钥
            $private_key = @openssl_pkey_get_private($private_key_content);
            
            // 记录私钥获取日志
            $this->LogV3PrivateKeyUsage($private_key !== false);
            
            if ($private_key === false) {
                // 私钥解析失败
                $error = openssl_error_string();
                $this->WriteLog('WeChat_V3_PrivateKey_Error', [
                    'error' => 'Failed to parse private key',
                    'openssl_error' => $error,
                    'key_preview' => substr($private_key_content, 0, 50) . '...'
                ]);
                return false;
            }
            
            return $private_key;
        }
        
        // 没有配置私钥
        $this->WriteLog('WeChat_V3_PrivateKey_Error', [
            'error' => 'No private key configured',
            'config_apiclient_key' => empty($this->config['apiclient_key']) ? 'empty' : 'exists'
        ]);
        return false;
    }
    
    /**
     * 记录V3私钥使用日志
     * @author  Devil
     * @param bool $success 私钥解析是否成功
     */
    private function LogV3PrivateKeyUsage($success = true)
    {
        $log_data = [
            'time' => date('Y-m-d H:i:s'),
            'action' => 'V3私钥获取',
            'account' => $this->current_account,
            'has_private_key' => !empty($this->config['apiclient_key']),
            'key_length' => strlen($this->config['apiclient_key'] ?? ''),
            'parse_success' => $success
        ];
        $this->WriteLog('WeChat_V3_PrivateKey', $log_data);
    }
    
    /**
     * 判断是否使用V3 API
     * @author  Devil
     * @return  [boolean]         [是否使用V3]
     */
    private function ShouldUseV3Api()
    {
        // 检查是否有API版本偏好设置
        if (isset($this->config['preferred_api_version'])) {
            $preferred = $this->config['preferred_api_version'];
            
            if ($preferred === 'v3' && !empty($this->config['v3_key'])) {
                // 验证V3私钥是否有效
                if ($this->IsV3PrivateKeyValid()) {
                    return true;
                } else {
                    $this->WriteLog('WeChat_V3_Fallback', [
                        'reason' => '偏好V3但私钥无效，回退到V2',
                        'preferred_api' => 'v3'
                    ]);
                    return false;
                }
            } elseif ($preferred === 'v2' && !empty($this->config['key'])) {
                return false;
            }
        }
        
        // 默认逻辑：有V3密钥且私钥有效则使用V3
        if (!empty($this->config['v3_key'])) {
            if ($this->IsV3PrivateKeyValid()) {
                return true;
            } else {
                $this->WriteLog('WeChat_V3_Fallback', [
                    'reason' => '有V3密钥但私钥无效，回退到V2',
                    'has_v3_key' => true
                ]);
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * 验证V3私钥是否有效
     * @return boolean
     */
    private function IsV3PrivateKeyValid()
    {
        if (empty($this->config['apiclient_key'])) {
            return false;
        }
        
        try {
            $private_key_content = $this->config['apiclient_key'];
            
            // 格式化私钥内容
            if (strpos($private_key_content, '-----BEGIN') === false) {
                $private_key_content = "-----BEGIN PRIVATE KEY-----\n" . wordwrap($private_key_content, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
            }
            
            // 尝试解析私钥
            $private_key = @openssl_pkey_get_private($private_key_content);
            
            if ($private_key === false) {
                return false;
            }
            
            // 清理资源
            if (is_resource($private_key)) {
                openssl_free_key($private_key);
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取当前账号信息
     * @author  Devil
     * @return  [array]           [账号信息]
     */
    public function GetCurrentAccount()
    {
        return [
            'account_name' => $this->current_account,
            'multi_config_enabled' => !empty($this->multi_configs),
            'failover_history' => $this->failover_history,
            'preferred_api_version' => $this->config['preferred_api_version'] ?? 'auto',
            'config_summary' => [
                'mch_id' => $this->config['mch_id'] ?? '',
                'has_v2' => !empty($this->config['key']),
                'has_v3' => !empty($this->config['v3_key'])
            ]
        ];
    }
    
    /**
     * 获取所有可用账号列表
     * @author  Devil
     * @return  [array]           [账号列表]
     */
    public function GetAvailableAccounts()
    {
        if (empty($this->multi_configs['accounts'])) {
            return [];
        }
        
        $accounts = [];
        foreach ($this->multi_configs['accounts'] as $name => $config) {
            $accounts[$name] = [
                'name' => $config['name'],
                'description' => $config['description'],
                'priority' => $config['priority'],
                'status' => $config['status'],
                'v2_enabled' => $config['v2']['enabled'] ?? false,
                'v3_enabled' => $config['v3']['enabled'] ?? false,
                'scenarios' => $config['scenarios'] ?? []
            ];
        }
        
        return $accounts;
    }

    /**
     * 获取 V3 证书序列号
     */
    private function GetV3SerialNo()
    {
        // 简化处理：从私钥证书中提取序列号
        // 在实际使用中，序列号应该从微信商户平台获取
        //return md5($this->config['mch_id']);
        return $this->config['v3_id'];
    }
    
    /**
     * 统一日志记录方法
     * @param string $type 日志类型
     * @param array $data 日志数据
     */
    private function WriteLog($type, $data)
    {
        try {
            // 统一日志目录路径
            $log_dir = defined('ROOT') ? ROOT . 'runtime' . DS . 'log' . DS . 'payment' : __DIR__ . DS . 'runtime_log';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
            }
            
            // 确保数据是数组格式
            if (!is_array($data)) {
                $data = ['message' => $data];
            }
            
            // 添加统一的元数据
            $log_data = array_merge($data, [
                'log_type' => $type,
                'timestamp' => time(),
                'account_info' => [
                    'current_account' => $this->current_account ?? 'default',
                    'api_version' => $this->api_version ?? 'auto'
                ]
            ]);
            
            // 统一到单个日志文件：weixin_pay_日期.log
            $log_file = $log_dir . DS . 'weixin_pay_' . date('Y_m_d') . '.log';
            $log_content = '[' . date('Y-m-d H:i:s') . '] [' . $type . '] ' . json_encode($log_data, JSON_UNESCAPED_UNICODE) . "\n";
            
            @file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // 静默处理日志错误，不影响支付流程
            error_log("WeChat Payment Log Error: " . $e->getMessage());
        }
    }
}
?>