# 安装与版本

## 环境要求

- PHP >= 8.1
- 建议启用扩展：`pcntl`、`posix`、`sockets`

## 安装

```bash
composer require kode/fibers
```

## 当前版本策略

- 主版本：兼容性变更
- 次版本：新增特性（如 `resilientBatch`、分布式调度 API）
- 修订版本：问题修复与稳定性增强

## 验证安装

```bash
composer test
php bin/fibers status
```

若上述命令执行成功，说明基础依赖与运行环境可用。
