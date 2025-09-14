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
use Throwable;

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
        } catch (Throwable $e) {
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

        // 9) 请求菜鸟（带日志）
        $url = 'https://link.cainiao.com/gateway/link.do';
        // 9.1 生成日志用的脱敏请求参数
        $log_request_params = $request_params;
        // 尝试脱敏 logistics_interface 中的手机号
        $masked_interface = $request_params['logistics_interface'];
        try {
            $ji = json_decode($request_params['logistics_interface'], true);
            if (is_array($ji) && isset($ji['arg0']) && is_array($ji['arg0'])) {
                if (isset($ji['arg0']['receiver'])) {
                    foreach (['mobile','phone'] as $pk) {
                        if (!empty($ji['arg0']['receiver'][$pk])) {
                            $val = (string)$ji['arg0']['receiver'][$pk];
                            $ji['arg0']['receiver'][$pk] = self::mask_mobile($val);
                        }
                    }
                }
                if (isset($ji['arg0']['sender'])) {
                    foreach (['mobile','phone'] as $pk) {
                        if (!empty($ji['arg0']['sender'][$pk])) {
                            $val = (string)$ji['arg0']['sender'][$pk];
                            $ji['arg0']['sender'][$pk] = self::mask_mobile($val);
                        }
                    }
                }
            }
            $masked_interface = json_encode($ji, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            // ignore
        }
        $log_request_params['logistics_interface'] = $masked_interface;

        // 写入文件日志 - 请求前
        self::write_log('[CainiaoShipment][REQUEST] url='.$url.' params='.json_encode($log_request_params, JSON_UNESCAPED_UNICODE));

        $res = CurlPost($url, $request_params);

        // 写入文件日志 - 响应
        self::write_log('[CainiaoShipment][RESPONSE] result='.json_encode($res, JSON_UNESCAPED_UNICODE));

        // 9.2 入库 PluginsExpressLog（无论成功失败都记录）
        try {
            $insert_data = [
                'express_type'   => 'cainiao',
                'express_name'   => 'GLOBAL_SALE_ORDER_NOTIFY',
                'express_number' => isset($orderRow['order_no']) ? $orderRow['order_no'] : (string)$order_id,
                'express_code'   => 'CAINIAO',
                'request_params' => json_encode($log_request_params, JSON_UNESCAPED_UNICODE),
                'response_data'  => json_encode($res, JSON_UNESCAPED_UNICODE),
                'add_time'       => time(),
            ];
            Db::name('PluginsExpressLog')->insertGetId($insert_data);
        } catch (Throwable $e) {
            self::write_log('[CainiaoShipment][DB-LOG][ERROR] '.$e->getMessage());
        }

        // 10) 解析返回
        if (is_array($res) && isset($res['code']) && $res['code'] == 0 && !empty($res['data'])) {
            $response = is_array($res['data']) ? $res['data'] : json_decode($res['data'], true);
            if (!empty($response)) {
                if (!empty($response['success']) && $response['success'] == true) {
                    $result_data = [];
                    if (!empty($response['result'])) {
                        $result_data = is_array($response['result']) ? $response['result'] : json_decode($response['result'], true);
                    }
                    // 成功日志
                    self::write_log('[CainiaoShipment][SUCCESS] order_id='.$order_id.' order_no='.(isset($orderRow['order_no'])?$orderRow['order_no']:'').
                        ' result='.json_encode($result_data, JSON_UNESCAPED_UNICODE));
                    return ApiService::ApiDataReturn(DataReturn('发货成功', 0, $result_data));
                }
                $error_msg = $response['errorMsg'] ?? '发货失败';
                self::write_log('[CainiaoShipment][FAIL] order_id='.$order_id.' msg='.$error_msg);
                return ApiService::ApiDataReturn(DataReturn($error_msg, -1));
            }
        }

        $error_msg = (is_array($res) && !empty($res['msg'])) ? $res['msg'] : '菜鸟发货接口请求失败';
        self::write_log('[CainiaoShipment][ERROR] order_id='.$order_id.' msg='.$error_msg);
        return ApiService::ApiDataReturn(DataReturn($error_msg, -1));
    }

    /**
     * 写入本地日志文件（runtime/log/cainiao_shipment.log）
     */
    private static function write_log($msg)
    {
        try {
            $ds = DIRECTORY_SEPARATOR;
            if (defined('ROOT')) {
                $log_dir = rtrim(ROOT, $ds).$ds.'runtime'.$ds.'log';
            } else {
                // 相对路径兜底：app/admin/controller/ -> ../../../runtime/log
                $log_dir = __DIR__.$ds.'..'.$ds.'..'.$ds.'..'.$ds.'runtime'.$ds.'log';
            }
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0777, true);
            }
            $file = rtrim($log_dir, $ds).$ds.'cainiao_shipment.log';
            $line = '['.date('Y-m-d H:i:s').'] '.$msg."\n";
            @error_log($line, 3, $file);
        } catch (Throwable $e) {
            // 忽略日志异常
        }
    }

    /**
     * 手机号脱敏：保留前3后2
     */
    private static function mask_mobile($s)
    {
        $s = (string)$s;
        $len = mb_strlen($s, 'UTF-8');
        if ($len <= 5) {
            return str_repeat('*', $len);
        }
        return mb_substr($s, 0, 3, 'UTF-8').str_repeat('*', max(0, $len-5)).mb_substr($s, -2, null, 'UTF-8');
    }
}
?>
