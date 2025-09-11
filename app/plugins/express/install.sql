# 请求日志
CREATE TABLE IF NOT EXISTS `{PREFIX}plugins_express_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `express_type` char(60) NOT NULL DEFAULT '' COMMENT '快递类型',
  `express_name` char(60) NOT NULL DEFAULT '' COMMENT '快递名称',
  `express_number` char(60) NOT NULL DEFAULT '' COMMENT '快递单号',
  `express_code` char(30) NOT NULL DEFAULT '' COMMENT '快递编码',
  `request_params` mediumtext COMMENT '请求参数（数组则json字符串存储）',
  `response_data` mediumtext COMMENT '响应参数（数组则json字符串存储）',
  `add_time` int unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`),
  KEY `express_type` (`express_type`),
  KEY `express_name` (`express_name`),
  KEY `number_code` (`express_number`,`express_code`)
) ENGINE=InnoDB DEFAULT CHARSET={CHARSET} ROW_FORMAT=DYNAMIC COMMENT='物流请求日志 - 应用';