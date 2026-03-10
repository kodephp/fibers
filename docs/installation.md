# 安装 Kode/Fibers

本指南将帮助您在项目中安装 Kode/Fibers 库。

## 系统要求

- PHP 8.1 或更高版本
- Composer 2.0 或更高版本

## 安装步骤

### 1. 使用 Composer 安装

打开终端，导航到您的项目目录，然后运行以下命令：

```bash
composer require kode/fibers
```

这将自动下载并安装 Kode/Fibers 库及其依赖项。

### 2. 检查安装

安装完成后，您可以通过查看项目的 `vendor` 目录来确认 Kode/Fibers 是否已成功安装。

```bash
ls -la vendor/kode/fibers
```

## 版本要求说明

Kode/Fibers 针对不同 PHP 版本提供了不同的功能支持：

| PHP 版本 | 支持级别 | 功能限制 |
|---------|---------|---------|
| PHP 8.1 | 完全支持 | 需启用安全析构模式 |
| PHP 8.2 | 完全支持 | 需启用安全析构模式 |
| PHP 8.3 | 完全支持 | 需启用安全析构模式 |
| PHP 8.4 | 完全支持 | 原生支持析构函数中切换纤程 |

## 可选依赖

为了获得最佳性能，我们建议安装以下可选依赖：

- **Swoole 或 OpenSwoole**: 提供高性能异步 I/O 支持
- **Swow**: 另一个高性能网络框架，专为 PHP 设计
- **Amp**: 异步 PHP 库

### 安装 Swoole

```bash
pecl install swoole
```

然后在 `php.ini` 中添加：

```ini
extension=swoole.so
```

### 安装 Swow

```bash
pecl install swow
```

然后在 `php.ini` 中添加：

```ini
extension=swow.so
```

## 后续步骤

安装完成后，您可以：

1. [初始化配置文件](configuration.md)
2. 查看[快速开始指南](quick-start.md)
3. 探索[核心概念](core-concepts.md)

## 遇到问题？

如果在安装过程中遇到问题，请查看[故障排除指南](troubleshooting.md)或在 [GitHub](https://github.com/Kode-php/fibers/issues) 上提交问题。