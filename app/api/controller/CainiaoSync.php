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
namespace app\api\controller;

use app\service\ApiService;
use app\service\CainiaoConfigService;
use app\service\OrderService;
use think\facade\Db;
use think\facade\Log;

/**
 * 菜鸟回调同步控制器
 */
class CainiaoSync extends Common
{
    /**
     * 菜鸟订单状态同步回调
     */
    public function CainiaoOrderStatusSync()
    {
        $log_file = root_path('runtime/log') . 'cainiao_status_sync_' . date('Y-m-d') . '.log';
        $request_id = md5('status'.uniqid('', true));
        $writeLog = function($level, $message, $data = []) use ($log_file, $request_id) {
            try {
                $timestamp = date('Y-m-d H:i:s');
                $json = empty($data) ? '' : json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($json === false) {
                    $json = 'JSON_ERROR: '.json_last_error_msg();
                }
                $log_dir = dirname($log_file);
                if (!is_dir($log_dir)) {
                    mkdir($log_dir, 0755, true);
                }
                $entry = sprintf('[%s] [%s] [%s] %s %s%s',
                    $timestamp,
                    strtoupper($level),
                    $request_id,
                    $message,
                    $json,
                    PHP_EOL
                );
                file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $e) {
                Log::error('[CainiaoSync] log write failed', [
                    'error' => $e->getMessage(),
                    'message' => $message,
                ]);
            }
        };

        $rawBody = request()->getContent();
        $params = $this->data_request;
        $clientIp = request()->ip();
        $writeLog('INFO', '请求到达', [
            'client_ip'    => $clientIp,
            'raw_body'     => $rawBody,
            'query_params' => $params,
            'headers'      => request()->header(),
        ]);

        try {
            $cfg = CainiaoConfigService::BaseConfig();
            $ipWhitelist = $cfg['ip_whitelist'] ?? [];
            if (!empty($ipWhitelist) && !in_array($clientIp, $ipWhitelist, true)) {
                $writeLog('ERROR', 'IP 不在白名单', ['client_ip' => $clientIp]);
                return ApiService::ApiDataReturn(DataReturn('IP 受限，拒绝访问', -1));
            }

            $payload = $this->extractStatusSyncPayload($params, $writeLog);
            if (empty($payload)) {
                $writeLog('ERROR', '未获取到有效的状态数据', ['raw_body' => $rawBody]);
                return ApiService::ApiDataReturn(DataReturn('未获取到有效的状态数据', -1));
            }

            $statusEntries = [];
            if (!empty($payload['orderStatusList']['orderStatus'])) {
                $statusEntries = $payload['orderStatusList']['orderStatus'];
            } elseif (!empty($payload['orderStatus'])) {
                $statusEntries = is_array($payload['orderStatus']) ? $payload['orderStatus'] : [$payload['orderStatus']];
            }

            if (!is_array($statusEntries) || empty($statusEntries)) {
                $writeLog('ERROR', '状态列表为空', ['payload' => $payload]);
                return ApiService::ApiDataReturn(DataReturn('状态列表为空', -1));
            }

            $results = [];
            foreach ($statusEntries as $index => $entry) {
                if (!is_array($entry)) {
                    $writeLog('WARNING', '状态项非数组，忽略', ['index' => $index, 'value' => $entry]);
                    continue;
                }
                $resultItem = $this->syncCainiaoStatusEntry($entry, $cfg, $writeLog);
                $results[] = $resultItem;
            }

            $writeLog('INFO', '状态同步完成', ['summary' => $results]);
            return ApiService::ApiDataReturn(DataReturn('菜鸟状态同步完成', 0, $results));
        } catch (\Throwable $e) {
            $writeLog('ERROR', '状态同步出现异常', [
                'error_message' => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);
            Log::error('[CainiaoSync] sync exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiService::ApiDataReturn(DataReturn('菜鸟状态同步异常：'.$e->getMessage(), -1));
        }
    }

    /**
     * 解析菜鸟状态同步请求数据
     */
    private function extractStatusSyncPayload(array $params, callable $logger): array
    {
        $payload = [];

        $content = $params['logistics_interface'] ?? $params['logisticsInterface'] ?? null;
        if ($content !== null) {
            $decoded = json_decode((string)$content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                $logger('ERROR', 'logistics_interface JSON 解析失败', ['error' => json_last_error_msg(), 'raw' => $content]);
            }
        }

        if (empty($payload) && isset($params['payload'])) {
            $decoded = is_array($params['payload']) ? $params['payload'] : json_decode((string)$params['payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (empty($payload) && isset($params['orderStatusList'])) {
            $payload = $params;
        }

        if (!empty($payload)) {
            $logger('INFO', '解析菜鸟状态 payload 成功', ['keys' => array_keys($payload)]);
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * 同步单条状态项
     */
    private function syncCainiaoStatusEntry(array $entry, array $cfg, callable $logger): array
    {
        $orderNo = (string)($entry['externalOrderCode'] ?? $entry['orderCode'] ?? '');
        $statusKey = strtoupper((string)($entry['orderStatus'] ?? ''));
        $logisticsNo = (string)($entry['logisticsNo'] ?? $entry['trackingNo'] ?? '');
        $logisticsCode = strtoupper((string)($entry['logisticsCompanyCode'] ?? $entry['logisticsCode'] ?? ''));
        $logisticsOrderCode = (string)($entry['logisticsOrderCode'] ?? $entry['lgOrderCode'] ?? '');
        $remark = (string)($entry['remark'] ?? $entry['statusRemark'] ?? '菜鸟状态同步');

        $result = [
            'order_no'      => $orderNo,
            'status_key'    => $statusKey,
            'logistics_no'  => $logisticsNo,
            'logistics_code'=> $logisticsCode,
            'updated'       => false,
            'message'       => '',
        ];

        $logger('INFO', '开始处理状态项', [
            'order_no'      => $orderNo,
            'status_key'    => $statusKey,
            'logistics_no'  => $logisticsNo,
            'logistics_code'=> $logisticsCode,
        ]);

        if ($orderNo === '' || $statusKey === '') {
            $logger('ERROR', '状态项缺少必要字段', ['entry' => $entry]);
            $result['message'] = '缺少订单号或状态';
            return $result;
        }

        $statusMap = $cfg['status_map'] ?? [];
        $targetStatus = $statusMap[$statusKey] ?? null;
        if ($targetStatus === null) {
            $logger('WARNING', '未配置状态映射，跳过', ['order_no' => $orderNo, 'status_key' => $statusKey]);
            $result['message'] = '未配置状态映射';
            return $result;
        }

        try {
            $order = Db::name('Order')->where('order_no', $orderNo)->field('id,user_id,status,delivery_time,collect_time,cancel_time,close_time')->find();
        } catch (\Throwable $e) {
            $logger('ERROR', '查询订单异常', ['order_no' => $orderNo, 'error' => $e->getMessage()]);
            $result['message'] = '查询订单异常: '.$e->getMessage();
            return $result;
        }

        if (empty($order)) {
            $logger('ERROR', '订单不存在', ['order_no' => $orderNo]);
            $result['message'] = '订单不存在';
            return $result;
        }

        $originalStatus = intval($order['status']);
        $now = time();
        $updateData = ['upd_time' => $now];

        if ($originalStatus !== $targetStatus) {
            $updateData['status'] = $targetStatus;
            if ($targetStatus === 3 && empty($order['delivery_time'])) {
                $updateData['delivery_time'] = $now;
            }
            if ($targetStatus === 4 && empty($order['collect_time'])) {
                $updateData['collect_time'] = $now;
            }
            if ($targetStatus === 5 && empty($order['cancel_time'])) {
                $updateData['cancel_time'] = $now;
            }
            if ($targetStatus === 6 && empty($order['close_time'])) {
                $updateData['close_time'] = $now;
            }

            try {
                Db::name('Order')->where('id', $order['id'])->update($updateData);
                OrderService::OrderHistoryAdd($order['id'], $targetStatus, $originalStatus, $remark, 0, 'cainiao-sync');
                $result['updated'] = true;
                $result['message'] = '状态已更新';
                $logger('INFO', '订单状态更新成功', [
                    'order_id' => $order['id'],
                    'order_no' => $orderNo,
                    'from'     => $originalStatus,
                    'to'       => $targetStatus,
                ]);
            } catch (\Throwable $e) {
                $logger('ERROR', '更新订单状态失败', ['order_id' => $order['id'], 'error' => $e->getMessage()]);
                $result['message'] = '更新订单状态失败: '.$e->getMessage();
                return $result;
            }
        } else {
            $result['message'] = '状态未变化';
            $logger('INFO', '订单状态无变化', ['order_id' => $order['id'], 'order_no' => $orderNo, 'status' => $targetStatus]);
        }

        $expressResult = $this->syncOrderExpressInfo($order, $logisticsCode, $logisticsNo, $logisticsOrderCode, $cfg, $logger);
        $result = array_merge($result, $expressResult);

        return $result;
    }

    /**
     * 同步订单快递信息
     */
    private function syncOrderExpressInfo(array $order, string $logisticsCode, string $logisticsNo, string $logisticsOrderCode, array $cfg, callable $logger): array
    {
        $response = [
            'express_updated' => false,
        ];

        if ($logisticsCode === '' || $logisticsNo === '') {
            return $response;
        }

        $expressId = $this->resolveExpressIdByCode($logisticsCode, $cfg);
        if ($expressId <= 0) {
            $logger('WARNING', '未匹配到快递ID', ['order_id' => $order['id'], 'logistics_code' => $logisticsCode]);
            $response['message'] = '未匹配到快递ID';
            return $response;
        }

        $data = [
            'express_id'     => $expressId,
            'express_number' => $logisticsNo,
            'note'           => $logisticsOrderCode,
        ];

        try {
            $existing = Db::name('OrderExpress')->where(['order_id' => $order['id']])->order('id desc')->find();
            if (empty($existing)) {
                $data['order_id'] = $order['id'];
                $data['user_id'] = $order['user_id'];
                $data['add_time'] = time();
                Db::name('OrderExpress')->insertGetId($data);
                $logger('INFO', '快递信息新增', ['order_id' => $order['id'], 'express_id' => $expressId, 'express_number' => $logisticsNo]);
            } else {
                $data['upd_time'] = time();
                Db::name('OrderExpress')->where('id', $existing['id'])->update($data);
                $logger('INFO', '快递信息更新', [
                    'order_id'      => $order['id'],
                    'express_id'    => $expressId,
                    'express_number'=> $logisticsNo,
                    'express_row_id'=> $existing['id'],
                ]);
            }
            $response['express_updated'] = true;
            $response['express_id'] = $expressId;
        } catch (\Throwable $e) {
            $logger('ERROR', '同步快递信息失败', ['order_id' => $order['id'], 'error' => $e->getMessage()]);
            $response['message'] = '同步快递失败: '.$e->getMessage();
        }

        return $response;
    }

    /**
     * 通过物流编码解析快递ID
     */
    private function resolveExpressIdByCode(string $logisticsCode, array $cfg): int
    {
        $code = strtoupper(trim($logisticsCode));
        if ($code === '') {
            return 0;
        }

        $map = $cfg['logistics_company_map'] ?? [];
        if (isset($map[$code])) {
            $value = $map[$code];
            if (is_array($value) && isset($value['express_id'])) {
                return (int)$value['express_id'];
            }
            return (int)$value;
        }

        try {
            $config = Db::name('PluginsDataConfig')->where(['plugins' => 'express'])->value('value');
            if (!empty($config)) {
                $configArr = json_decode($config, true);
                if (!empty($configArr['express_codes']) && is_array($configArr['express_codes'])) {
                    foreach ($configArr['express_codes'] as $expressId => $expressCode) {
                        if (strtoupper(trim((string)$expressCode)) === $code) {
                            return (int)$expressId;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[CainiaoSync] resolve express id failed', ['code' => $code, 'error' => $e->getMessage()]);
        }

        return 0;
    }
}
?>
