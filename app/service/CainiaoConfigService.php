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
namespace app\service;

/**
 * 菜鸟配置服务
 */
class CainiaoConfigService
{
    /**
     * 基础配置
     */
    public static function BaseConfig(): array
    {
        return [
            'enabled'       => true,
            'environment'   => 'production',
            'app_code'      => '102905',
            'resource_code' => '95f7ac77fd52d162a68eaea5cef3dc55',
            'app_secret'    => '466aN6F8t0Q6jxiK8GUrFM355mju19j8',
            'app_name'      => '杭州圣劳诗',
            'to_code'       => '',
            'order_source'  => '201',
            // 税率及折扣配置，可根据业务线灵活调整（示例：0.05 即 5%）
            'tax_rates'     => [
                'customs'     => 0.0,    // 关税税率 0%
                'consumption' => 0.15,   // 消费税税率 15%
                'vat'         => 0.13,   // 增值税税率 13%
            ],
            'tax_discount'       => 1.0,   // 折扣（例如海关减免系数）

            // 保险金额兼容配置，优先读取订单字段，其次使用默认值
            'default_insurance'  => 0.0,
            'insurance_field'    => '',

            // 接口访问白名单，限制可调用同步接口的来源 IP
            'ip_whitelist'       => [''],

            // 菜鸟状态 -> ShopXO 状态映射，可按业务需要调整
            'status_map' => [
                'ORDER_CREATED'    => 0,
                'WAIT_BUYER_PAY'   => 1,
                'WAIT_SELLER_SEND' => 2,
                'PICKED_UP'        => 3,
                'ON_THE_WAY'       => 3,
                'DELIVERED'        => 3,
                'SIGNED'           => 4,
                'COMPLETED'        => 4,
                'CANCELLED'        => 5,
                'CLOSED'           => 6,
            ],

            // 物流公司编码 -> 快递ID映射（需根据系统快递配置填写）
            'logistics_company_map' => [
                // 'YTO' => 1,
                // 'STO' => 2,
                // 'ZTO' => 3,
            ],
        ];
    }
}
?>
