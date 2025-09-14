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
namespace app\admin\controller;

use app\admin\controller\Base;
use app\service\ApiService;
use think\facade\Db;

/**
 * 菜鸟相关控制器
 */
class Cainiao extends Base
{
    /**
     * 通知菜鸟发货
     * 路由：admin/cainiao/cainiaoshipment
     */
    public function CainiaoShipment()
    {
        // 1) 参数校验
        $params = $this->data_request;
        $order_id = isset($params['id']) ? intval($params['id']) : 0;
        if ($order_id <= 0) {
            return ApiService::ApiDataReturn(DataReturn('参数有误：缺少订单ID', -1));
        }

        // 2) 可选快递信息（目前仅保留，不强依赖）
        $express = [];
        if (!empty($params['express_data'])) {
            $raw = urldecode($params['express_data']);
            $arr = json_decode($raw, true);
            if (is_array($arr) && !empty($arr)) {
                $last = end($arr);
                if (is_array($last)) {
                    $express = [
                        'express_name'   => $last['express_name'] ?? ($last['name'] ?? ''),
                        'express_number' => $last['express_number'] ?? ($last['number'] ?? ''),
                        'express_code'   => $last['express_code'] ?? ($last['code'] ?? ''),
                        'note'           => $last['note'] ?? '',
                    ];
                }
            }
        }

        // 3) 固定配置（建议迁移到配置中心）
        $cfg = [
            'cainiao_app_name'   => '杭州圣劳诗',
            'cainiao_app_code'   => '95f7ac77fd52d162a68eaea5cef3dc55',
            'cainiao_app_secret' => '466aN6F8t0Q6jxiK8GUrFM355mju19j8',
            'sender_name'        => '杭州圣劳诗',
            'sender_mobile'      => '0571-12345678',
            'sender_province'    => '浙江省',
            'sender_city'        => '杭州市',
            'sender_area'        => '西湖区',
            'sender_address'     => '云栖小镇',
        ];

        $appCode   = trim($cfg['cainiao_app_code'] ?? '');
        $appSecret = trim($cfg['cainiao_app_secret'] ?? '');
        if ($appCode === '' || $appSecret === '') {
            return ApiService::ApiDataReturn(DataReturn('菜鸟配置参数不完整', -1));
        }

        // 4) 读取订单与明细
        try {
            $orderRow = Db::name('Order')->where('id', $order_id)->find();
            if (empty($orderRow)) {
                return ApiService::ApiDataReturn(DataReturn('未找到订单', -1));
            }
            $details = Db::name('OrderDetail')->where('order_id', $order_id)->select()->toArray();
        } catch (\Throwable $e) {
            return ApiService::ApiDataReturn(DataReturn('数据库错误：'.$e->getMessage(), -1));
        }

        // 5) 组装商品项
        $items = [];
        $sumCount = 0;
        $sumAmount = 0.0;
        if (!empty($details)) {
            foreach ($details as $d) {
                $title      = $d['title'] ?? '商品';
                $count      = (int)($d['buy_number'] ?? 1);
                $price      = (float)($d['price'] ?? 0);
                $totalPrice = (float)($d['total_price'] ?? ($price * $count));
                $weightG    = (int)round((float)($d['spec_weight'] ?? 0));

                $items[] = [
                    'itemName'   => $title,
                    'count'      => $count,
                    'weight'     => $weightG,
                    'itemCode'   => $d['spec_coding']  ?? '',
                    'barcode'    => $d['spec_barcode'] ?? '',
                    'price'      => round($price, 2),
                    'totalPrice' => round($totalPrice, 2),
                ];

                $sumCount  += $count;
                $sumAmount += $totalPrice;
            }
        } else {
            $items[] = [
                'itemName'   => '商品',
                'count'      => 1,
                'weight'     => 0,
                'itemCode'   => '',
                'barcode'    => '',
                'price'      => 0.00,
                'totalPrice' => 0.00,
            ];
            $sumCount  = 1;
            $sumAmount = 0.00;
        }

        // 6) 汇总字段与收件人
        $itemCount   = (int)($orderRow['buy_number_count'] ?? $sumCount);
        $totalAmount = (float)($orderRow['total_price'] ?? $sumAmount);
        $receiverTel = $orderRow['tel'] ?? '';

        // 7) 组装发货数据
        $shipment_data = [
            'orderCode'    => $orderRow['order_no'] ?? $order_id,
            'orderId'      => $order_id,
            'totalAmount'  => round($totalAmount, 2),
            'itemCount'    => $itemCount,
            'payPrice'     => (float)($orderRow['pay_price'] ?? 0.0),
            'payTime'      => (int)($orderRow['pay_time'] ?? 0),
            'deliveryTime' => (int)($orderRow['delivery_time'] ?? 0),
            'receiver' => [
                'name'     => $orderRow['receive_name']     ?? '张先生',
                'mobile'   => $receiverTel,
                'phone'    => $receiverTel,
                'province' => $orderRow['receive_province'] ?? '',
                'city'     => $orderRow['receive_city']     ?? '',
                'area'     => $orderRow['receive_county']   ?? '',
                'address'  => $orderRow['receive_address']  ?? '',
            ],
            'sender' => [
                'name'     => $cfg['sender_name'],
                'mobile'   => $cfg['sender_mobile'],
                'phone'    => $cfg['sender_mobile'],
                'province' => $cfg['sender_province'],
                'city'     => $cfg['sender_city'],
                'area'     => $cfg['sender_area'],
                'address'  => $cfg['sender_address'],
            ],
            'items' => $items,
        ];

        // 8) 组装请求参数
        $content = json_encode(['arg0' => $shipment_data], JSON_UNESCAPED_UNICODE);
        $request_params = [
            'msg_type'             => 'GLOBAL_SALE_ORDER_NOTIFY',
            'logistic_provider_id' => $appCode,
            'to_code'              => 'CAINIAO',
            'logistics_interface'  => $content,
        ];
        $request_params['data_digest'] = base64_encode(md5($content.$appSecret, true));

        // 9) 请求菜鸟
        $url = 'https://link.cainiao.com/gateway/link.do';
        $res = CurlPost($url, $request_params);

        // 10) 解析返回
        if (is_array($res) && isset($res['code']) && $res['code'] == 0 && !empty($res['data'])) {
            $response = is_array($res['data']) ? $res['data'] : json_decode($res['data'], true);
            if (!empty($response)) {
                if (!empty($response['success']) && $response['success'] == true) {
                    $result_data = [];
                    if (!empty($response['result'])) {
                        $result_data = is_array($response['result']) ? $response['result'] : json_decode($response['result'], true);
                    }
                    return ApiService::ApiDataReturn(DataReturn('发货成功', 0, $result_data));
                }
                $error_msg = $response['errorMsg'] ?? '发货失败';
                return ApiService::ApiDataReturn(DataReturn($error_msg, -1));
            }
        }

        $error_msg = (is_array($res) && !empty($res['msg'])) ? $res['msg'] : '菜鸟发货接口请求失败';
        return ApiService::ApiDataReturn(DataReturn($error_msg, -1));
    }
}
?>
