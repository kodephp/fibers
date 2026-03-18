# 快速开始

## 安装

```bash
# 最小安装（仅核心功能，使用原生实现）
composer require kode/fibers

# 完整安装（推荐，使用增强包实现）
composer require kode/fibers kode/http-client guzzlehttp/psr7 kode/console kode/facade
```

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

## 7) HTTP 客户端（自动降级）

```php
use Kode\Fibers\HttpClient\HttpClient;

$client = new HttpClient();

// 自动选择最佳驱动
// - 已安装 kode/http-client + guzzlehttp/psr7：使用完整功能
// - 未安装：使用原生 cURL 实现
$response = $client->get('https://api.example.com');

echo $response->getStatusCode();
echo $response->getBody();

// 检查当前驱动
if ($client->isUsingNativeDriver()) {
    echo "使用原生 cURL 驱动";
} else {
    echo "使用 kode/http-client 包驱动";
}
```

## 8) 连接池

```php
use Kode\Fibers\Fibers;

// PDO 连接池
$pdoPool = Fibers::pdoPool(
    'mysql:host=127.0.0.1;dbname=test',
    'username',
    'password'
);

$conn = $pdoPool->getConnection();
$result = $conn->query('SELECT * FROM users');
$pdoPool->releaseConnection($conn);

// Redis 连接池
$redisPool = Fibers::redisPool('127.0.0.1', 6379);
```

## 9) 调试模式

```php
use Kode\Fibers\Fibers;

// 启用调试模式
Fibers::enableDebug();

// 记录调试信息
\FiberDebugger::log('test', ['data' => 'value']);

// 获取当前纤程信息
$info = \FiberDebugger::getCurrentFiberInfo();
print_r($info);
```
