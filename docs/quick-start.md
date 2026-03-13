# 快速开始

## 1) 执行单个任务

```php
use Kode\Fibers\Fibers;

$result = Fibers::go(fn() => 'hello fibers');
echo $result;
```

## 2) 带上下文执行

```php
use Kode\Fibers\Fibers;
use Kode\Context\Context;

$value = Fibers::withContext(
    ['trace_id' => 'trace-1001'],
    fn() => Context::get('trace_id')
);
echo $value;
```

## 3) 批量任务

```php
use Kode\Fibers\Fibers;

$items = [1, 2, 3, 4];
$results = Fibers::batch($items, fn(int $item) => $item * 2, 2);
print_r($results);
```

## 4) 健壮批处理（容错）

```php
use Kode\Fibers\Fibers;

$response = Fibers::resilientBatch(
    ['a' => 1, 'b' => 2, 'c' => 3],
    function (int $item, string $key) {
        if ($key === 'b') {
            throw new RuntimeException('模拟失败');
        }
        return $item * 10;
    },
    ['fail_fast' => false, 'max_retries' => 1]
);

print_r($response['results']);
print_r(array_keys($response['errors']));
```

## 5) 健壮单任务（熔断 + 重试）

```php
use Kode\Fibers\Fibers;

$result = Fibers::resilientRun(
    function () {
        throw new RuntimeException('外部依赖异常');
    },
    [
        'max_retries' => 0,
        'failure_threshold' => 1,
        'fallback' => fn() => 'fallback-data',
    ]
);

echo $result;
```

## 6) 高级分布式分配（标签 + 节点健康）

```php
use Kode\Fibers\Fibers;

$tasks = [
    'job-1' => ['required_tags' => ['gpu']],
    'job-2' => ['required_tags' => ['cpu']],
];

$nodes = [
    'node-a' => ['healthy' => true, 'tags' => ['gpu', 'cpu']],
    'node-b' => ['healthy' => true, 'tags' => ['cpu']],
];

$dispatch = Fibers::scheduleDistributedAdvanced(
    $tasks,
    $nodes,
    ['unhealthy_nodes' => ['node-b']]
);

print_r($dispatch);
```
