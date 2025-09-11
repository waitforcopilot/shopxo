<?php
// 微信支付配置文件
// 请根据实际情况修改配置参数

return [
    'main' => [
        // 应用配置
        'appid' => 'wx1234567890abcdef', // 公众号AppID
        'mini_appid' => 'wx0987654321fedcba', // 小程序AppID  
        'app_appid' => 'wx1122334455667788', // APP AppID

        // V2 API配置
        'v2' => [
            'mch_id' => '1234567890', // 商户号
            'key' => '32位商户API密钥', // 商户API密钥
        ],

        // V3 API配置
        'v3' => [
            'enabled' => true,
            'mch_id' => '1234567890', // 商户号
            'key' => '32位商户API密钥V3', // APIv3密钥
            'serial_no' => '商户证书序列号', // 商户证书序列号
            'appid' => 'wx1234567890abcdef', // 公众号AppID
            'mini_appid' => 'wx0987654321fedcba', // 小程序AppID
            'private_key' => '商户私钥内容', // 商户API私钥内容
        ],

        // 菜鸟仓储配置
        'cainiao' => [
            // 基础配置
            'enabled' => false, // 是否启用菜鸟集成
            'environment' => 'sandbox', // 环境：sandbox(测试) 或 production(生产)
            'app_code' => '102905', // 菜鸟应用appCode
            'resource_code' => '95f7ac77fd52d162a68eaea5cef3dc55', // 菜鸟资源code(logistic_provider_id)
            'app_secret' => '466aN6F8t0Q6jxiK8GUrFM355mju19j8', // 菜鸟应用密钥
            'app_name' => 'ShopXO', // 应用名称
            
            // 货主信息
            'owner_user_id' => '2220576876930', // 货主用户ID
            'business_unit_id' => 'B06738021', // BU信息(多BU场景下必填)
            
            // 订单配置
            'order_type' => 'BONDED_WHS', // 订单类型
            'order_source' => '201', // 订单来源
            'order_sub_source' => '', // 订单子渠道来源
            
            // 仓库配置  
            'store_code' => 'JIX230', // 仓库代码
            'shop_name' => 'ShopXO商城', // 店铺名称
            'shop_id' => '', // 外部店铺ID
            
            // 其他配置
            'log_to_db' => true, // 是否记录到数据库
            'auto_check_warehouse' => true, // 是否自动检查仓库
            'warehouse_keywords' => ['菜鸟', 'cainiao', '菜鸟仓'], // 仓库关键词匹配
        ],
    ],
];
?>