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
use app\service\CainiaoConfigService;
use think\facade\Db;
use think\facade\Log;

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
        // 使用项目根目录下的runtime/log目录
        $log_file = root_path('runtime/log') . 'cainiao_shipment_' . date('Y-m-d') . '.log';
        $start_time = microtime(true);
        $request_id = md5(uniqid() . microtime());

        // 写入专用日志文件的函数
        $writeLog = function($level, $message, $data = []) use ($log_file, $request_id) {
            try {
                $timestamp = date('Y-m-d H:i:s');
                $json_data = '';
                if (!empty($data)) {
                    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $json_data = 'JSON_ENCODE_ERROR: ' . json_last_error_msg() . ' - DATA_TYPE: ' . gettype($data);
                    }
                }
                $log_entry = sprintf("[%s] [%s] [%s] %s %s\n",
                    $timestamp,
                    strtoupper($level),
                    $request_id,
                    $message,
                    $json_data
                );

                // 确保日志目录存在
                $log_dir = dirname($log_file);
                if (!is_dir($log_dir)) {
                    mkdir($log_dir, 0755, true);
                }

                $result = file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
                if ($result === false) {
                    // 如果写入失败，尝试写入系统日志
                    Log::error('[CainiaoShipment] WriteLog failed', [
                        'log_file' => $log_file,
                        'message' => $message,
                        'level' => $level
                    ]);
                }
            } catch (\Throwable $e) {
                // 写日志失败时记录到系统日志
                Log::error('[CainiaoShipment] WriteLog exception', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'original_message' => $message,
                    'original_level' => $level
                ]);
            }
        };

        $writeLog('INFO', '========== CainiaoShipment 开始执行 ==========', [
            'request_params' => $this->data_request,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        Log::info('[CainiaoShipment] enter', ['request_id' => $request_id, 'params' => json_encode($this->data_request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

        // 1) 参数校验
        $writeLog('INFO', '步骤1: 开始参数校验');
        $params = $this->data_request;
        $order_id = isset($params['id']) ? intval($params['id']) : 0;
        $writeLog('INFO', '参数解析完成', ['order_id' => $order_id, 'params_keys' => array_keys($params)]);

        if ($order_id <= 0) {
            $writeLog('ERROR', '参数校验失败: 订单ID无效', ['order_id' => $order_id]);
            Log::warning('[CainiaoShipment] invalid order id', ['order_id' => $order_id]);
            return ApiService::ApiDataReturn(DataReturn('参数有误：缺少订单ID', -1));
        }
        $writeLog('INFO', '步骤1: 参数校验成功', ['order_id' => $order_id]);

        // 注册错误处理函数，捕获致命错误
        register_shutdown_function(function() use ($writeLog, $request_id) {
            $error = error_get_last();
            if ($error !== null && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR)) {
                $writeLog('FATAL', 'PHP致命错误', [
                    'error_type' => $error['type'],
                    'error_message' => $error['message'],
                    'error_file' => $error['file'],
                    'error_line' => $error['line']
                ]);
            }
        });

        try {
            $writeLog('INFO', '步骤2: 开始菜鸟配置');

            // 2) 菜鸟配置（按你提供的 wxconfig.php 硬编码）
            $writeLog('INFO', '正在构建菜鸟配置数组');

            $cfg = array_merge(CainiaoConfigService::BaseConfig(), [
                'environment'    => 'production',
                'warehouse_name' => '菜鸟金华义乌综保保税中心仓F1255',
                'log_to_db'      => true,
                'auto_check_warehouse' => true,
                'warehouse_keywords'   => ['菜鸟', 'cainiao', '菜鸟仓'],
                'currency'       => 'CNY',
                'pay_channel'    => 'WEIXINPAY',
            ]);
            $cfg['wx_appid']    = 'wxd3733ec9c0b1be60';
            $cfg['wx_mchid']    = '1727065102';
            $cfg['wx_mch_customs_no']    = '3301960G5S';
            $cfg['wx_customs']    = 'HANGZHOU_ZS';
            $cfg['wx_api_key_v2']    = 'Z4mT8qV1fL7yH2cX9pD5kR3wS6bN0aJg';

            $cfg['owner_user_id']    = '2220576876930';
            $cfg['business_unit_id'] = 'B06738021';
            $cfg['order_type']       = 'BONDED_WHS';
            $cfg['order_source']     = '1280';
            $cfg['order_sub_source'] = '';
            $cfg['sale_mode']        = '1';
            $cfg['store_code']       = 'YWZ806';
            $cfg['shop_name']        = '杭州圣劳诗';
            $cfg['shop_id']          = '杭州圣劳诗';
            $cfg['sender_name']      = '杭州圣劳诗';
            $cfg['sender_mobile']    = '0571-12345678';
            $cfg['sender_province']  = '浙江省';
            $cfg['sender_city']      = '杭州市';
            $cfg['sender_area']      = '西湖区';
            $cfg['sender_address']   = '云栖小镇';

            $writeLog('INFO', '菜鸟配置数组构建完成');

        } catch (\Throwable $e) {
            $writeLog('ERROR', '构建菜鸟配置时发生异常', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiService::ApiDataReturn(DataReturn('菜鸟配置构建失败：'.$e->getMessage(), -1));
        }

        $writeLog('INFO', '步骤2: 配置信息准备完成', [
            'environment' => $cfg['environment'],
            'store_code' => $cfg['store_code'],
            'order_type' => $cfg['order_type']
        ]);

        $cpCode    = trim($cfg['resource_code'] ?? '');
        $appSecret = trim($cfg['app_secret'] ?? '');
        $writeLog('INFO', '步骤2: 开始配置参数校验', [
            'cpCode_length' => strlen($cpCode),
            'appSecret_length' => strlen($appSecret)
        ]);

        if ($cpCode === '' || $appSecret === '') {
            $writeLog('ERROR', '配置校验失败: 缺少关键参数', ['cpCode_empty' => empty($cpCode), 'appSecret_empty' => empty($appSecret)]);
            Log::error('[CainiaoShipment] missing cpCode or appSecret');
            return ApiService::ApiDataReturn(DataReturn('菜鸟配置参数不完整', -1));
        }

        // 根据实际接口文档要求进行必填校验，owner_user_id 如文档未要求则不强制
        if (empty($cfg['store_code']) || empty($cfg['order_type'])) {
            $writeLog('ERROR', '配置校验失败: 缺少业务配置', [
                'store_code' => $cfg['store_code'] ?? '',
                'order_type' => $cfg['order_type'] ?? ''
            ]);
            Log::error('[CainiaoShipment] missing store_code/order_type', ['cfg' => $cfg]);
            return ApiService::ApiDataReturn(DataReturn('菜鸟业务配置缺失（store_code/order_type）', -1));
        }
        $writeLog('INFO', '步骤2: 配置参数校验成功');

        // 3) 读取订单与明细
        $writeLog('INFO', '步骤3: 开始读取订单数据', ['order_id' => $order_id]);
        try {
            $writeLog('INFO', '正在查询订单主表');
            $orderRow = Db::name('Order')->where('id', $order_id)->find();
            if (empty($orderRow)) {
                $writeLog('ERROR', '订单查询失败: 订单不存在', ['order_id' => $order_id]);
                Log::warning('[CainiaoShipment] order not found', ['order_id' => $order_id]);
                return ApiService::ApiDataReturn(DataReturn('未找到订单', -1));
            }
            $writeLog('INFO', '订单主表查询成功', [
                'order_id' => $orderRow['id'] ?? null,
                'order_no' => $orderRow['order_no'] ?? null,
                'user_id' => $orderRow['user_id'] ?? null,
                'order_status' => $orderRow['order_status'] ?? null
            ]);

            // 获取订单关联信息
            $pay_log_value = Db::name('PayLogValue')->where(['business_no'=>$orderRow['order_no']])->find();
            if(empty($pay_log_value))
            {
                $writeLog('ERROR', ' 获取订单关联信息失败: 订单不存在', ['order_no' => $orderRow['order_no']]);
                Log::warning('[CainiaoShipment] PayLogValue not found', ['order_no' => $orderRow['order_no']]);
                return ApiService::ApiDataReturn(DataReturn('获取订单关联信息失败', -1));
            }
            // 获取支付日志订单
            $pay_log_data = Db::name('PayLog')->where(['id'=>$pay_log_value['pay_log_id']])->find();
            if(empty($pay_log_data))
            {
                $writeLog('ERROR', ' 获取支付日志订单: 订单不存在', ['id' => $pay_log_value['pay_log_id']]);
                Log::warning('[CainiaoShipment] PayLog not found', ['id' => $pay_log_value['pay_log_id']]);
                return ApiService::ApiDataReturn(DataReturn('获取支付日志订单', -1));
            }

            $writeLog('INFO', '获取支付日志订单', [
                'log_no' => $pay_log_data['log_no'] ?? null,
                'trade_no' => $pay_log_data['trade_no'] ?? null,
                'buyer_user' => $pay_log_data['buyer_user'] ?? null
            ]);

            
            $writeLog('INFO', '正在查询订单明细');
            $details = Db::name('OrderDetail')->where('order_id', $order_id)->select()->toArray();
            $writeLog('INFO', '订单明细查询完成', ['details_count' => is_array($details) ? count($details) : 0]);

            // 读取订单地址（用于 receiverInfo）
            $writeLog('INFO', '正在查询订单地址');
            $orderAddress = Db::name('OrderAddress')->where('order_id', $order_id)->find();
            $writeLog('INFO', '订单地址查询完成', [
                'has_address' => !empty($orderAddress),
                'province' => $orderAddress['province_name'] ?? '',
                'city' => $orderAddress['city_name'] ?? '',
                'county' => $orderAddress['county_name'] ?? ''
            ]);

            Log::info('[CainiaoShipment] db loaded', [
                'order' => ['id'=>$orderRow['id'] ?? null, 'order_no'=>$orderRow['order_no'] ?? null],
                'details_count' => is_array($details) ? count($details) : 0,
                'has_address' => empty($orderAddress) ? 0 : 1,
            ]);
        } catch (\Throwable $e) {
            $writeLog('ERROR', '数据库操作异常', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            Log::error('[CainiaoShipment] db error', ['ex' => $e->getMessage()]);
            return ApiService::ApiDataReturn(DataReturn('数据库错误：'.$e->getMessage(), -1));
        }
        $writeLog('INFO', '步骤3: 数据库操作完成');

        // 4) 金额及税费计算
        $declareCalc = $this->prepareDeclareAmounts($orderRow, $details, $cfg, $writeLog);
        $declareSummary = $declareCalc['summary'];
        $itemTaxShare = $declareCalc['items'];
        $itemTotals = $declareCalc['item_totals'];

        $writeLog('INFO', '步骤4: 税费及金额计算完成', $declareSummary);

        // 5) 组装商品项（转换为文档要求的 orderItemList 结构）
        $writeLog('INFO', '步骤5: 开始组装商品项', ['details_count' => count($details)]);
        $items = [];
        $sumCount = 0;
        $sumAmount = 0.0;
        if (!empty($details)) {
            $writeLog('INFO', '开始处理订单明细');
            foreach ($details as $index => $d) {
                $title      = $d['title'] ?? '商品';
                $count      = (int)($d['buy_number'] ?? 1);
                $price      = (float)($d['price'] ?? 0);
                $totalPrice = isset($itemTotals[$index]) ? $itemTotals[$index] : (float)($d['total_price'] ?? ($price * $count));
                $itemId     = (string)($d['spec_coding'] ?? $d['goods_id'] ?? '');

                $writeLog('INFO', "处理商品 #{$index}", [
                    'title' => $title,
                    'itemId' => $itemId,
                    'count' => $count,
                    'price' => $price,
                    'totalPrice' => $totalPrice
                ]);

                // 声明信息（金额单位存在差异，通常为分，以下以分为单位上送，按需调整）
                $taxShare = $itemTaxShare[$index] ?? [
                    'customs_tax' => 0.0,
                    'consumption_tax' => 0.0,
                    'vat' => 0.0,
                    'total_tax' => 0.0,
                ];
                $item = [
                    'itemId'       => $itemId,
                    'itemQuantity' => $count,
                    'declareInfo'  => [
                        'itemTotalPrice'       => $this->toCent($totalPrice),
                        'itemTotalActualPrice' => $this->toCent($totalPrice),
                        'customsTax'           => $this->toCent($taxShare['customs_tax']),
                        'consumptionTax'       => $this->toCent($taxShare['consumption_tax']),
                        'vat'                  => $this->toCent($taxShare['vat']),
                        'totalTax'             => $this->toCent($taxShare['total_tax']),
                    ],
                ];
                $items[] = $item;
                $writeLog('INFO', "商品项组装完成 #{$index}", ['item' => $item]);

                $sumCount  += $count;
                $sumAmount += $totalPrice;
            }
            $sumAmount = $declareSummary['goods_total_price'];
            $writeLog('INFO', '所有商品项处理完成', ['total_count' => $sumCount, 'total_amount' => $sumAmount]);
        } else {
            $writeLog('WARNING', '无订单明细，使用默认商品项');
            $items[] = [
                'itemId'       => 'DEFAULT',
                'itemQuantity' => 1,
                'declareInfo'  => [
                    'itemTotalPrice'       => 0,
                    'itemTotalActualPrice' => 0,
                    'customsTax'           => 0,
                    'consumptionTax'       => 0,
                    'vat'                  => 0,
                    'totalTax'             => 0,
                ],
            ];
            $sumCount  = 1;
            $sumAmount = $declareSummary['goods_total_price'];
        }

        // 6) 汇总字段与收件人/发件人
        $itemCount   = (int)($orderRow['buy_number_count'] ?? $sumCount);
        $totalAmount = (float)($orderRow['total_price'] ?? $sumAmount);
        // 使用订单地址表中的信息作为收件人信息来源
        $receiverTel = $orderAddress['tel'] ?? ($orderRow['tel'] ?? '');
        $receiverName = $orderAddress['name'] ?? '收件人';
            
        $receiverCountry = 'CN';
        $receiverProvince = $orderAddress['province_name'] ?? ($orderRow['receive_province'] ?? '');
        $receiverCity     = $orderAddress['city_name'] ?? ($orderRow['receive_city'] ?? '');
        $receiverCounty   = $orderAddress['county_name'] ?? ($orderRow['receive_county'] ?? '');
        $receiverAddress  = $orderAddress['address'] ?? ($orderRow['receive_address'] ?? '');

        // 订单时间
    $orderCreateTime = !empty($orderRow['add_time']) ? date('Y-m-d H:i:s', (int)$orderRow['add_time']) : date('Y-m-d H:i:s');
    $orderPayTime    = !empty($orderRow['pay_time']) ? date('Y-m-d H:i:s', (int)$orderRow['pay_time']) : $orderCreateTime;
    Log::info('[CainiaoShipment] order time', ['create' => $orderCreateTime, 'pay' => $orderPayTime]);

        // customsDeclareInfo - 优先从插件订单商品表单中读取（证件姓名/证件号码），无则从常见订单字段兜底
        $buyerNameFromForm = '';
        $buyerIdNoFromForm = '';
        if (!empty($details)) {
            $firstGoodsId = $details[0]['goods_id'] ?? null;
            if (!empty($firstGoodsId)) {
                // 1) 优先从订单维度的插件表单数据读取
                $formRows = Db::name('PluginsOrdergoodsformOrderData')
                    ->where(['order_id' => $order_id, 'goods_id' => $firstGoodsId])
                    ->select()->toArray();
                if (!empty($formRows)) {
                    foreach ($formRows as $fr) {
                        $title = $fr['title'] ?? '';
                        $content = trim((string)($fr['content'] ?? ''));
                        if ($content === '') { continue; }
                        if ($buyerNameFromForm === '' && (strpos($title, '证件姓名') !== false || strpos($title, '姓名') !== false)) {
                            $buyerNameFromForm = $content;
                        }
                        if ($buyerIdNoFromForm === '' && (strpos($title, '证件号码') !== false || strpos($title, '身份证') !== false || strpos($title, '证件号') !== false)) {
                            $buyerIdNoFromForm = $content;
                        }
                    }
                }
                // 2) 如订单数据未取到，则尝试从用户商品表单数据读取
                if (($buyerNameFromForm === '' || $buyerIdNoFromForm === '') && !empty($orderRow['user_id'])) {
                    $goodsFormRows = Db::name('PluginsOrdergoodsformGoodsData')
                        ->where(['goods_id' => $firstGoodsId, 'user_id' => $orderRow['user_id']])
                        ->select()->toArray();
                    if (!empty($goodsFormRows)) {
                        foreach ($goodsFormRows as $gr) {
                            $title = $gr['title'] ?? '';
                            $content = trim((string)($gr['content'] ?? ''));
                            if ($content === '') { continue; }
                            if ($buyerNameFromForm === '' && (strpos($title, '证件姓名') !== false || strpos($title, '姓名') !== false)) {
                                $buyerNameFromForm = $content;
                            }
                            if ($buyerIdNoFromForm === '' && (strpos($title, '证件号码') !== false || strpos($title, '身份证') !== false || strpos($title, '证件号') !== false)) {
                                $buyerIdNoFromForm = $content;
                            }
                        }
                    }
                }
            }
        }

        // 订单字段兜底
        $buyerNameFallback = $receiverName;
        $buyerIdNoFallback = $orderRow['buyer_id_no']
            ?? $orderRow['idcard_no']
            ?? $orderRow['id_card']
            ?? $orderRow['idcard']
            ?? '';

        $buyerName = $buyerNameFromForm !== '' ? $buyerNameFromForm : $buyerNameFallback;
        $buyerIdNo = $buyerIdNoFromForm !== '' ? $buyerIdNoFromForm : $buyerIdNoFallback;
        if (empty($buyerIdNo)) {
            Log::warning('[CainiaoShipment] missing buyerIDNo after form+fallback', [
                'buyerNameFromForm' => $buyerNameFromForm,
                'buyerIdNoFromForm' => $buyerIdNoFromForm,
                'buyerIdNoFallback' => $buyerIdNoFallback,
            ]);
            return ApiService::ApiDataReturn(DataReturn('缺少清关所需身份证号 buyerIDNo，请完善订单实名认证信息', -1));
        }
        Log::info('[CainiaoShipment] receiver/customs prepared', [
            'receiver' => [
                'name' => $receiverName,
                'tel' => $receiverTel,
                'province' => $receiverProvince,
                'city' => $receiverCity,
                'district' => $receiverCounty,
                'address' => $receiverAddress,
            ],
            'customs' => [
                'buyerName' => $buyerName,
                'buyerIDNo_len' => strlen($buyerIdNo),
            ],
        ]);

        // 6) 组装文档要求的请求数据（logistics_interface）
        $writeLog('INFO', '步骤6: 开始组装请求数据');
        $externalOrderCode = (string)($orderRow['order_no'] ?? $order_id);
        $channelSourceCode = isset($cfg['order_source']) ? (string)$cfg['order_source'] : '';
        if ($channelSourceCode === '') {
            $channelSourceCode = $externalOrderCode;
        }

        $request_data = [
            'ownerUserId'       => (string)$cfg['owner_user_id'],
            'externalOrderCode' => $externalOrderCode,
            'orderType'         => (string)$cfg['order_type'],
            'storeCode'         => (string)$cfg['store_code'],
            'externalShopName'  => (string)$cfg['shop_name'],
            'orderSource'       => $channelSourceCode,
            'orderCreateTime'   => $orderCreateTime,
            'orderPayTime'      => $orderPayTime,
            'saleMode'          => (string)$cfg['sale_mode'],
            'receiverInfo' => [
                'country'  => $receiverCountry,
                'province' => (string)$receiverProvince,
                'city'     => (string)$receiverCity,
                'district' => (string)$receiverCounty,
                'address'  => (string)$receiverAddress,
                'name'     => (string)$receiverName,
                'contactNo'=> (string)$receiverTel,
            ],
            'senderInfo' => [
                'country'  => 'CN',
                'province' => (string)$cfg['sender_province'],
                'city'     => (string)$cfg['sender_city'],
                'district' => (string)$cfg['sender_area'],
                'address'  => (string)$cfg['sender_address'],
                'name'     => (string)$cfg['sender_name'],
                'contactNo'=> (string)$cfg['sender_mobile'],
            ],
            'refunderInfo' => [
                'country'  => 'CN',
                'province' => (string)$cfg['sender_province'],
                'city'     => (string)$cfg['sender_city'],
                'district' => (string)$cfg['sender_area'],
                'address'  => (string)$cfg['sender_address'],
                'name'     => (string)$cfg['sender_name'],
                'contactNo'=> (string)$cfg['sender_mobile'],
            ],
            // 结构调整为对象+数组：{"orderItem":[{...},{...}]}
            'orderItemList' => [ 'orderItem' => $items ],
            'orderAmountInfo' => [
                'dutiablePrice'  => $this->toCent($declareSummary['dutiable_price']),
                'customsTax'     => $this->toCent($declareSummary['customs_tax']),
                'consumptionTax' => $this->toCent($declareSummary['consumption_tax']),
                'vat'            => $this->toCent($declareSummary['vat']),
                'totalTax'       => $this->toCent($declareSummary['total_tax']),
                'insurance'      => $this->toCent($declareSummary['insurance']),
                'coupon'         => $this->toCent($declareSummary['coupon']),
                'actualPayment'  => $this->toCent($declareSummary['actual_payment']),
                'postFee'        => $this->toCent($declareSummary['post_fee']),
                'currency'       => (string)$cfg['currency'],
            ],
            'customsDeclareInfo' => [
                'buyerName'       => (string)$buyerName,
                'buyerPlatformId' => (string)($orderRow['user_id'] ?? ''),
                'buyerIDType'     => '1',
                'buyerIDNo'       => (string)$buyerIdNo,
                'payChannel'      => (string)$cfg['pay_channel'],
                'payOrderId'      => (string)($pay_log_data['trade_no'] ?? null),
                'nationality'     => 'CN',
                'contactNo'       => (string)$receiverTel,
            ],
        ];

        // 可选字段按配置补充
        if (!empty($cfg['business_unit_id'])) {
            $request_data['businessUnitId'] = (string)$cfg['business_unit_id'];
        }
        if (!empty($cfg['order_sub_source'])) {
            $request_data['orderSubSource'] = (string)$cfg['order_sub_source'];
        }
        if (!empty($cfg['shop_id'])) {
            $request_data['externalShopId'] = (string)$cfg['shop_id'];
        }

        $writeLog('INFO', '步骤6: 请求数据组装完成 - 组装的完整数据内容', [
            'request_data' => $request_data
        ]);
        // 6.1) 微信支付清关申报（先清关，成功后再调用菜鸟接口）
        $writeLog('INFO', '步骤6.1: 调用微信支付清关申报API（WeChat customs declare）');

        // 证件/姓名从前面解析结果或订单字段兜底
        $buyerNameForWx = $buyerName ?? ($receiverName ?? '');
        $buyerIdNoForWx = $buyerIdNo ?? '';

        $wxOrderFeeYuan     = $pay_log_data['total_price'] ?? ($orderRow['total_price'] ?? ($this->data_request['order_fee_yuan'] ?? 0));
        $wxTransportFeeYuan = $orderRow['express_price'] ?? ($this->data_request['transport_fee_yuan'] ?? 0);
        $wxOrderFee         = $this->toCent($wxOrderFeeYuan);
        $wxTransportFee     = $this->toCent($wxTransportFeeYuan);
        $wxProductFee       = max(0, $wxOrderFee - $wxTransportFee);

        // 组装微信清关参数（可从配置/订单映射，也可通过前端传入覆盖）
        $writeLog('INFO', '微信清关金额计算', [
            'order_fee_yuan' => $wxOrderFeeYuan,
            'transport_fee_yuan' => $wxTransportFeeYuan,
            'order_fee_cent' => $wxOrderFee,
            'transport_fee_cent' => $wxTransportFee,
            'product_fee_cent' => $wxProductFee,
        ]);

        $wxReq = [
            'appid'           => $cfg['wx_appid'] ?? ($this->data_request['wx_appid'] ?? ''),
            'mch_id'          => $cfg['wx_mchid'] ?? ($this->data_request['wx_mchid'] ?? ''),
            'mch_customs_no'  => $cfg['wx_mch_customs_no'] ?? ($this->data_request['wx_mch_customs_no'] ?? ''),
            'customs'         => $cfg['wx_customs'] ?? ($this->data_request['wx_customs'] ?? 'ZHENGZHOU_ZHENGGAO'),
            'cert_key'        => $cfg['wx_api_key_v2'] ?? ($this->data_request['wx_api_key_v2'] ?? ''),
            'transaction_id'  => $pay_log_data['trade_no'] ?? ($orderRow['payment_no'] ?? ($this->data_request['transaction_id'] ?? '')),
            'out_trade_no'    => $orderRow['order_no'] ?? ($this->data_request['out_trade_no'] ?? ''),
            // 移除所有拆单相关字段：order_fee, transport_fee, product_fee, fee_type
            // 个人证件相关字段：参与签名计算并提交到XML，使用正确的参数名
            'cert_type'       => $this->data_request['cert_type'] ?? ($cfg['wx_certificate_type'] ?? 'IDCARD'),
            'cert_id'         => $this->data_request['cert_id'] ?? $buyerIdNoForWx,
            'name'            => $this->data_request['name'] ?? $buyerNameForWx,
            // 移除buyer_country：不存在拆单情况时不需要
            // 'buyer_country'   => $this->data_request['buyer_country'] ?? ($receiverCountry ?? 'CN'),
        ];

        // 移除所有费用分拆相关的覆盖逻辑

        $wxResult = $this->WechatCustomsDeclareProxy($wxReq, $writeLog);

        if (($wxResult['code'] ?? -1) != 0) {
            $writeLog('ERROR', '微信清关失败，中止菜鸟发货', $wxResult);
            return ApiService::ApiDataReturn(DataReturn('微信清关失败：'.($wxResult['msg'] ?? 'unknown'), -1, $wxResult));
        }

        $writeLog('INFO', '微信清关成功，继续菜鸟发货', $wxResult);


        // 7) 组装请求参数并调用接口
        $writeLog('INFO', '步骤7: 开始组装API请求参数');
        $apiResult = $this->callCainiaoApi('GLOBAL_SALE_ORDER_NOTIFY', $request_data, $cfg, $writeLog);
        $request_params = $apiResult['request_params'];
        $writeLog('INFO', '请求参数组装完成', [
            'msg_type' => $request_params['msg_type'] ?? '',
            'logistic_provider_id' => $request_params['logistic_provider_id'] ?? '',
            'to_code' => $request_params['to_code'] ?? '',
            'data_digest_length' => strlen($request_params['data_digest'] ?? ''),
        ]);

        $maskedSecret = substr($appSecret, 0, 4).'****'.substr($appSecret, -4);
        Log::info('[CainiaoShipment] request built', [
            'url_env' => $cfg['environment'],
            'msg_type' => $request_params['msg_type'] ?? '',
            'logistic_provider_id' => $request_params['logistic_provider_id'] ?? '',
            'to_code' => $request_params['to_code'] ?? '',
            'data_digest_len' => strlen($request_params['data_digest'] ?? ''),
            'app_secret_masked' => $maskedSecret,
        ]);
        Log::debug('[CainiaoShipment] logistics_interface', ['body' => is_array($request_data) ? json_encode($request_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $request_data]);

        $writeLog('INFO', '步骤8: 接口调用完成', [
            'url' => $apiResult['url'],
            'duration_ms' => $apiResult['duration_ms'],
            'success_flag' => $apiResult['success_flag'],
        ]);

        // 写入数据库日志（记录接口调用历史）
        try {
            $insert_data = [
                'express_type'   => 'cainiao',
                'express_name'   => 'GLOBAL_SALE_ORDER_NOTIFY',
                'express_number' => $orderRow['order_no'] ?? (string)$order_id,
                'express_code'   => 'CAINIAO',
                'request_params' => json_encode($request_params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_data'  => json_encode($apiResult['response_raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'add_time'       => time(),
            ];
            Db::name('PluginsExpressLog')->insertGetId($insert_data);
            $writeLog('INFO', '数据库日志记录成功');
        } catch (\Throwable $e) {
            $writeLog('ERROR', '数据库日志记录异常', [
                'error' => $e->getMessage()
            ]);
        }

        // 9) 解析返回
        $writeLog('INFO', '步骤9: 开始解析API响应');
        $response = $apiResult['response'];
        $writeLog('INFO', 'HTTP响应概览', [
            'success_flag' => $apiResult['success_flag'],
            'error_code'   => $apiResult['error_code'],
            'error_msg'    => $apiResult['error_msg'],
            'response_keys'=> is_array($response) ? array_keys($response) : [],
        ]);

        if ($apiResult['success']) {
            $result_data = $response['result'] ?? $response['Result'] ?? [
                'cnOrderCode'       => $response['cnOrderCode'] ?? null,
                'externalOrderCode' => $response['externalOrderCode'] ?? null,
            ];
            if (is_string($result_data)) {
                $writeLog('INFO', '结果数据为字符串，尝试JSON解析');
                $tmp = json_decode($result_data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $result_data = $tmp;
                    $writeLog('INFO', '结果数据JSON解析成功');
                } else {
                    $writeLog('WARNING', '结果数据JSON解析失败', ['error' => json_last_error_msg()]);
                }
            }
            $writeLog('INFO', '======= 菜鸟接口调用成功 =======', [
                'total_duration_ms' => round((microtime(true) - $start_time) * 1000, 2),
                'order_id' => $order_id,
                'order_no' => $orderRow['order_no'] ?? '',
                'result_data' => $result_data,
            ]);
            return ApiService::ApiDataReturn(DataReturn('发货成功', 0, $result_data));
        }

        $error_msg = $apiResult['error_msg'] ?: '菜鸟发货接口请求失败';
        $writeLog('ERROR', '======= 菜鸟接口调用失败 =======', [
            'order_id' => $order_id,
            'order_no' => $orderRow['order_no'] ?? '',
            'error_msg' => $error_msg,
            'error_code'=> $apiResult['error_code'],
            'response' => $response,
        ]);
        return ApiService::ApiDataReturn(DataReturn($error_msg, -1, [
            'error_code' => $apiResult['error_code'],
            'success'    => $apiResult['success_flag'],
        ]));
    }


    /**
     * 取消菜鸟发货
     * 路由：admin/cainiao/cainiaoshipmentcancel
     */
    public function CainiaoShipmentCancel()
    {
        $cancel_log_file = root_path('runtime/log') . 'cainiao_cancel_' . date('Y-m-d') . '.log';
        $cancel_request_id = md5('cancel'.uniqid('', true));
        $cancelWriteLog = function($level, $message, $data = []) use ($cancel_log_file, $cancel_request_id) {
            try {
                $timestamp = date('Y-m-d H:i:s');
                $json_data = '';
                if (!empty($data)) {
                    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $json_data = 'JSON_ERROR: '.json_last_error_msg();
                    }
                }
                $log_dir = dirname($cancel_log_file);
                if (!is_dir($log_dir)) {
                    mkdir($log_dir, 0755, true);
                }
                $entry = sprintf('[%s] [%s] [%s] %s %s%s',
                    $timestamp,
                    strtoupper($level),
                    $cancel_request_id,
                    $message,
                    $json_data,
                    PHP_EOL
                );
                file_put_contents($cancel_log_file, $entry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                Log::error('[CainiaoShipmentCancel] write cancel log failed', [
                    'error' => $e->getMessage(),
                    'original_message' => $message,
                ]);
            }
        };

        $cancelWriteLog('INFO', '======== CainiaoShipmentCancel start ========', [
            'request_params' => $this->data_request,
            'request_id'     => $cancel_request_id,
        ]);

        $params   = $this->data_request;
        $order_id = isset($params['id']) ? intval($params['id']) : 0;
        if ($order_id <= 0) {
            $cancelWriteLog('ERROR', 'invalid order id', ['order_id' => $order_id]);
            return ApiService::ApiDataReturn(DataReturn('参数有误：缺少订单ID', -1));
        }

        $cancel_reason        = trim($params['reason'] ?? '管理员取消发货');
        $logistics_order_code = trim($params['logistics_order_code'] ?? '');
        $cancelWriteLog('INFO', 'basic params parsed', [
            'order_id' => $order_id,
            'cancel_reason' => $cancel_reason,
            'logistics_order_code' => $logistics_order_code,
        ]);

        try {
            $orderRow = Db::name('Order')
                ->field('id, order_no, user_id, pay_time, delivery_time')
                ->where('id', $order_id)
                ->find();
        } catch (\Throwable $e) {
            Log::error('[CainiaoShipmentCancel] db error', ['order_id' => $order_id, 'ex' => $e->getMessage()]);
            $cancelWriteLog('ERROR', 'db exception when fetching order', ['error' => $e->getMessage()]);
            return ApiService::ApiDataReturn(DataReturn('数据库错误：'.$e->getMessage(), -1));
        }

        if (empty($orderRow)) {
            Log::warning('[CainiaoShipmentCancel] order not found', ['order_id' => $order_id]);
            $cancelWriteLog('ERROR', 'order not found', ['order_id' => $order_id]);
            return ApiService::ApiDataReturn(DataReturn('未找到订单', -1));
        }
        $cancelWriteLog('INFO', 'order loaded', ['order' => $orderRow]);

        // 固定配置（优先与发货接口保持一致）
        $cfg = $this->getCainiaoBaseConfig();

        $cpCode    = trim($cfg['resource_code'] ?? '');
        $appSecret = trim($cfg['app_secret'] ?? '');
        if ($cpCode === '' || $appSecret === '') {
            Log::error('[CainiaoShipmentCancel] missing cpCode/appSecret', ['cfg' => $cfg]);
            $cancelWriteLog('ERROR', 'config missing cpCode/appSecret', ['cfg' => $cfg]);
            return ApiService::ApiDataReturn(DataReturn('菜鸟配置参数不完整', -1));
        }
        $cancelWriteLog('INFO', 'config ready', ['cpCode' => $cpCode, 'environment' => $cfg['environment']]);

        $orderSource = isset($cfg['order_source']) ? (string)$cfg['order_source'] : '1280';
        $externalOrderCode = (string)($orderRow['order_no'] ?? $order_id);
        $userId = isset($cfg['owner_user_id']) ? (string)$cfg['owner_user_id'] : '';
        $extUserId = isset($cfg['shop_id']) ? (string)$cfg['shop_id'] : '';

        $cancelRequest = [
            'userId'          => $userId,
            'orderSource'     => $orderSource,
            'externalOrderId' => $externalOrderCode,
        ];

        if ($logistics_order_code !== '') {
            $cancelRequest['lgOrderCode'] = $logistics_order_code;
        }
        if ($extUserId !== '') {
            $cancelRequest['extUserId'] = $extUserId;
        }

        $extendFields = [];
        if ($cancel_reason !== '') {
            $extendFields['cancelReason'] = $cancel_reason;
        }
        if (!empty($extendFields)) {
            $cancelRequest['extendFields'] = $extendFields;
        }

        $cancel_payload = ['request' => $cancelRequest];
        $cancelWriteLog('INFO', 'payload assembled', ['payload' => $cancel_payload]);

        $apiResult = $this->callCainiaoApi('GLOBAL_SALE_ORDER_CANCEL', $cancel_payload, $cfg, $cancelWriteLog);

        Log::info('[CainiaoShipmentCancel] request', [
            'order_id' => $order_id,
            'order_no' => $orderRow['order_no'] ?? '',
            'url'      => $apiResult['url'],
        ]);

        if ($apiResult['success']) {
            $result_data = $apiResult['response']['result'] ?? $apiResult['response']['Result'] ?? [];
            if (empty($result_data)) {
                $result_data = $apiResult['response'];
            }
            $cancelWriteLog('INFO', 'cancel success', ['result' => $result_data]);
            return ApiService::ApiDataReturn(DataReturn('取消发货成功', 0, $result_data));
        }

        $error_msg = $apiResult['error_msg'] ?: '菜鸟取消发货接口请求失败';
        Log::warning('[CainiaoShipmentCancel] response error', [
            'order_id'    => $order_id,
            'error_code'  => $apiResult['error_code'],
            'success'     => $apiResult['success_flag'],
            'error_msg'   => $error_msg,
            'response'    => $apiResult['response'],
        ]);
        $cancelWriteLog('ERROR', 'cancel failed', [
            'error_msg'   => $error_msg,
            'error_code'  => $apiResult['error_code'],
            'success_flag'=> $apiResult['success_flag'],
            'response_raw'=> $apiResult['response_raw'],
        ]);
        return ApiService::ApiDataReturn(DataReturn($error_msg, -1, [
            'error_code' => $apiResult['error_code'],
            'success'    => $apiResult['success_flag'],
        ]));
    }


    /**
     * 保税订单金额与税费计算
     */
    private function prepareDeclareAmounts(array $orderRow, array $details, array $cfg, callable $writeLog): array
    {
        $itemTotals = [];
        $goodsTotal = 0.0;

        foreach ($details as $index => $detail) {
            $count = (int)($detail['buy_number'] ?? 1);
            $price = (float)($detail['price'] ?? 0);
            $lineTotal = $detail['total_price'] ?? ($price * $count);
            $lineTotal = $this->roundAmount((float)$lineTotal);
            $itemTotals[$index] = $lineTotal;
            $goodsTotal += $lineTotal;
        }

        $goodsTotal = $this->roundAmount($goodsTotal);

        if ($goodsTotal <= 0.0) {
            $estimate = (float)($orderRow['total_price'] ?? 0) - (float)($orderRow['express_price'] ?? 0);
            if ($estimate > 0) {
                $goodsTotal = $this->roundAmount($estimate);
            }
        }

        $postFee = $this->roundAmount((float)($orderRow['express_price'] ?? 0));
        $insurance = $this->roundAmount($this->extractInsuranceAmount($orderRow, $cfg));
        $coupon = $this->roundAmount((float)($orderRow['preferential_price'] ?? 0));
        $actualPayment = $this->roundAmount((float)($orderRow['pay_price'] ?? 0));

        $dutiablePrice = $this->roundAmount($goodsTotal + $postFee + $insurance);

        $rates = $this->resolveTaxRates($orderRow, $cfg);
        $discount = $rates['discount'];

        $customsTax = $this->roundAmount($dutiablePrice * $rates['customs'] * $discount);

        $consumptionTax = 0.0;
        if ($rates['consumption'] > 0 && $rates['consumption'] < 1) {
            $base = $dutiablePrice + $customsTax;
            $consumptionTax = $this->roundAmount(($base * $rates['consumption'] / (1 - $rates['consumption'])) * $discount);
        } elseif ($rates['consumption'] >= 1) {
            $writeLog('WARNING', '消费税税率配置异常，已忽略消费税计算', $rates);
        }

        $vat = $this->roundAmount(($dutiablePrice + $customsTax + $consumptionTax) * $rates['vat'] * $discount);
        $totalTax = $this->roundAmount($customsTax + $consumptionTax + $vat);
        $expectedPayment = $this->roundAmount($dutiablePrice + $totalTax - $coupon);
        $validPayment = abs($expectedPayment - $actualPayment) <= 0.05;

        if (!$validPayment) {
            $writeLog('WARNING', '实付金额校验未通过', [
                'expected' => $expectedPayment,
                'actual' => $actualPayment,
                'difference' => $this->roundAmount($expectedPayment - $actualPayment),
            ]);
        }

        $itemShares = [];
        if ($goodsTotal > 0 && !empty($itemTotals)) {
            $itemSharesCustoms = $this->allocateTaxAmounts($customsTax, $itemTotals, $goodsTotal);
            $itemSharesConsumption = $this->allocateTaxAmounts($consumptionTax, $itemTotals, $goodsTotal);
            $itemSharesVat = $this->allocateTaxAmounts($vat, $itemTotals, $goodsTotal);
            foreach ($itemTotals as $index => $total) {
                $itemShares[$index] = [
                    'customs_tax' => $itemSharesCustoms[$index] ?? 0.0,
                    'consumption_tax' => $itemSharesConsumption[$index] ?? 0.0,
                    'vat' => $itemSharesVat[$index] ?? 0.0,
                    'total_tax' => $this->roundAmount(($itemSharesCustoms[$index] ?? 0.0)
                        + ($itemSharesConsumption[$index] ?? 0.0)
                        + ($itemSharesVat[$index] ?? 0.0)),
                ];
            }
        }

        return [
            'summary' => [
                'goods_total_price' => $goodsTotal,
                'post_fee' => $postFee,
                'insurance' => $insurance,
                'dutiable_price' => $dutiablePrice,
                'customs_tax' => $customsTax,
                'consumption_tax' => $consumptionTax,
                'vat' => $vat,
                'total_tax' => $totalTax,
                'coupon' => $coupon,
                'actual_payment' => $actualPayment,
                'expected_payment' => $expectedPayment,
                'discount' => $discount,
                'rates' => $rates,
                'valid_payment' => $validPayment,
            ],
            'items' => $itemShares,
            'item_totals' => $itemTotals,
        ];
    }


    /**
     * 税率解析，订单字段优先
     */
    private function resolveTaxRates(array $orderRow, array $cfg): array
    {
        $ratesCfg = $cfg['tax_rates'] ?? [];
        $rates = [
            'customs' => isset($ratesCfg['customs']) ? (float)$ratesCfg['customs'] : 0.0,
            'consumption' => isset($ratesCfg['consumption']) ? (float)$ratesCfg['consumption'] : 0.0,
            'vat' => isset($ratesCfg['vat']) ? (float)$ratesCfg['vat'] : 0.0,
            'discount' => isset($cfg['tax_discount']) ? (float)$cfg['tax_discount'] : 1.0,
        ];

        $orderOverrides = [
            'customs' => $orderRow['customs_tax_rate'] ?? ($orderRow['customs_taxrate'] ?? null),
            'consumption' => $orderRow['consumption_tax_rate'] ?? ($orderRow['consumption_taxrate'] ?? null),
            'vat' => $orderRow['vat_rate'] ?? ($orderRow['vatrate'] ?? null),
            'discount' => $orderRow['bonded_discount'] ?? ($orderRow['discount'] ?? null),
        ];

        foreach ($orderOverrides as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $rates[$key] = (float)$value;
        }

        if ($rates['discount'] <= 0) {
            $rates['discount'] = 1.0;
        }

        return $rates;
    }


    /**
     * 提取订单保险金额，支持配置字段
     */
    private function extractInsuranceAmount(array $orderRow, array $cfg): float
    {
        if (isset($orderRow['insurance_price'])) {
            return (float)$orderRow['insurance_price'];
        }
        if (isset($orderRow['insurance'])) {
            return (float)$orderRow['insurance'];
        }
        if (!empty($cfg['insurance_field']) && isset($orderRow[$cfg['insurance_field']])) {
            return (float)$orderRow[$cfg['insurance_field']];
        }
        return isset($cfg['default_insurance']) ? (float)$cfg['default_insurance'] : 0.0;
    }


    /**
     * 按比例分摊税费（保留两位小数并保证合计一致）
     */
    private function allocateTaxAmounts(float $totalAmount, array $itemTotals, float $goodsTotal): array
    {
        $allocations = [];
        $running = 0.0;
        $lastKey = null;
        foreach ($itemTotals as $key => $value) {
            $lastKey = $key;
        }

        foreach ($itemTotals as $key => $value) {
            if ($totalAmount <= 0 || $goodsTotal <= 0 || $value <= 0) {
                $allocations[$key] = 0.0;
                continue;
            }

            if ($key === $lastKey) {
                $allocations[$key] = $this->roundAmount($totalAmount - $running);
            } else {
                $ratio = $value / $goodsTotal;
                $current = $this->roundAmount($totalAmount * $ratio);
                $allocations[$key] = $current;
                $running = $this->roundAmount($running + $current);
            }
        }

        if ($lastKey !== null && !isset($allocations[$lastKey])) {
            $allocations[$lastKey] = $this->roundAmount($totalAmount - $running);
        }

        return $allocations;
    }


    /**
     * 金额转分
     */
    private function toCent($amount): int
    {
        return (int)round(((float)$amount) * 100);
    }


    /**
     * 金额标准化（保留两位小数）
     */
    private function roundAmount($amount): float
    {
        return round((float)$amount, 2);
    }


    /**
     * 内部代理：以数组参数调用 WechatCustomsDeclare()，返回统一结构
     */
    private function WechatCustomsDeclareProxy(array $req, callable $logger): array
    {
        $logPayload = $req;
        if (isset($logPayload['cert_key'])) {
            $certKey = (string)$logPayload['cert_key'];
            $logPayload['cert_key_masked'] = strlen($certKey) > 8
                ? substr($certKey, 0, 4).'****'.substr($certKey, -4)
                : '****';
            unset($logPayload['cert_key']);
        }
        $logger('INFO', 'WechatCustomsDeclareProxy request payload', $logPayload);

        $orig = $this->data_request;
        $resp = null;
        try {
            $this->data_request = $req;
            $resp = $this->WechatCustomsDeclare();
        } catch (\Throwable $e) {
            $this->data_request = $orig;
            $logger('ERROR', 'WechatCustomsDeclareProxy exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        } finally {
            $this->data_request = $orig;
        }

        $parsed = null;
        $responseType = is_object($resp) ? get_class($resp) : gettype($resp);

        if (is_array($resp)) {
            $parsed = $resp;
        } elseif (is_object($resp)) {
            if (method_exists($resp, 'getData')) {
                try {
                    $parsed = $resp->getData();
                } catch (\Throwable $e) {
                    $logger('WARNING', 'WechatCustomsDeclareProxy getData failed', ['error' => $e->getMessage()]);
                }
            }
            if ($parsed === null && method_exists($resp, 'getContent')) {
                $content = $resp->getContent();
                $decoded = json_decode($content, true);
                $parsed = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
            }
        }

        $logger('INFO', 'WechatCustomsDeclareProxy raw response', [
            'type' => $responseType,
            'parsed' => $parsed,
        ]);

        if (is_array($parsed) && isset($parsed['code']) && $parsed['code'] == 0) {
            $logger('INFO', 'WechatCustomsDeclareProxy normalized response', ['code' => 0, 'msg' => 'success']);
            return ['code'=>0, 'msg'=>'success', 'data'=>$parsed['data'] ?? []];
        }
        if (is_array($parsed)) {
            $logger('WARNING', 'WechatCustomsDeclareProxy non-success response', ['code' => $parsed['code'] ?? null, 'msg' => $parsed['msg'] ?? null]);
            return ['code'=>-1, 'msg'=>$parsed['msg'] ?? 'unknown error', 'data'=>$parsed['data'] ?? []];
        }

        return ['code'=>-1, 'msg'=>'unexpected response', 'data' => ['raw_type' => $responseType, 'raw' => $parsed]];
    }


    /**
     * 菜鸟基础配置
     */
    private function getCainiaoBaseConfig(): array
    {
        return CainiaoConfigService::BaseConfig();
    }


    /**
     * 通用的菜鸟接口请求
     * @param array|string $payload 业务参数（数组会自动 JSON）
     * @param array $cfg 基础配置
     * @param callable|null $logger 可选日志函数($level, $message, $context)
     */
    private function callCainiaoApi(string $msgType, $payload, array $cfg, ?callable $logger = null): array
    {
        $cpCode    = trim($cfg['resource_code'] ?? '');
        $appSecret = trim($cfg['app_secret'] ?? '');

        $content = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($logger) {
            $logger('INFO', '[callCainiaoApi] payload encoded', [
                'payload_type'   => is_array($payload) ? 'array' : gettype($payload),
                'content_length' => strlen($content),
            ]);
        }

        $request_params = [
            'msg_type'             => $msgType,
            'logistic_provider_id' => $cpCode,
            'logistics_interface'  => $content,
        ];
        if (!empty($cfg['to_code'])) {
            $request_params['to_code'] = $cfg['to_code'];
        }
        if ($appSecret !== '') {
            $request_params['data_digest'] = base64_encode(md5($content.$appSecret, true));
        } else {
            $request_params['data_digest'] = '';
        }

        $environment = strtolower($cfg['environment'] ?? 'production');
        $url = ($environment === 'sandbox')
            ? 'http://linkdaily.tbsandbox.com/gateway/link.do'
            : 'https://link.cainiao.com/gateway/link.do';

        if ($logger) {
            $logger('INFO', '[callCainiaoApi] request ready', [
                'msg_type' => $msgType,
                'url'      => $url,
                'request_params' => $request_params,
            ]);
        }

        $start = microtime(true);
        $rawResponse = CurlPost($url, $request_params);
        $duration = round((microtime(true) - $start) * 1000, 2);

        if ($logger) {
            $logger('INFO', '[callCainiaoApi] response received', [
                'duration_ms' => $duration,
                'raw'         => $rawResponse,
            ]);
        }

        $responseData = null;
        $successFlag  = null;
        $errorCode    = null;
        $errorMsg     = null;

        if (is_array($rawResponse) && isset($rawResponse['data'])) {
            $raw = $rawResponse['data'];
            if (is_array($raw)) {
                $responseData = $raw;
            } elseif (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $responseData = $decoded;
                } elseif (strlen($raw) > 0 && $raw[0] === '<') {
                    if ($logger) {
                        $logger('INFO', '[callCainiaoApi] try xml parse');
                    }
                    try {
                        $xml = @simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
                        if ($xml !== false) {
                            $responseData = json_decode(json_encode($xml), true);
                        }
                    } catch (\Throwable $e) {
                        if ($logger) {
                            $logger('ERROR', '[callCainiaoApi] xml parse failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            if (!empty($responseData)) {
                $successFlag = $responseData['success'] ?? $responseData['Success'] ?? null;
                $errorCode   = $responseData['errorCode'] ?? $responseData['ErrorCode'] ?? null;
                $errorMsg    = $responseData['errorMsg'] ?? $responseData['ErrorMsg'] ?? null;
            }

            if ($errorMsg === null) {
                $errorMsg = $rawResponse['msg'] ?? null;
            }
        } else {
            $errorMsg = is_array($rawResponse) ? ($rawResponse['msg'] ?? null) : null;
        }

        $success = in_array($successFlag, [true, 'true', 1, '1'], true);

        return [
            'url'            => $url,
            'request_params' => $request_params,
            'response_raw'   => $rawResponse,
            'response'       => $responseData ?? [],
            'success'        => $success,
            'success_flag'   => $successFlag,
            'error_code'     => $errorCode,
            'error_msg'      => $errorMsg,
            'duration_ms'    => $duration,
        ];
    }

    /**
     * =============================
     * 微信支付 - 清关申报 API（v2 XML）
     * 说明：提供两个接口封装：申报(customdeclareorder) 与 查询(customdeclarequery)。
     * 为避免强耦合，这里不依赖全局配置，必要字段从请求参数读取。
     * 
     * 路由建议：
     *   admin/cainiao/wechatcustomsdeclare
     *   admin/cainiao/wechatcustomsquery
     * 
     * 必填示例（申报）：
     *   appid, mch_id, mch_customs_no, customs,
     *   out_trade_no 或 transaction_id（二选一，推荐先用 transaction_id）,
     *   cert_key(商户API密钥v2, 用于签名), sign_type(MD5|HMAC-SHA256, 默认MD5)
     * 可选：cert_type, cert_id, name, commerce_type, goods_info, etc.
     */
    
    /**
     * 申报接口：/cgi-bin/mch/customs/customdeclareorder
     */
    public function WechatCustomsDeclare()
    {
        $logger = function($level, $message, $context = []) {
            try {
                $log_file = root_path('runtime/log') . 'wechat_customs_' . date('Y-m-d') . '.log';
                $line = '['.date('Y-m-d H:i:s')."][".$level.'] '.$message.' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
                @file_put_contents($log_file, $line, FILE_APPEND);
            } catch (\Throwable $e) {}
        };
        $req = $this->data_request;
        $request_id = 'WXCG-DEC-' . date('YmdHis') . '-' . mt_rand(1000,9999);
        $logger('INFO', 'WeChat customs declare start', ['request_id'=>$request_id,'params'=>$req]);

        // 基础校验
        $appid           = trim($req['appid'] ?? '');
        $mch_id          = trim($req['mch_id'] ?? '');
        $mch_customs_no  = trim($req['mch_customs_no'] ?? '');
        $customs         = trim($req['customs'] ?? '');
        // 移除fee_type参数
        $key_v2          = trim($req['cert_key'] ?? '');
        $sign_type       = strtoupper(trim($req['sign_type'] ?? 'MD5'));
        $transaction_id  = trim($req['transaction_id'] ?? '');
        $out_trade_no    = trim($req['out_trade_no'] ?? '');
        // 移除所有拆单相关参数：order_fee, transport_fee, product_fee

        // 记录密钥长度，不记录实际内容（安全考虑）
        $logger('INFO', 'WeChat key_v2 length', ['length' => strlen($key_v2), 'masked' => strlen($key_v2) > 8 ? substr($key_v2, 0, 4) . '****' . substr($key_v2, -4) : '****']);
        if ($appid==='' || $mch_id==='' || $mch_customs_no==='' || $customs==='' || $key_v2==='') {
            $logger('ERROR','missing required base fields', compact('appid','mch_id','mch_customs_no','customs'));
            return ApiService::ApiDataReturn(DataReturn('缺少必要参数（appid/mch_id/mch_customs_no/customs/cert_key）', -1));
        }
        if ($transaction_id==='' && $out_trade_no==='') {
            return ApiService::ApiDataReturn(DataReturn('缺少交易号（transaction_id 或 out_trade_no 二选一）', -1));
        }
        // 移除所有费用相关的验证

        $url = 'https://api.mch.weixin.qq.com/cgi-bin/mch/customs/customdeclareorder';
        $params = [
            'appid'           => $appid,
            'mch_id'          => $mch_id,
            'mch_customs_no'  => $mch_customs_no,
            'customs'         => $customs,
            // 移除所有拆单相关字段：fee_type, order_fee, transport_fee, product_fee
            // 移除nonce_str：海关申报接口不需要此字段
        ];

        // 关键修复：确保参数格式完全符合微信要求
        $logger('INFO', '构建基础参数完成', [
            'appid_len' => strlen($appid),
            'mch_id_len' => strlen($mch_id)
        ]);
        if ($transaction_id!=='') { $params['transaction_id'] = $transaction_id; }
        if ($out_trade_no!=='')   { $params['out_trade_no']   = $out_trade_no; }

        // 透传可选字段（若传入则加入签名）
        $optionalKeys = [
            'cert_type','cert_id','name',
            'commerce_type','goods_info','payer_id_type','payer_id','pay_time','order_time',
        ];
        foreach ($optionalKeys as $k) {
            if (isset($req[$k]) && $req[$k] !== '') {
                $value = trim((string)$req[$k]);
                // 特殊处理：移除所有不可见字符和多余空格
                $value = preg_replace('/\s+/', ' ', $value);  // 多个空格压缩为一个
                $value = preg_replace('/[^\x20-\x7E\x{4e00}-\x{9fa5}]/u', '', $value);  // 只保留可见ASCII和中文
                if ($value !== '') {
                    $params[$k] = $value;
                    $logger('INFO', "可选参数[$k]已添加", ['value' => $value, 'original' => $req[$k]]);
                }
            }
        }

        // 签名前验证和格式化参数
        $logger('INFO','签名前参数验证', [
            'params_count' => count($params),
            'required_fields' => ['appid', 'mch_id', 'mch_customs_no', 'customs'],
            'actual_keys' => array_keys($params)
        ]);

        // 确保数值类型参数为整数字符串（微信要求）
        foreach (['order_fee', 'transport_fee', 'product_fee'] as $feeField) {
            if (isset($params[$feeField])) {
                $params[$feeField] = (string)(int)$params[$feeField];
            }
        }

        // 签名
        $params['sign_type'] = $sign_type;
        $signString = null;
        $params['sign']      = $this->wxSign($params, $key_v2, $sign_type, $signString);

        $logger('INFO','declare sign string',[ 'sign_type'=>$sign_type, 'string'=>$signString, 'sign'=>$params['sign'] ]);
        $logger('INFO','最终参数对比', [
            'final_params' => $params,
            'sign_excluded' => array_diff_key($params, ['sign' => ''])
        ]);

        $xml = $this->wxArrayToXml($params);
        $logger('INFO','1.declare request xml built',['xml'=>$xml]);

        $respXml = $this->wxPostXmlCurl($url, $xml, false, null, null, 30);
        $respArr = $this->wxXmlToArray($respXml);
        $logger('INFO','1.declare response received',['raw'=>$respXml,'parsed'=>$respArr]);

        // 按照微信支付文档要求的完整状态判断逻辑
        $return_code = $respArr['return_code'] ?? '';
        $result_code = $respArr['result_code'] ?? '';
        $state = $respArr['state'] ?? '';
        $err_code = $respArr['err_code'] ?? '';
        $err_code_des = $respArr['err_code_des'] ?? '';

        $logger('INFO', '微信API响应状态分析', [
            'return_code' => $return_code,
            'result_code' => $result_code,
            'state' => $state,
            'err_code' => $err_code,
            'err_code_des' => $err_code_des
        ]);

        // 第一层检查：通信状态
        if ($return_code !== 'SUCCESS') {
            $logger('ERROR', '微信API通信失败', [
                'return_code' => $return_code,
                'return_msg' => $respArr['return_msg'] ?? ''
            ]);
            return ApiService::ApiDataReturn(DataReturn('微信支付通信失败: ' . ($respArr['return_msg'] ?? '未知错误'), -1));
        }

        // 第二层检查：业务状态
        if ($result_code !== 'SUCCESS') {
            $error_msg = '微信支付业务处理失败';
            if ($err_code) {
                $error_msg .= " (错误码: {$err_code})";
            }
            if ($err_code_des) {
                $error_msg .= " {$err_code_des}";
            }

            $logger('ERROR', '微信API业务失败', [
                'result_code' => $result_code,
                'err_code' => $err_code,
                'err_code_des' => $err_code_des
            ]);

            return ApiService::ApiDataReturn(DataReturn($error_msg, -1));
        }

        // 第三层检查：海关申报状态
        if ($state !== 'SUCCESS') {
            $state_messages = [
                'UNDECLARED' => '尚未申报',
                'SUBMITTED' => '申报已提交',
                'PROCESSING' => '申报处理中',
                'FAIL' => '申报失败',
                'EXCEPT' => '海关接口异常'
            ];

            $state_msg = $state_messages[$state] ?? "未知状态: {$state}";

            // 对于非成功状态，记录详细信息但根据状态决定是否返回错误
            $logger('INFO', '海关申报状态', [
                'state' => $state,
                'state_message' => $state_msg,
                'customs_result' => $respArr
            ]);

            // 处理中的状态不算失败
            if (in_array($state, ['PROCESSING', 'SUBMITTED'])) {
                $logger('INFO', '海关申报处理中，后续可查询状态', ['state' => $state]);
                return ApiService::ApiDataReturn(DataReturn("海关申报{$state_msg}，请稍后查询状态", 0, $respArr));
            }

            return ApiService::ApiDataReturn(DataReturn("海关申报{$state_msg}", -1, $respArr));
        }

        // 完全成功的情况
        $logger('INFO', '海关申报成功', $respArr);
        return ApiService::ApiDataReturn(DataReturn('海关申报成功', 0, $respArr));
    }

    /**
     * 查询接口：/cgi-bin/mch/customs/customdeclarequery
     */
    public function WechatCustomsQuery()
    {
        $logger = function($level, $message, $context = []) {
            try {
                $log_file = root_path('runtime/log') . 'wechat_customs_' . date('Y-m-d') . '.log';
                $line = '['.date('Y-m-d H:i:s')."][".$level.'] '.$message.' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
                @file_put_contents($log_file, $line, FILE_APPEND);
            } catch (\Throwable $e) {}
        };
        $req = $this->data_request;
        $request_id = 'WXCG-QRY-' . date('YmdHis') . '-' . mt_rand(1000,9999);
        $logger('INFO', 'WeChat customs query start', ['request_id'=>$request_id,'params'=>$req]);

        $appid          = trim($req['appid'] ?? '');
        $mch_id         = trim($req['mch_id'] ?? '');
        $customs        = trim($req['customs'] ?? '');
        $key_v2         = trim($req[''] ?? '');
        $sign_type      = strtoupper(trim($req['sign_type'] ?? 'MD5'));
        $transaction_id = trim($req['transaction_id'] ?? '');
        $out_trade_no   = trim($req['out_trade_no'] ?? '');

        if ($appid==='' || $mch_id==='' || $customs==='' || $key_v2==='') {
            return ApiService::ApiDataReturn(DataReturn('缺少必要参数（appid/mch_id/customs/cert_key）', -1));
        }
        if ($transaction_id==='' && $out_trade_no==='') {
            return ApiService::ApiDataReturn(DataReturn('缺少交易号（transaction_id 或 out_trade_no 二选一）', -1));
        }

        $url = 'https://api.mch.weixin.qq.com/cgi-bin/mch/customs/customdeclarequery';
        $params = [
            'appid'     => $appid,
            'mch_id'    => $mch_id,
            'customs'   => $customs,
            'nonce_str' => bin2hex(random_bytes(8)),
        ];
        if ($transaction_id!=='') { $params['transaction_id'] = $transaction_id; }
        if ($out_trade_no!=='')   { $params['out_trade_no']   = $out_trade_no; }

        $params['sign_type'] = $sign_type;
        $signString = null;
        $params['sign']      = $this->wxSign($params, $key_v2, $sign_type, $signString);

        $xml = $this->wxArrayToXml($params);
        $logger('INFO','2.query request xml built',['xml'=>$xml]);

        $respXml = $this->wxPostXmlCurl($url, $xml, false, null, null, 30);
        $respArr = $this->wxXmlToArray($respXml);
        $logger('INFO','2.query response received',['raw'=>$respXml,'parsed'=>$respArr]);

        $ok = isset($respArr['return_code']) && $respArr['return_code']==='SUCCESS'
           && isset($respArr['result_code']) && $respArr['result_code']==='SUCCESS';

        return ApiService::ApiDataReturn(DataReturn($ok?'查询成功':'查询失败', $ok?0:-1, $respArr));
    }

    /**
     * =============================
     * 以下为微信 v2 XML 工具方法
     * =============================
     */
    private function wxSign(array $data, string $key, string $sign_type = 'MD5', ?string &$stringForSign = null): string
    {
        // 写入签名日志的函数
        $writeSignLog = function($level, $message, $data = []) {
            try {
                $log_file = root_path('runtime/log') . 'wechat_customs_' . date('Y-m-d') . '.log';
                $timestamp = date('Y-m-d H:i:s');
                $json_data = '';
                if (!empty($data)) {
                    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $log_entry = sprintf("[%s] [%s] [WxSign] %s %s\n", $timestamp, strtoupper($level), $message, $json_data);
                @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                // 忽略日志写入错误
            }
        };

        ksort($data);
        $pairs = [];
        foreach ($data as $k => $v) {
            // 关键修复：排除不参与签名的字段
            // 1. sign, sign_type - 标准排除字段
            // 2. nonce_str - 海关申报接口不参与签名
            $excludeFields = ['sign', 'sign_type', 'nonce_str'];
            if ($v === '' || $v === null || in_array($k, $excludeFields)) continue;
            $pairs[] = $k.'='.$v;
        }

        // 拼接待签名字符串
        $paramString = implode('&', $pairs);
        $stringSignTemp = $paramString . '&key=' . $key;
        $stringForSign = $stringSignTemp;

        // 记录签名过程详细信息
        $maskedKey = strlen($key) > 8 ? substr($key, 0, 4) . '****' . substr($key, -4) : '****';
        $writeSignLog('INFO', '签名参数详情', [
            'sign_type' => $sign_type,
            'param_pairs' => $pairs,
            'param_string' => $paramString,
            'api_v2_key_masked' => $maskedKey,
            'string_for_sign' => $stringSignTemp
        ]);

        // 执行签名
        if ($sign_type === 'HMAC-SHA256') {
            $finalSign = strtoupper(hash_hmac('sha256', $stringSignTemp, $key));
            $writeSignLog('INFO', '最终签名结果(HMAC-SHA256)', ['final_sign' => $finalSign]);
            return $finalSign;
        }

        $finalSign = strtoupper(md5($stringSignTemp));
        $writeSignLog('INFO', '最终签名结果(MD5)', ['final_sign' => $finalSign]);
        return $finalSign;
    }

    private function wxArrayToXml(array $arr): string
    {
        // 微信支付要求UTF-8编码，但不需要XML声明头
        // 关键修复：XML参数顺序必须与签名字符串的ASCII字典序完全一致

        $xml = '<xml>';

        // 按照微信支付签名算法要求：所有参数按ASCII字典序排序
        // 这样XML参数顺序与签名字符串顺序完全一致
        ksort($arr);

        foreach ($arr as $k => $v) {
            // 排除不需要在XML中输出的字段
            if ($k === 'sign_type' || $k === 'nonce_str') continue;

            if (is_numeric($v)) {
                $xml .= "<{$k}>{$v}</{$k}>";
            } else {
                // 按照微信文档要求：参数值用XML转义即可，不使用CDATA标签
                $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
                $escaped_value = htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $xml .= "<{$k}>{$escaped_value}</{$k}>";
            }
        }

        $xml .= '</xml>';
        return $xml;
    }

    private function wxXmlToArray(string $xml): array
    {
        if (function_exists('libxml_disable_entity_loader') && version_compare(PHP_VERSION, '8.0.0', '<')) {
            // 在 PHP < 8.0 中显式关闭实体加载以保证安全
            libxml_disable_entity_loader(true);
        }
        $data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true) ?: [];
    }

    private function wxPostXmlCurl(string $url, string $xml, bool $useCert=false, ?string $sslCertPath=null, ?string $sslKeyPath=null, int $timeout=30): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

        // 关键：设置正确的Content-Type头，微信支付要求text/xml格式
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($xml),
            'User-Agent: Mozilla/5.0 (compatible; WeChatPay/API)',
        ]);
        if ($useCert && $sslCertPath && $sslKeyPath) {
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $sslCertPath);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $sslKeyPath);
        }

        // 记录发送前的请求信息
        try {
            $log_file = root_path('runtime/log') . 'wechat_customs_' . date('Y-m-d') . '.log';
            $timestamp = date('Y-m-d H:i:s');
            $log_entry = sprintf("[%s] [INFO] [WxPostXmlCurl] 发送请求: URL=%s, XML长度=%d, XML内容=%s\n",
                $timestamp, $url, strlen($xml), $xml);
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // 忽略日志写入错误
        }

        $data = curl_exec($ch);
        // 参考现有logger方式记录curl响应数据
        try {
            $log_file = root_path('runtime/log') . 'wechat_customs_' . date('Y-m-d') . '.log';
            $timestamp = date('Y-m-d H:i:s');
            $response_data = ($data === false ? 'CURL_EXEC_FALSE' : $data);
            $log_entry = sprintf("[%s] [INFO] [WxPostXmlCurl] curl response: %s\n", $timestamp, $response_data);
            @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // 忽略日志写入错误
        }
        if ($data === false) {
            $err = curl_error($ch);
            curl_close($ch);
            // 按照微信文档要求使用XML转义而不是CDATA
            $escaped_err = htmlspecialchars($err, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            return '<xml><return_code>FAIL</return_code><return_msg>'.$escaped_err.'</return_msg></xml>';
        }
        curl_close($ch);
        return $data;
    }

}
?>
