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

            $writeLog('INFO', '设置基础配置参数');
            $cfg = [
                // 基础配置
                'enabled'       => true,
                'environment'   => 'sandbox', // sandbox 或 production
                'app_code'      => '102905', // 菜鸟应用 appCode（当前未用于 LINK 提交）
                'resource_code' => '95f7ac77fd52d162a68eaea5cef3dc55', // logistic_provider_id
                'app_secret'    => '466aN6F8t0Q6jxiK8GUrFM355mju19j8',
                'app_name'      => '杭州圣劳诗',
                'warehouse_name'=> '菜鸟金华义乌综保保税中心仓F1255',
            ];
            $writeLog('INFO', '基础配置完成');

            // 货主/BU 信息
            $cfg['owner_user_id'] = '2220576876930';
            $cfg['business_unit_id'] = 'B06738021'; // 多BU场景下使用
            $writeLog('INFO', '货主信息配置完成');

            // 订单配置
            $cfg['order_type'] = 'BONDED_WHS';
            $cfg['order_source'] = '201';
            $cfg['order_sub_source'] = '';
            $cfg['sale_mode'] = '1'; // 1-线上
            $writeLog('INFO', '订单配置完成');

            // 仓库/店铺
            $cfg['store_code'] = 'YWZ806';
            $cfg['shop_name'] = '杭州圣劳诗';
            $cfg['shop_id'] = '杭州圣劳诗';
            $writeLog('INFO', '仓库店铺配置完成');

            // 其他
            $cfg['log_to_db'] = true;
            $cfg['auto_check_warehouse'] = true;
            $cfg['warehouse_keywords'] = ['菜鸟', 'cainiao', '菜鸟仓'];
            $cfg['currency'] = 'CNY';
            $cfg['pay_channel'] = 'ALIPAY';
            $writeLog('INFO', '其他配置完成');

            // 税费默认值（如实际需要按规则计算，请替换）
            $cfg['default_customs_tax'] = 0;
            $cfg['default_consumption_tax'] = 0;
            $cfg['default_vat'] = 0;
            $cfg['default_total_tax'] = 0;
            $cfg['default_insurance'] = 0;
            $writeLog('INFO', '税费配置完成');

            // 发件人信息（如无专用字段，则沿用店铺名/默认值）
            $cfg['sender_name'] = '杭州圣劳诗';
            $cfg['sender_mobile'] = '0571-12345678';
            $cfg['sender_province'] = '浙江省';
            $cfg['sender_city'] = '杭州市';
            $cfg['sender_area'] = '西湖区';
            $cfg['sender_address'] = '云栖小镇';
            $writeLog('INFO', '发件人信息配置完成');

            // to_code 部分接口可不填
            $cfg['to_code'] = '';
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

        // 4) 组装商品项（转换为文档要求的 orderItemList 结构）
        $writeLog('INFO', '步骤4: 开始组装商品项', ['details_count' => count($details)]);
        $items = [];
        $sumCount = 0;
        $sumAmount = 0.0;
        if (!empty($details)) {
            $writeLog('INFO', '开始处理订单明细');
            foreach ($details as $index => $d) {
                $title      = $d['title'] ?? '商品';
                $count      = (int)($d['buy_number'] ?? 1);
                $price      = (float)($d['price'] ?? 0);
                $totalPrice = (float)($d['total_price'] ?? ($price * $count));
                $itemId     = (string)($d['spec_coding'] ?? $d['goods_id'] ?? '');

                $writeLog('INFO', "处理商品 #{$index}", [
                    'title' => $title,
                    'itemId' => $itemId,
                    'count' => $count,
                    'price' => $price,
                    'totalPrice' => $totalPrice
                ]);

                // 声明信息（金额单位存在差异，通常为分，以下以分为单位上送，按需调整）
                $item = [
                    'itemId'       => $itemId,
                    'itemQuantity' => $count,
                    'declareInfo'  => [
                        'itemTotalPrice'       => (int)round($totalPrice * 100),
                        'itemTotalActualPrice' => (int)round($totalPrice * 100),
                        'customsTax'           => (int)$cfg['default_customs_tax'],
                        'consumptionTax'       => (int)$cfg['default_consumption_tax'],
                        'vat'                  => (int)$cfg['default_vat'],
                        'totalTax'             => (int)$cfg['default_total_tax'],
                    ],
                ];
                $items[] = $item;
                $writeLog('INFO', "商品项组装完成 #{$index}", ['item' => $item]);

                $sumCount  += $count;
                $sumAmount += $totalPrice;
            }
            $writeLog('INFO', '所有商品项处理完成', ['total_count' => $sumCount, 'total_amount' => $sumAmount]);
        } else {
            $writeLog('WARNING', '无订单明细，使用默认商品项');
            $items[] = [
                'itemId'       => 'DEFAULT',
                'itemQuantity' => 1,
                'declareInfo'  => [
                    'itemTotalPrice'       => 0,
                    'itemTotalActualPrice' => 0,
                    'customsTax'           => (int)$cfg['default_customs_tax'],
                    'consumptionTax'       => (int)$cfg['default_consumption_tax'],
                    'vat'                  => (int)$cfg['default_vat'],
                    'totalTax'             => (int)$cfg['default_total_tax'],
                ],
            ];
            $sumCount  = 1;
            $sumAmount = 0.00;
        }

        // 5) 汇总字段与收件人/发件人
        $itemCount   = (int)($orderRow['buy_number_count'] ?? $sumCount);
        $totalAmount = (float)($orderRow['total_price'] ?? $sumAmount);
        // 使用订单地址表中的信息作为收件人信息来源
        $receiverTel = $orderAddress['tel'] ?? ($orderRow['tel'] ?? '');
        $receiverName = $orderAddress['extraction_contact_name']
            ?? $orderAddress['name']
            ?? ($orderRow['receive_name'] ?? '收件人');
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
        $request_data = [
            'ownerUserId'       => (string)$cfg['owner_user_id'],
            'externalOrderCode' => (string)($orderRow['order_no'] ?? $order_id),
            'orderType'         => (string)$cfg['order_type'],
            'storeCode'         => (string)$cfg['store_code'],
            'externalShopName'  => (string)$cfg['shop_name'],
            'orderSource'       => (string)$cfg['order_source'],
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
                'dutiablePrice'  => (int)round(($orderRow['total_price'] ?? 0) * 100),
                'customsTax'     => (int)$cfg['default_customs_tax'],
                'consumptionTax' => (int)$cfg['default_consumption_tax'],
                'vat'            => (int)$cfg['default_vat'],
                'totalTax'       => (int)$cfg['default_total_tax'],
                'insurance'      => (int)$cfg['default_insurance'],
                'coupon'         => (int)round(($orderRow['preferential_price'] ?? 0) * 100),
                'actualPayment'  => (int)round(($orderRow['pay_price'] ?? 0) * 100),
                'postFee'        => (int)round(($orderRow['express_price'] ?? 0) * 100),
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

        // 7) 组装请求参数
        $writeLog('INFO', '步骤7: 开始组装API请求参数');
        $content = json_encode($request_data, JSON_UNESCAPED_UNICODE);
        $writeLog('INFO', 'JSON序列化完成', ['content_length' => strlen($content)]);

        $request_params = [
            'msg_type'             => 'GLOBAL_SALE_ORDER_NOTIFY',
            'logistic_provider_id' => $cpCode,
            'logistics_interface'  => $content,
        ];
        if (!empty($cfg['to_code'])) {
            $request_params['to_code'] = $cfg['to_code'];
        }
        $request_params['data_digest'] = base64_encode(md5($content.$appSecret, true));
        $writeLog('INFO', '请求参数组装完成', [
            'msg_type' => $request_params['msg_type'],
            'logistic_provider_id' => $cpCode,
            'to_code' => $request_params['to_code'] ?? '',
            'data_digest_length' => strlen($request_params['data_digest'])
        ]);

        $maskedSecret = substr($appSecret, 0, 4).'****'.substr($appSecret, -4);
        Log::info('[CainiaoShipment] request built', [
            'url_env' => $cfg['environment'],
            'msg_type' => $request_params['msg_type'],
            'logistic_provider_id' => $cpCode,
            'to_code' => $request_params['to_code'] ?? '',
            // 注意：不要直接打印 data_digest 和完整 content 到生产日志；这里仅调试期保留
            'data_digest_len' => strlen($request_params['data_digest']),
            'app_secret_masked' => $maskedSecret,
        ]);
        Log::debug('[CainiaoShipment] logistics_interface', ['body' => json_encode($request_data, JSON_UNESCAPED_UNICODE)]);

        // 8) 请求菜鸟（按环境切换网关）
        $writeLog('INFO', '步骤8: 准备发送API请求');
        $url = ($cfg['environment'] === 'sandbox')
            ? 'http://linkdaily.tbsandbox.com/gateway/link.do'
            : 'https://link.cainiao.com/gateway/link.do';
        $writeLog('INFO', '确定请求URL', ['url' => $url, 'environment' => $cfg['environment']]);

        Log::info('[CainiaoShipment] post url', ['url' => $url]);

        $writeLog('INFO', '======= 菜鸟接口调用开始 =======');
        $writeLog('INFO', '接口调用前 - 输入参数', [
            'url' => $url,
            'request_params' => $request_params
        ]);

        $writeLog('INFO', '正在发送HTTP请求...');
        $req_start_time = microtime(true);
        $res = CurlPost($url, $request_params);
        $req_duration = round((microtime(true) - $req_start_time) * 1000, 2);

        $writeLog('INFO', '接口调用后 - 返回结果', [
            'duration_ms' => $req_duration,
            'response' => $res
        ]);

        // 写入数据库日志（记录接口调用历史）
        try {
            $insert_data = [
                'express_type'   => 'cainiao',
                'express_name'   => 'GLOBAL_SALE_ORDER_NOTIFY',
                'express_number' => $orderRow['order_no'] ?? (string)$order_id,
                'express_code'   => 'CAINIAO',
                'request_params' => json_encode($request_params, JSON_UNESCAPED_UNICODE),
                'response_data'  => json_encode($res, JSON_UNESCAPED_UNICODE),
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
        $writeLog('INFO', 'HTTP响应检查', [
            'res_is_array' => is_array($res),
            'res_code' => is_array($res) ? ($res['code'] ?? 'no_code') : 'not_array',
            'has_data' => is_array($res) ? !empty($res['data']) : false
        ]);

        if (is_array($res) && isset($res['code']) && $res['code'] == 0 && !empty($res['data'])) {
            $writeLog('INFO', 'HTTP请求成功，开始解析响应数据');
            $raw = $res['data'];
            $writeLog('INFO', '原始响应数据', [
                'raw_type' => gettype($raw),
                'raw_length' => is_string($raw) ? strlen($raw) : (is_array($raw) ? count($raw) : 'unknown'),
                'raw_sample' => is_string($raw) ? substr($raw, 0, 200) : 'not_string'
            ]);

            $response = is_array($raw) ? $raw : json_decode($raw, true);
            $writeLog('INFO', 'JSON解析结果', [
                'response_is_array' => is_array($response),
                'json_error' => json_last_error(),
                'json_error_msg' => json_last_error_msg()
            ]);

            if (empty($response) && is_string($raw) && strlen($raw) > 0 && $raw[0] === '<') {
                $writeLog('INFO', '检测到XML格式响应，尝试XML解析');
                // XML 兜底
                if (function_exists('XmlArray')) {
                    $response = XmlArray($raw);
                    $writeLog('INFO', 'XML解析完成', ['xml_parsed' => !empty($response)]);
                } else {
                    $writeLog('WARNING', 'XmlArray函数不存在，无法解析XML');
                }
            }

            if (!empty($response)) {
                $writeLog('INFO', '响应数据解析成功', ['response_keys' => array_keys($response)]);
                $success = isset($response['success']) ? $response['success'] : (isset($response['Success']) ? $response['Success'] : null);
                $writeLog('INFO', '检查响应成功标志', ['success_value' => $success, 'success_type' => gettype($success)]);

                if ($success === true || $success === 'true' || $success === 1 || $success === '1') {
                    $writeLog('INFO', '响应表示成功，提取结果数据');
                    // 兼容可能的结果字段
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
                        'result_data' => $result_data
                    ]);
                    return ApiService::ApiDataReturn(DataReturn('发货成功', 0, $result_data));
                }
                $error_msg = $response['errorMsg'] ?? $response['ErrorMsg'] ?? '发货失败';
                $writeLog('ERROR', '======= 菜鸟接口调用失败 =======', [
                    'order_id' => $order_id,
                    'order_no' => $orderRow['order_no'] ?? '',
                    'error_msg' => $error_msg,
                    'full_response' => $response
                ]);
                return ApiService::ApiDataReturn(DataReturn($error_msg, -1));
            } else {
                $writeLog('ERROR', '响应数据解析失败，数据为空');
            }
        } else {
            $writeLog('ERROR', 'HTTP请求失败或响应格式错误', ['full_response' => $res]);
        }

        $error_msg = (is_array($res) && !empty($res['msg'])) ? $res['msg'] : '菜鸟发货接口请求失败';
        $writeLog('ERROR', '======= 菜鸟接口调用异常 =======', [
            'total_duration_ms' => round((microtime(true) - $start_time) * 1000, 2),
            'order_id' => $order_id,
            'order_no' => $orderRow['order_no'] ?? '',
            'error_msg' => $error_msg,
            'response' => $res
        ]);
        return ApiService::ApiDataReturn(DataReturn($error_msg, -1));
    }


}
?>
