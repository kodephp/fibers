# 运行时桥接

本章节描述与 Swoole/OpenSwoole/Swow/Workerman 的运行时桥接能力。

## API

- `Fibers::runtimeBridgeInfo()`：返回当前环境可用桥接能力
- `Fibers::runOnBridge(callable $task, ?string $preferred = null)`：在指定或最佳桥接环境执行任务

## 示例

```php
use Kode\Fibers\Fibers;

$bridge = Fibers::runtimeBridgeInfo();
print_r($bridge);

$value = Fibers::runOnBridge(fn() => 'ok', 'native');
echo $value;
```

## 说明

- 当前版本提供统一入口与能力检测。
- 若对应扩展未安装，会自动回退到 `native` 执行模式。
