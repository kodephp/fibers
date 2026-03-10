# 上下文传递机制

本章节描述如何在 Fiber 执行链路中安全传递上下文变量。

## 核心能力

- `Context::snapshot()`：导出当前上下文快照
- `Context::restore(array $snapshot)`：恢复上下文
- `Context::runWith(array $context, callable $task)`：临时上下文运行
- `Context::fork(array $extra = [])`：基于当前上下文派生新上下文
- `Fibers::concurrentWithContext(array $context, array $tasks)`：并发任务上下文透传

## 使用示例

```php
use Kode\Fibers\Context\Context;
use Kode\Fibers\Fibers;

Context::set('trace_id', 'root');

$result = Fibers::concurrentWithContext(
    ['tenant' => 'acme'],
    [
        fn() => Context::get('trace_id') . ':' . Context::get('tenant'),
        fn() => Context::get('trace_id') . ':' . Context::get('tenant'),
    ]
);

print_r($result);
```

## 安全建议

- 不要在上下文中存储密码、Token 原文
- 建议保存 trace_id、tenant_id、request_id 这类可观测字段
