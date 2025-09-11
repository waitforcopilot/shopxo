# 字段添加 v2.0.0
ALTER TABLE `{PREFIX}plugins_express_log` add `express_type` char(60) NOT NULL DEFAULT '' COMMENT '快递类型' after `id`;
ALTER TABLE `{PREFIX}plugins_express_log` add `express_name` char(60) NOT NULL DEFAULT '' COMMENT '快递名称' after `express_type`;