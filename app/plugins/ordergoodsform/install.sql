# 下单商品表单 - 应用
CREATE TABLE IF NOT EXISTS `{PREFIX}plugins_ordergoodsform` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `title` char(60) NOT NULL DEFAULT '' COMMENT '标题',
  `config_data` longtext COMMENT '配置表单数据',
  `config_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '配置表单数量',
  `goods_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '关联商品数量',
  `is_enable` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '是否启用（0否，1是）',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  `upd_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `title` (`title`),
  KEY `is_enable` (`is_enable`),
  KEY `config_count` (`config_count`),
  KEY `goods_count` (`goods_count`)
) ENGINE=InnoDB DEFAULT CHARSET={CHARSET} ROW_FORMAT=DYNAMIC COMMENT='下单商品表单 - 应用';


# 下单商品表单关联商品 - 应用
CREATE TABLE IF NOT EXISTS `{PREFIX}plugins_ordergoodsform_goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `form_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品表单id',
  `goods_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品id',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  KEY `goods_id` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET={CHARSET} ROW_FORMAT=DYNAMIC COMMENT='下单商品表单关联商品 - 应用';


# 下单商品表单关联商品数据 - 应用
CREATE TABLE IF NOT EXISTS `{PREFIX}plugins_ordergoodsform_goods_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `form_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品表单id',
  `goods_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品id',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户id',
  `title` char(60) NOT NULL DEFAULT '' COMMENT '标题',
  `md5_key` char(32) NOT NULL DEFAULT '' COMMENT '数据唯一md5key',
  `content` text COMMENT '数据值',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  `upd_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  KEY `goods_id` (`goods_id`),
  KEY `md5_key` (`md5_key`)
) ENGINE=InnoDB DEFAULT CHARSET={CHARSET} ROW_FORMAT=DYNAMIC COMMENT='下单商品表单关联商品数据 - 应用';


# 下单商品表单关联订单数据 - 应用
CREATE TABLE IF NOT EXISTS `{PREFIX}plugins_ordergoodsform_order_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `form_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品表单id',
  `goods_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商品id',
  `order_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '订单id',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户id',
  `md5_key` char(32) NOT NULL DEFAULT '' COMMENT '数据唯一md5key',
  `title` char(60) NOT NULL DEFAULT '' COMMENT '标题',
  `content` text COMMENT '数据值',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  KEY `goods_id` (`goods_id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `md5_key` (`md5_key`)
) ENGINE=InnoDB DEFAULT CHARSET={CHARSET} ROW_FORMAT=DYNAMIC COMMENT='下单商品表单关联订单数据 - 应用';