# CLI 使用指南

本文详细介绍 `vendor/bin/fibers` 命令行工具的使用方法与示例。

## 安装

```bash
composer require nova/fibers
```

## 基本用法

```bash
php vendor/bin/fibers [command] [options] [arguments]
```

## 命令列表

- init: 为当前项目生成配置文件（自动识别框架）
- status: 查看当前纤程状态
- cleanup: 清理僵尸纤程
- benchmark: 进行性能压测
- help: 显示帮助

## init 命令

```bash
php vendor/bin/fibers init [--force|-f]
```

- 自动检测框架：Laravel / Symfony / Yii / ThinkPHP / 其他
- 生成配置位置：
  - Laravel: `config/fibers.php`
  - Symfony: `config/packages/fibers.yaml`
  - Yii: `config/fibers.php`
  - ThinkPHP: `config/fibers.php`
  - 其他: `fibers-config.php`

本包会通过内部定位器自动查找并读取上述配置，并将键名统一为：
`default_pool`, `channels`, `features`, `scheduler`, `profiler`。

## 自动加载配置

无需手动传入配置，直接使用：

```php
use Nova\\Fibers\\Facades\\FiberManager;

$pool = FiberManager::pool(); // 自动读取并规范化配置
```

## 退出码

- 0: 成功
- 非0: 错误（会输出错误信息）
