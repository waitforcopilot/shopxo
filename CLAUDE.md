# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目简介

ShopXO是一个企业级免费开源的B2C电商系统，基于ThinkPHP框架开发。支持PC+手机自适应、独立H5、小程序（支付宝、微信、百度、头条&抖音、QQ、快手）和APP（IOS、Android）。

## 常用命令

### PHP依赖管理
```bash
# 安装依赖
composer install

# 更新依赖  
composer update

# 自动加载
composer dump-autoload
```

### ThinkPHP框架命令
```bash
# 通过think命令行工具执行各种操作
php think

# 清除缓存
php think clear

# 生成服务发现
php think service:discover

# 发布vendor资源
php think vendor:publish
```

### 数据库操作
```bash
# 数据库安装和初始化通过web界面完成
# 访问 /install.php 进行系统安装
```

## 架构概述

### 目录结构
- `app/` - 应用目录，按模块组织（admin管理后台、api接口、index前台、install安装）
  - `admin/` - 后台管理模块（控制器、视图、表单、语言包）
  - `api/` - API接口模块
  - `index/` - 前台显示模块
  - `install/` - 系统安装模块
  - `service/` - 业务逻辑服务类
  - `module/` - 可复用模块组件

- `config/` - 配置文件目录
  - `shopxo.php` - ShopXO核心配置文件
  - `app.php` - 应用配置
  - 其他ThinkPHP框架配置文件

- `extend/` - 扩展类库目录
  - `base/` - 基础扩展类（支付、短信、邮件、二维码等）
  - `payment/` - 支付网关扩展
  - `qrcode/` - 二维码库

- `public/` - Web访问目录
  - `static/` - 静态资源（CSS、JS、图片）
  - 各模块入口文件

- `vendor/` - Composer依赖包

### 核心架构
1. **多应用模式**: 基于ThinkPHP多应用架构，分离管理后台、前台展示、API接口
2. **服务层架构**: 业务逻辑封装在service目录中，控制器保持精简
3. **模块化设计**: 支持插件化开发，功能模块可独立开发和部署
4. **多端支持**: 统一后端接口，支持PC、H5、小程序、APP等多端访问

### 关键服务类
- `AdminService` - 管理员相关服务
- `UserService` - 用户相关服务  
- `GoodsService` - 商品相关服务
- `OrderService` - 订单相关服务
- `PaymentService` - 支付相关服务
- `ConfigService` - 系统配置服务

### 数据库
- 使用ThinkPHP ORM进行数据库操作
- 支持MySQL数据库
- 数据库结构定义在config/shopxo.sql中

### 开发环境要求
- PHP >= 8.0
- MySQL 5.6+
- 支持Rewrite的Web服务器（Apache/Nginx）
- 开启相关PHP扩展（curl、gd、mbstring等）

### 缓存系统
- 支持多种缓存方式（文件、Redis等）
- 缓存键名定义在config/shopxo.php中
- 使用CacheService统一管理缓存操作

### 权限系统
- 基于RBAC权限模型
- AdminPowerService处理权限验证
- AdminRoleService处理角色管理

### 插件系统
- 支持功能插件化开发
- 插件目录：app/plugins/
- PluginsService负责插件管理

### 模板系统
- 使用ThinkPHP内置模板引擎
- 支持多主题切换
- 视图文件位于各模块的view目录下