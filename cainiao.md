# 菜鸟仓储接口集成深度分析报告

## 概述

本文档深入分析菜鸟仓储接口与ShopXO系统的完整集成方案，包括现有ExpressQueryService.php的调用方式分析、两个菜鸟接口的参数差异对比、ShopXO数据结构映射关系，以及准确的参数来源确定。

## 一、ExpressQueryService.php中菜鸟接口调用分析

### 1.1 现有CainiaoQuery方法分析
```php
// app/plugins/express/service/ExpressQueryService.php (第231-303行)
public static function CainiaoQuery($config, $order, $express)
{
    // 业务参数构建
    $content = json_encode(['arg0'=>[
        'appCode'       => $config['cainiao_app_name'],        // ❌ 错误！应为app_code
        'mailNo'        => $express['express_number'],         // ✅ 快递单号
        'cpCode'        => $express['express_code'],          // ✅ 快递公司代码
        'receiverPhone' => $order['tel'],                     // ✅ 收件人电话
    ]], JSON_UNESCAPED_UNICODE);
    
    // 公共参数
    $request_params = [
        'msg_type'              => 'CNTECH_LV_LOGISTICS_DETAIL_GET',
        'logistic_provider_id'  => $config['cainiao_app_code'],  // ❌ 错误！应为resource_code
        'to_code'               => 'CNTECH_LV',
        'logistics_interface'   => $content,
    ];

    // 签名生成 ✅ 正确
    $request_params['data_digest'] = base64_encode(md5($content.$config['cainiao_app_secret'], true));
}
```

### 1.2 参数配置错误分析

| 参数位置 | 当前错误配置 | 正确配置 | 说明 |
|---------|-------------|---------|------|
| 业务参数.appCode | `cainiao_app_name` | `cainiao_app_code` (102905) | 应用代码，不是应用名称 |
| 公共参数.logistic_provider_id | `cainiao_app_code` | `cainiao_resource_code` (95f7ac77...) | 资源code，不是应用code |

### 1.3 调用流程分析
1. **输入参数**：`$config`(插件配置)、`$order`(订单数据)、`$express`(快递数据)
2. **业务参数**：构建arg0数组，包含查询所需的核心信息
3. **公共参数**：遵循菜鸟接口标准格式
4. **签名验证**：使用MD5+Base64签名算法
5. **响应处理**：解析JSON响应，提取物流轨迹信息

## 二、菜鸟接口参数对比分析

### 2.1 CNTECH_LV_LOGISTICS_DETAIL_GET (物流查询) vs GLOBAL_SALE_ORDER_NOTIFY (订单下发)

#### 2.1.1 公共参数对比 (完全相同)
| 参数名 | 类型 | 必需 | 描述 | 使用说明 |
|--------|------|------|------|----------|
| msg_type | String | √ | 消息类型 | 查询:'CNTECH_LV_LOGISTICS_DETAIL_GET', 下发:'GLOBAL_SALE_ORDER_NOTIFY' |
| logistic_provider_id | String | √ | 资源code | **统一使用resource_code (95f7ac77...)** |
| data_digest | String | √ | 请求签名 | **统一使用app_secret进行MD5签名** |
| to_code | String | × | 目的方编码 | 查询:'CNTECH_LV', 下发:'GLOBAL_SALE' |
| logistics_interface | String | √ | 业务参数JSON | **业务参数差异巨大** |

#### 2.1.2 业务参数对比

**物流查询业务参数 (logistics_interface内容)**：
```json
{
  "arg0": {
    "appCode": "102905",           // 应用code
    "mailNo": "快递单号",          // 来源: express.express_number
    "cpCode": "快递公司代码",      // 来源: express.express_code  
    "receiverPhone": "收件人电话"  // 来源: order.tel
  }
}
```

**订单下发业务参数 (logistics_interface内容)**：
```json
{
  "ownerUserId": "2220576876930",     // 货主用户ID (配置)
  "businessUnitId": "B06738021",     // BU信息 (配置)
  "externalOrderCode": "订单编号",    // 来源: order.order_no
  "orderType": "BONDED_WHS",         // 订单类型 (配置)
  "storeCode": "JIX230",             // 仓库代码 (配置)
  "externalShopName": "店铺名称",     // 配置
  "orderSource": "201",              // 订单来源 (配置)
  "orderCreateTime": "2023-01-01 12:00:00", // 来源: order.add_time
  "consigneeName": "收件人姓名",      // 来源: order_address.name
  "consigneePhone": "收件人电话",     // 来源: order_address.tel
  "consigneeAddress": {              // 收货地址
    "province": "省份",              // 来源: order_address.province_name
    "city": "城市",                  // 来源: order_address.city_name
    "area": "区县",                  // 来源: order_address.county_name
    "detail": "详细地址"             // 来源: order_address.address
  },
  "items": [{                        // 商品列表
    "itemId": "商品ID",              // 来源: order_detail.goods_id
    "itemName": "商品名称",          // 来源: order_detail.title
    "quantity": 1,                   // 来源: order_detail.buy_number
    "itemPrice": 100.00              // 来源: order_detail.price
  }]
}
```

## 三、ShopXO数据库表结构与菜鸟接口映射分析

### 3.1 核心数据表结构分析

#### 3.1.1 订单相关表
```sql
-- 订单主表 (sxo_order)
CREATE TABLE `sxo_order` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` char(60) NOT NULL DEFAULT '',           -- 订单号 → externalOrderCode
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,     -- 用户ID
  `warehouse_id` int(10) UNSIGNED NOT NULL DEFAULT 0, -- 仓库ID (用于判断菜鸟仓)
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00, -- 订单总价 → totalAmount
  `pay_price` decimal(10,2) NOT NULL DEFAULT 0.00,   -- 实付金额 → actualAmount
  `add_time` int(10) UNSIGNED NOT NULL DEFAULT 0,    -- 创建时间 → orderCreateTime
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,   -- 订单状态
  `pay_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 -- 支付状态
);

-- 订单收货地址表 (sxo_order_address)
CREATE TABLE `sxo_order_address` (
  `order_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `name` char(60) NOT NULL DEFAULT '',          -- 收件人 → consigneeName
  `tel` char(15) NOT NULL DEFAULT '',           -- 电话 → consigneePhone/Mobile
  `province_name` char(30) NOT NULL DEFAULT '', -- 省份 → consigneeAddress.province
  `city_name` char(30) NOT NULL DEFAULT '',     -- 城市 → consigneeAddress.city
  `county_name` char(30) NOT NULL DEFAULT '',   -- 区县 → consigneeAddress.area
  `address` char(200) NOT NULL DEFAULT ''       -- 详细地址 → consigneeAddress.detail
);

-- 订单商品明细表 (sxo_order_detail)
CREATE TABLE `sxo_order_detail` (
  `order_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `goods_id` int(10) UNSIGNED NOT NULL DEFAULT 0,    -- 商品ID → items[].itemId
  `title` char(160) NOT NULL DEFAULT '',             -- 商品名 → items[].itemName
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,       -- 价格 → items[].itemPrice
  `buy_number` int(10) UNSIGNED NOT NULL DEFAULT 0,  -- 数量 → items[].quantity
  `spec` text NULL,                                  -- 规格 → items[].itemCode
  `spec_coding` char(80) NOT NULL DEFAULT '',        -- 商品编码
  `spec_barcode` char(80) NOT NULL DEFAULT ''        -- 条形码
);
```

#### 3.1.2 商品相关表
```sql
-- 商品主表 (sxo_goods)
CREATE TABLE `sxo_goods` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` char(160) NOT NULL DEFAULT '',        -- 商品名称
  `model` char(30) NOT NULL DEFAULT '',         -- 型号
  `inventory` int(10) UNSIGNED NOT NULL DEFAULT 0, -- 库存
  `price` char(60) NOT NULL DEFAULT '',         -- 价格区间
  `min_price` decimal(10,2) NOT NULL DEFAULT 0.00, -- 最低价格
  `max_price` decimal(10,2) NOT NULL DEFAULT 0.00  -- 最高价格
);

-- 商品规格基础表 (sxo_goods_spec_base) 
CREATE TABLE `sxo_goods_spec_base` (
  `goods_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,     -- 规格价格
  `inventory` int(10) UNSIGNED NOT NULL DEFAULT 0, -- 规格库存
  `weight` decimal(10,2) NOT NULL DEFAULT 0.00,    -- 重量
  `volume` decimal(10,2) NOT NULL DEFAULT 0.00,    -- 体积
  `coding` char(80) NOT NULL DEFAULT '',           -- 规格编码
  `barcode` char(80) NOT NULL DEFAULT ''           -- 条形码
);
```

#### 3.1.3 仓库相关表
```sql
-- 仓库表 (sxo_warehouse)
CREATE TABLE `sxo_warehouse` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` char(60) NOT NULL DEFAULT '',          -- 仓库名称 (判断菜鸟仓关键字)
  `alias` char(60) NOT NULL DEFAULT '',         -- 仓库别名
  `is_enable` tinyint(3) NOT NULL DEFAULT 1     -- 是否启用
);

-- 仓库商品关联表 (sxo_warehouse_goods)
CREATE TABLE `sxo_warehouse_goods` (
  `warehouse_id` int(10) UNSIGNED NOT NULL DEFAULT 0, -- 仓库ID
  `goods_id` int(10) UNSIGNED NOT NULL DEFAULT 0,     -- 商品ID
  `inventory` int(10) UNSIGNED NOT NULL DEFAULT 0     -- 仓库库存
);
```

### 3.2 菜鸟接口参数完整映射表

#### 3.2.1 GLOBAL_SALE_ORDER_NOTIFY接口必需参数映射
| 菜鸟参数 | 类型 | 必需 | ShopXO数据来源 | 获取方式 | 备注 |
|---------|------|------|----------------|----------|------|
| **公共参数** |
| msg_type | string | √ | 固定值 | 'GLOBAL_SALE_ORDER_NOTIFY' | 接口标识 |
| logistic_provider_id | string | √ | 配置文件 | $config['cainiao_resource_code'] | **资源code: 95f7ac77fd52d162a68eaea5cef3dc55** |
| data_digest | string | √ | 签名计算 | base64_encode(md5($content.$app_secret, true)) | MD5+Base64签名 |
| to_code | string | × | 固定值 | 'GLOBAL_SALE' | 目的方编码 |
| logistics_interface | string | √ | 业务参数JSON | json_encode($business_params) | 核心业务数据 |
| **业务参数 (logistics_interface内容)** |
| ownerUserId | string | √ | 配置文件 | $config['cainiao_owner_user_id'] | **货主ID: 2220576876930** |
| businessUnitId | string | × | 配置文件 | $config['cainiao_business_unit_id'] | **BU信息: B06738021** |
| externalOrderCode | string | √ | sxo_order | $order['order_no'] | **订单编号，去重依据** |
| externalTradeCode | string | × | sxo_order | $order['order_no'] | 交易平台编码(可复用订单号) |
| orderType | string | √ | 配置文件 | $config['cainiao_order_type'] | **订单类型: BONDED_WHS** |
| storeCode | string | √ | 配置文件 | $config['cainiao_store_code'] | **仓库代码: JIX230** |
| externalShopId | string | × | 配置文件 | $config['cainiao_shop_id'] | 店铺ID |
| externalShopName | string | √ | 配置文件 | $config['cainiao_shop_name'] | **店铺名称** |
| orderSource | string | √ | 配置文件 | $config['cainiao_order_source'] | **订单来源: 201** |
| orderSubSource | string | × | 配置文件 | $config['cainiao_order_sub_source'] | 订单子渠道 |
| orderCreateTime | date | √ | sxo_order | date('Y-m-d H:i:s', $order['add_time']) | **订单创建时间** |
| **收货人信息** |
| consigneeName | string | √ | sxo_order_address | $order_address['name'] | **收货人姓名** |
| consigneePhone | string | √ | sxo_order_address | $order_address['tel'] | **收货人电话** |
| consigneeMobile | string | √ | sxo_order_address | $order_address['tel'] | 收货人手机(同电话) |
| consigneeAddress.province | string | √ | sxo_order_address | $order_address['province_name'] | **省份** |
| consigneeAddress.city | string | √ | sxo_order_address | $order_address['city_name'] | **城市** |
| consigneeAddress.area | string | √ | sxo_order_address | $order_address['county_name'] | **区县** |
| consigneeAddress.town | string | × | - | '' | 街道(ShopXO无此字段) |
| consigneeAddress.detail | string | √ | sxo_order_address | $order_address['address'] | **详细地址** |
| **商品信息** |
| items[].itemId | string | √ | sxo_order_detail | (string)$goods['goods_id'] | **商品ID** |
| items[].itemName | string | √ | sxo_order_detail | $goods['title'] | **商品名称** |
| items[].itemCode | string | × | sxo_order_detail | $goods['spec'] 或 $goods['spec_coding'] | 商品编码 |
| items[].quantity | int | √ | sxo_order_detail | (int)$goods['buy_number'] | **商品数量** |
| items[].itemPrice | decimal | √ | sxo_order_detail | (float)$goods['price'] | **商品单价** |
| **可选参数** |
| payTime | date | × | 支付时间 | date('Y-m-d H:i:s') | 当前时间(支付回调时) |
| totalAmount | decimal | × | sxo_order | (float)$order['total_price'] | 订单总金额 |
| actualAmount | decimal | × | sxo_order | (float)$order['pay_price'] | 实付金额 |

### 3.3 数据获取SQL示例
```sql
-- 获取完整订单数据
SELECT 
    o.*,
    oa.name, oa.tel, oa.province_name, oa.city_name, oa.county_name, oa.address
FROM sxo_order o
LEFT JOIN sxo_order_address oa ON o.id = oa.order_id
WHERE o.order_no = '订单号';

-- 获取订单商品明细
SELECT 
    od.goods_id, od.title, od.price, od.buy_number, od.spec, od.spec_coding, od.spec_barcode
FROM sxo_order_detail od
WHERE od.order_id = 订单ID;

-- 判断商品是否来自菜鸟仓库
SELECT w.name, w.alias
FROM sxo_warehouse_goods wg
LEFT JOIN sxo_warehouse w ON wg.warehouse_id = w.id
WHERE wg.goods_id = 商品ID 
  AND w.is_enable = 1
  AND (w.name LIKE '%菜鸟%' OR w.alias LIKE '%cainiao%');
```

## 四、关键配置参数来源确认

### 4.1 菜鸟平台提供的参数
```php
// 来自菜鸟开放平台的实际参数
$cainiao_config = [
    // 应用信息
    'app_code' => '102905',                                      // 应用appCode
    'resource_code' => '95f7ac77fd52d162a68eaea5cef3dc55',      // 资源code(logistic_provider_id)
    'app_secret' => '466aN6F8t0Q6jxiK8GUrFM355mju19j8',         // AppSecret
    
    // 货主信息  
    'owner_user_id' => '2220576876930',                         // 货主用户ID
    'business_unit_id' => 'B06738021',                          // BU信息
    
    // 业务配置
    'store_code' => 'JIX230',                                   // 仓库代码
    'shop_name' => '实际店铺名称',                               // 店铺名称
];
```

### 4.2 参数使用场景对比
| 参数名称 | 物流查询接口 | 订单下发接口 | 作用说明 |
|---------|-------------|-------------|----------|
| app_code (102905) | ✅ 业务参数.appCode | ❌ 不使用 | 应用标识，仅查询接口需要 |
| resource_code (95f7ac77...) | ✅ 公共参数.logistic_provider_id | ✅ 公共参数.logistic_provider_id | **两个接口都需要** |
| app_secret | ✅ 签名生成 | ✅ 签名生成 | **两个接口都需要** |
| owner_user_id | ❌ 不使用 | ✅ 业务参数.ownerUserId | 货主ID，仅下发接口需要 |
| business_unit_id | ❌ 不使用 | ✅ 业务参数.businessUnitId | BU信息，仅下发接口需要 |

## 五、代码修正建议

### 5.1 ExpressQueryService.php修正 
```php
// ❌ 当前错误的代码 (第236、248行)
'appCode' => $config['cainiao_app_name'],           // 应该用app_code
'logistic_provider_id' => $config['cainiao_app_code'], // 应该用resource_code

// ✅ 修正后的代码
'appCode' => $config['cainiao_app_code'],            // 102905
'logistic_provider_id' => $config['cainiao_resource_code'], // 95f7ac77...
```

### 5.2 Weixin.php中的菜鸟配置加载修正
```php
// extend/payment/Weixin.php 配置加载部分需要修正
'cainiao_app_code' => $main_config['cainiao']['app_code'] ?? '',           // ✅ 102905
'cainiao_resource_code' => $main_config['cainiao']['resource_code'] ?? '', // ✅ 95f7ac77...
'cainiao_app_secret' => $main_config['cainiao']['app_secret'] ?? '',       // ✅ 签名密钥
```

### 5.3 wxconfig.php最终正确配置
```php
// extend/payment/wxconfig.php
'cainiao' => [
    // 基础配置
    'enabled' => true,  // 启用菜鸟集成
    'environment' => 'sandbox', // 环境：sandbox/production
    'app_code' => '102905',     // 应用appCode (用于物流查询接口的业务参数)
    'resource_code' => '95f7ac77fd52d162a68eaea5cef3dc55', // 资源code (两个接口的公共参数)
    'app_secret' => '466aN6F8t0Q6jxiK8GUrFM355mju19j8',   // 应用密钥 (签名用)
    
    // 货主信息
    'owner_user_id' => '2220576876930', // 货主用户ID (订单下发接口专用)
    'business_unit_id' => 'B06738021',  // BU信息 (订单下发接口专用)
    
    // 订单配置
    'order_type' => 'BONDED_WHS',       // 订单类型
    'order_source' => '201',            // 订单来源
    'store_code' => 'JIX230',           // 仓库代码
    'shop_name' => 'ShopXO商城',        // 店铺名称
    
    // 其他配置
    'log_to_db' => true,
    'warehouse_keywords' => ['菜鸟', 'cainiao'], // 仓库识别关键词
]
```

## 六、完整实现流程图

### 6.1 微信支付成功 → 菜鸟订单下发流程
```
1. 微信支付回调 
   ↓
2. Weixin.php::Respond() 验证支付成功
   ↓  
3. 调用 CainiaoOrderNotify($payment_result)
   ↓
4. 检查菜鸟配置是否启用 ($config['cainiao_enabled'])
   ↓
5. 验证必需参数 (resource_code, app_secret)
   ↓
6. 获取订单数据 (sxo_order + sxo_order_address)
   ↓
7. 获取商品明细 (sxo_order_detail)
   ↓
8. 检查仓库类型 CheckCainiaoWarehouse()
   ├─ 查询商品仓库关联 (sxo_warehouse_goods)
   ├─ 检查仓库名称关键词 (sxo_warehouse.name/alias)
   └─ 匹配菜鸟仓库返回true
   ↓
9. 构建菜鸟API数据 BuildCainiaoOrderData()
   ├─ 组装公共参数 (msg_type, logistic_provider_id, etc.)
   ├─ 组装业务参数 (ownerUserId, externalOrderCode, etc.)
   └─ JSON编码业务参数到logistics_interface
   ↓
10. 生成签名 base64_encode(md5($content.$app_secret, true))
    ↓
11. 发送HTTP请求到菜鸟API SendToCainiao()
    ↓
12. 记录请求日志 LogCainiaoRequest()
    ↓
13. 返回处理结果 (不影响支付主流程)
```

### 6.2 菜鸟仓库判断逻辑
```sql
-- 判断商品是否来自菜鸟仓库的SQL
SELECT COUNT(*) as is_cainiao 
FROM sxo_warehouse_goods wg
JOIN sxo_warehouse w ON wg.warehouse_id = w.id  
WHERE wg.goods_id = :goods_id
  AND w.is_enable = 1
  AND (LOWER(w.name) LIKE '%菜鸟%' 
       OR LOWER(w.name) LIKE '%cainiao%'
       OR LOWER(w.alias) LIKE '%菜鸟%' 
       OR LOWER(w.alias) LIKE '%cainiao%');
```

## 七、必需参数缺失风险分析

### 7.1 高风险缺失参数 🔴
| 参数名 | 风险等级 | 影响 | 解决方案 |
|--------|---------|------|----------|
| ownerUserId | 🔴 高 | 无法识别货主，接口调用失败 | **必须配置正确的货主ID** |
| storeCode | 🔴 高 | 无法确定发货仓库，订单无法处理 | **必须配置正确的仓库代码** |
| externalOrderCode | 🔴 高 | 订单去重依据，重复订单会被拒绝 | **必须使用唯一的订单号** |

### 7.2 中风险缺失参数 🟡
| 参数名 | 风险等级 | 影响 | 解决方案 |
|--------|---------|------|----------|
| businessUnitId | 🟡 中 | 多BU场景下可能无法正确路由 | 单BU场景可不填，多BU必填 |
| orderType | 🟡 中 | 影响订单处理方式 | 根据实际业务选择类型 |
| consigneeAddress.town | 🟡 中 | 可能影响配送精确度 | ShopXO暂无此字段，设为空 |

### 7.3 低风险缺失参数 🟢  
| 参数名 | 风险等级 | 影响 | 解决方案 |
|--------|---------|------|----------|
| externalTradeCode | 🟢 低 | 仅用于识别查询，不影响核心功能 | 可复用订单号或设为空 |
| payTime | 🟢 低 | 仅用于统计分析 | 使用当前时间 |
| items[].itemCode | 🟢 低 | 商品识别的补充信息 | 使用商品规格或编码字段 |

## 八、测试验证建议

### 8.1 配置验证清单
- [ ] 确认菜鸟平台参数正确性
- [ ] 验证resource_code在两个接口中都能正常使用  
- [ ] 确认签名算法与菜鸟文档一致
- [ ] 测试沙箱环境接口调用

### 8.2 数据映射测试
- [ ] 创建测试订单，验证所有必需字段都有值
- [ ] 测试菜鸟仓库识别逻辑
- [ ] 验证商品数据完整性
- [ ] 测试收货地址格式转换

### 8.3 异常处理测试  
- [ ] 测试配置缺失时的降级处理
- [ ] 验证API调用失败时不影响支付流程
- [ ] 测试日志记录功能
- [ ] 验证重复订单的处理机制

## 九、总结

通过深入分析ExpressQueryService.php和菜鸟接口文档，发现了关键的参数配置错误：

1. **核心问题**：混淆了app_code和resource_code的使用场景
2. **关键修正**：
   - 物流查询：业务参数用app_code，公共参数用resource_code  
   - 订单下发：不需要app_code，公共参数用resource_code
3. **数据来源**：ShopXO的订单和商品表结构完全支持菜鸟接口要求
4. **实现方案**：在微信支付成功回调中集成菜鸟订单下发功能

修正后的方案能够确保菜鸟仓储接口的正确调用，实现支付成功后自动下发订单到菜鸟仓库的完整功能。
| logistic_provider_id | String | √ | 来源CP编号(资源code) |
| data_digest | String | √ | 请求签名 |
| to_code | String | × | 目的方编码，默认GLOBAL_SALE |
| logistics_interface | String | √ | 请求报文内容(JSON) |

### 1.3 业务参数分析

#### 1.3.1 必需参数
| 参数名 | 类型 | 长度 | 说明 | ShopXO对应字段 | 匹配状态 |
|--------|------|------|------|----------------|----------|
| ownerUserId | string | 99 | 货主用户ID | - | ❌ 缺失，需配置 |
| externalOrderCode | string | 99 | ERP订单编码(去重依据) | order_no | ✅ 匹配 |
| orderType | string | 99 | 订单类型 | - | ❌ 缺失，需配置 |
| storeCode | string | 100 | 仓库代码 | - | ❌ 缺失，需配置 |
| externalShopName | string | 512 | 店铺名称 | - | ❌ 缺失，需配置 |
| orderSource | string | 99 | 订单来源 | - | ❌ 缺失，需配置 |
| orderCreateTime | date | 99 | 订单创建时间 | add_time | ✅ 匹配(需转换格式) |

#### 1.3.2 收货人信息（必需）
| 参数名 | 类型 | 长度 | 说明 | ShopXO对应字段 | 匹配状态 |
|--------|------|------|------|----------------|----------|
| consigneeName | string | 60 | 收货人姓名 | order_address.name | ✅ 匹配 |
| consigneePhone | string | 32 | 收货人电话 | order_address.tel | ✅ 匹配 |
| consigneeMobile | string | 32 | 收货人手机 | order_address.tel | ✅ 匹配 |
| consigneeAddress.province | string | 60 | 省份 | order_address.province_name | ✅ 匹配 |
| consigneeAddress.city | string | 60 | 城市 | order_address.city_name | ✅ 匹配 |
| consigneeAddress.area | string | 60 | 区县 | order_address.county_name | ✅ 匹配 |
| consigneeAddress.detail | string | 200 | 详细地址 | order_address.address | ✅ 匹配 |

#### 1.3.3 商品信息（必需）
| 参数名 | 类型 | 长度 | 说明 | ShopXO对应字段 | 匹配状态 |
|--------|------|------|------|----------------|----------|
| items[].itemId | string | 60 | 商品ID | order_detail.goods_id | ✅ 匹配 |
| items[].itemName | string | 500 | 商品名称 | order_detail.title | ✅ 匹配 |
| items[].quantity | int | - | 商品数量 | order_detail.buy_number | ✅ 匹配 |
| items[].itemPrice | decimal | - | 商品单价 | order_detail.price | ✅ 匹配 |

#### 1.3.4 可选参数
| 参数名 | 类型 | 说明 | ShopXO对应字段 | 匹配状态 |
|--------|------|------|----------------|----------|
| businessUnitId | string | BU信息(多BU场景) | - | ❌ 缺失，需配置 |
| externalTradeCode | string | 交易平台交易编码 | order_no | ✅ 可复用 |
| externalShopId | string | 店铺ID | - | ❌ 缺失，需配置 |
| orderSubSource | string | 订单子渠道来源 | - | ❌ 缺失，需配置 |
| payTime | date | 支付时间 | - | ❌ 需从支付记录获取 |
| totalAmount | decimal | 订单总金额 | order.total_price | ✅ 匹配 |
| actualAmount | decimal | 实付金额 | order.pay_price | ✅ 匹配 |

## 二、ShopXO数据库字段分析

### 2.1 订单主表(sxo_order)
```sql
- id: 订单ID
- order_no: 订单号 → externalOrderCode
- user_id: 用户ID
- warehouse_id: 仓库ID → 用于判断是否菜鸟仓
- total_price: 订单总价 → totalAmount
- pay_price: 实付金额 → actualAmount
- add_time: 创建时间 → orderCreateTime
- status: 订单状态
- pay_status: 支付状态
```

### 2.2 收货地址表(sxo_order_address)
```sql
- order_id: 订单ID
- name: 收货人姓名 → consigneeName
- tel: 联系电话 → consigneePhone/consigneeMobile
- province_name: 省份名称 → consigneeAddress.province
- city_name: 城市名称 → consigneeAddress.city
- county_name: 区县名称 → consigneeAddress.area
- address: 详细地址 → consigneeAddress.detail
```

### 2.3 订单商品表(sxo_order_detail)
```sql
- order_id: 订单ID
- goods_id: 商品ID → items[].itemId
- title: 商品标题 → items[].itemName
- price: 商品单价 → items[].itemPrice
- buy_number: 购买数量 → items[].quantity
- spec: 商品规格 → items[].itemCode
- spec_coding: 商品编码
- spec_barcode: 条形码
```

### 2.4 仓库相关表(sxo_warehouse, sxo_warehouse_goods)
```sql
-- 仓库表
- id: 仓库ID
- name: 仓库名称 → 用于判断是否菜鸟仓
- alias: 仓库别名
- is_enable: 是否启用

-- 仓库商品关联表
- warehouse_id: 仓库ID
- goods_id: 商品ID
```

## 三、字段匹配状态总结

### 3.1 完全匹配字段 ✅
- 订单编号: order_no → externalOrderCode
- 收货人信息: name, tel, address → 完整收货人信息
- 商品信息: goods_id, title, price, buy_number → 完整商品信息
- 金额信息: total_price, pay_price → totalAmount, actualAmount
- 时间信息: add_time → orderCreateTime(需格式转换)

### 3.2 需要配置的字段 ❌
| 字段名 | 说明 | 建议配置值 |
|--------|------|------------|
| ownerUserId | 货主用户ID | 固定配置，如：14544 |
| businessUnitId | BU信息 | 可选，多BU场景下配置 |
| orderType | 订单类型 | BONDED_WHS(保税仓) |
| storeCode | 仓库代码 | 根据实际菜鸟仓库代码配置 |
| externalShopName | 店铺名称 | ShopXO商城或实际店铺名 |
| externalShopId | 店铺ID | 可选，外部店铺标识 |
| orderSource | 订单来源 | 201(电商平台) |
| orderSubSource | 订单子渠道 | 可选，如：301 |

### 3.3 需要处理的字段 ⚠️
| 字段名 | 问题 | 解决方案 |
|--------|------|----------|
| payTime | 支付时间缺失 | 从支付回调获取当前时间 |
| consigneeAddress.town | 街道信息缺失 | 设为空字符串或从详细地址解析 |
| items[].itemCode | 商品编码可选 | 使用spec或spec_coding字段 |

## 四、wxconfig.php配置模板

已创建`extend/payment/wxconfig.php`配置文件，包含菜鸟相关配置：

```php
'cainiao' => [
    // 基础配置
    'enabled' => false,                    // 是否启用菜鸟集成
    'environment' => 'sandbox',            // 环境：sandbox/production
    'app_code' => '',                      // 菜鸟应用代码
    'app_secret' => '',                    // 菜鸟应用密钥
    
    // 货主信息
    'owner_user_id' => '14544',           // 货主用户ID
    'business_unit_id' => '',             // BU信息
    
    // 订单配置
    'order_type' => 'BONDED_WHS',         // 订单类型
    'order_source' => '201',              // 订单来源
    'store_code' => 'JIX230',             // 仓库代码
    'shop_name' => 'ShopXO商城',          // 店铺名称
    
    // 其他配置
    'log_to_db' => true,                  // 是否记录到数据库
    'warehouse_keywords' => ['菜鸟', 'cainiao'], // 仓库关键词
]
```

## 五、集成实现状态

### 5.1 已实现功能 ✅
- 微信支付回调触发菜鸟订单下发
- 仓库智能识别(通过仓库名称关键词)
- 完整的订单数据映射和转换
- 错误处理和日志记录
- 签名生成和API调用

### 5.2 核心实现方法
```php
// 在Weixin.php中添加的核心方法
- CainiaoOrderNotify()      // 主入口方法
- CheckCainiaoWarehouse()   // 仓库检查
- BuildCainiaoOrderData()   // 数据构建
- SendToCainiao()          // API调用
- LogCainiaoRequest()      // 日志记录
```

### 5.3 集成流程
1. 微信支付成功回调 → 验证签名
2. 获取订单和商品信息
3. 检查是否菜鸟仓库商品
4. 构建菜鸟API数据格式
5. 调用菜鸟GLOBAL_SALE_ORDER_NOTIFY接口
6. 记录调用日志

## 六、使用建议

### 6.1 配置步骤
1. 修改`extend/payment/wxconfig.php`中的菜鸟配置项
2. 设置`enabled => true`启用菜鸟集成
3. 配置正确的`app_code`和`app_secret`
4. 根据实际情况调整仓库代码等参数

### 6.2 测试建议
1. 先在沙箱环境测试
2. 确保仓库名称包含"菜鸟"关键词
3. 监控日志文件查看调用结果
4. 验证菜鸟系统是否收到订单

### 6.3 注意事项
- API调用失败不会影响支付流程
- 仅支持微信支付回调触发
- 需要预先在菜鸟平台配置相关权限
- 建议定期检查日志确保正常运行

## 七、总结

ShopXO系统与菜鸟GLOBAL_SALE_ORDER_NOTIFY接口的兼容性良好，主要订单和商品字段都能完整匹配。通过配置补充缺失的业务参数，可以实现完整的订单下发功能。集成方案已在Weixin.php中实现，支持智能仓库识别和自动订单推送。