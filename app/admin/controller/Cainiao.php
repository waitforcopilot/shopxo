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
                    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
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

        Log::info('[CainiaoShipment] enter', ['request_id' => $request_id, 'params' => json_encode($this->data_request, JSON_UNESCAPED_UNICODE)]);

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

            $cfg['owner_user_id']    = '2220576876930';
            $cfg['business_unit_id'] = 'B06738021';
            $cfg['order_type']       = 'BONDED_WHS';
            $cfg['order_source']     = '201';
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
                'payOrderId'      => (string)($orderRow['payment_id'] ?? ''),
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
        Log::debug('[CainiaoShipment] logistics_interface', ['body' => is_array($request_data) ? json_encode($request_data, JSON_UNESCAPED_UNICODE) : $request_data]);

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
                'request_params' => json_encode($request_params, JSON_UNESCAPED_UNICODE),
                'response_data'  => json_encode($apiResult['response_raw'], JSON_UNESCAPED_UNICODE),
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
                    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
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

        $orderSource = isset($cfg['order_source']) ? (string)$cfg['order_source'] : '201';
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

        $content = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
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


}
?>
