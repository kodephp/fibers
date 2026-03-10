# Profiler 可视化面板

## API

- `Fibers::profile(callable $task, string $name = 'task')`
- `Fibers::profilerDashboard(array $records)`

## 示例

```php
use Kode\Fibers\Fibers;

$profile = Fibers::profile(fn() => 'hello', 'demo-task');
$html = Fibers::profilerDashboard($profile['records']);

file_put_contents(__DIR__ . '/profiler.html', $html);
```

## 输出字段

- `name`：任务名称
- `status`：`success` 或 `failed`
- `duration_ms`：执行耗时（毫秒）
- `memory_delta`：执行期内存变化
- `error`：错误信息
