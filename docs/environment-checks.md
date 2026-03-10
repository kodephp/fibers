# 环境检测

本指南将详细介绍 Kode/Fibers 中的环境检测功能，帮助您识别和处理可能影响纤程执行的环境限制。

## 为什么需要环境检测？

PHP Fiber 的运行依赖于特定的 PHP 版本和环境设置。在某些情况下，服务器配置或 PHP 设置可能会限制 Fiber 的功能或性能。Kode/Fibers 提供了全面的环境检测工具，可以帮助您：

- 确认 PHP 版本是否满足要求
- 检测可能影响 Fiber 执行的禁用函数
- 识别可能导致问题的 PHP 配置
- 获取系统信息（如 CPU 核心数）
- 提供诊断报告，帮助排查问题

## 基本环境要求

Kode/Fibers 有以下基本环境要求：

- PHP 8.1 或更高版本
- Composer 2.0 或更高版本
- PHP 8.1+ 中启用的 Fiber 扩展（注意：PHP 8.1+ 内置 Fiber，无需额外安装扩展）

## 使用环境检测工具

### 执行全面诊断

```php
use Kode\Fibers\Support\Environment;

// 执行全面环境诊断
$issues = Environment::diagnose();

// 输出诊断结果
foreach ($issues as $issue) {
    echo "[{$issue['severity']}] {$issue['type']}: {$issue['message']}\n";
}
```

诊断结果示例：

```
[warning] function_disabled: proc_open is disabled
[warning] fiber_unsafe: set_time_limit may break fiber suspension
[info] php_version: PHP 8.2.10 detected (recommended: >= 8.1)
[info] cpu_cores: 8 CPU cores detected
```

### 检测特定问题

```php
use Kode\Fibers\Support\Environment;

// 检查 PHP 版本是否满足要求
$isVersionSupported = Environment::checkPhpVersion();

// 检查特定函数是否被禁用
$isDisabled = Environment::isFunctionDisabled('proc_open');

// 检查是否存在可能影响 Fiber 的问题
$hasFiberUnsafeFunctions = Environment::hasFiberUnsafeFunctions();

// 获取 CPU 核心数
$cpuCount = Environment::getCpuCores();

// 检测当前使用的框架
$framework = Environment::detectFramework();
```

### 命令行诊断

Kode/Fibers 提供了命令行工具来执行环境诊断：

```bash
# 使用内置命令行工具
php vendor/bin/fibers diagnose

# 使用框架特定命令（如 Laravel）
php artisan fibers:diagnose
```

命令行诊断会生成类似以下的输出：

```
===== Kode/Fibers Environment Diagnostic =====

PHP Version: 8.2.10 (required: >= 8.1)
PHP SAPI: cli
CPU Cores: 8

Potential Issues:
  ⚠️  function_disabled: proc_open is disabled
  ⚠️  function_disabled: shell_exec is disabled
  ⚠️  fiber_unsafe: set_time_limit may break fiber suspension

Recommended Actions:
  1. Enable 'proc_open' and 'shell_exec' for full functionality
  2. Avoid using 'set_time_limit' in fiber code
  3. Consider upgrading to PHP 8.4 for improved fiber destructor support

===== End of Diagnostic =====
```

## 常见环境问题及解决方案

### 1. PHP 版本不兼容

**问题**：使用的 PHP 版本低于 8.1

**影响**：Fiber 功能完全不可用

**解决方案**：
- 升级 PHP 版本到 8.1 或更高
- 如果无法升级，可以使用协程库替代方案（如 Swoole、Swow）

### 2. 禁用函数问题

某些 PHP 函数可能会被服务器配置禁用，这可能会影响 Kode/Fibers 的功能。以下是一些常见的被禁用函数及其影响：

| 函数名 | 影响 | 解决方案 |
|--------|------|----------|
| `proc_open` | 无法执行子进程，影响某些高级功能 | 启用该函数或避免使用依赖它的功能 |
| `shell_exec` | 无法执行 shell 命令 | 启用该函数或避免使用依赖它的功能 |
| `pcntl_fork` | 无法创建子进程 | 启用该函数或使用其他并发方案 |
| `curl_exec` | 影响 HTTP 客户端功能 | 启用该函数或使用 stream 替代 |
| `file_get_contents` | 影响文件操作和 HTTP 请求 | 启用该函数或使用其他文件操作函数 |

**检测禁用函数的代码示例**：

```php
use Kode\Fibers\Support\Environment;

$requiredFunctions = ['curl_exec', 'file_get_contents', 'stream_socket_client'];
$disabledFunctions = [];

foreach ($requiredFunctions as $function) {
    if (Environment::isFunctionDisabled($function)) {
        $disabledFunctions[] = $function;
    }
}

if (!empty($disabledFunctions)) {
    echo "The following required functions are disabled: " . implode(', ', $disabledFunctions) . "\n";
    echo "Please enable them in your php.ini file.\n";
}
```

### 3. Fiber 不安全函数

某些函数在 Fiber 环境中使用可能会导致问题，特别是在 PHP 8.4 之前的版本。

| 函数名 | 问题 | 解决方案 |
|--------|------|----------|
| `set_time_limit` | 可能会中断 Fiber 挂起 | 避免使用，或使用 `@set_time_limit(0)` 禁用时间限制 |
| `sleep` / `usleep` | 在非 Fiber 感知的环境中会阻塞 | 使用 Kode/Fibers 提供的替代函数或确保它们在 Fiber 中调用 |
| `exit` / `die` | 会终止整个进程，包括所有 Fiber | 避免使用，抛出异常替代 |
| `register_shutdown_function` | 执行时机不确定 | 谨慎使用，了解其行为 |

**检测和处理不安全函数的代码示例**：

```php
use Kode\Fibers\Support\Environment;

// 检测是否存在不安全函数
$unsafeFunctions = Environment::getFiberUnsafeFunctions();

if (!empty($unsafeFunctions)) {
    echo "Warning: The following functions may cause issues in a fiber environment:\n";
    foreach ($unsafeFunctions as $function) {
        echo "  - $function\n";
    }
}

// 禁用 set_time_limit 以避免问题
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
```

### 4. PHP 8.4 之前的析构函数限制

**问题**：在 PHP 8.4 之前，不允许在对象的析构函数中调用 `Fiber::suspend()`

**影响**：可能导致致命错误或不可预期的行为

**解决方案**：
- Kode/Fibers 会自动检测 PHP 版本并启用安全析构模式
- 避免在析构函数中执行可能导致 Fiber 挂起的操作
- 如果可能，升级到 PHP 8.4 或更高版本

**代码示例**：

```php
// Kode/Fibers 内部会自动处理析构函数限制
// 以下是内部实现的简化版本
if (PHP_VERSION_ID < 80400) {
    // 启用安全析构模式
    Fiber::enableSafeDestructMode();
}

// 在您自己的代码中，避免在析构函数中调用可能导致挂起的操作
class MyClass {
    public function __destruct() {
        // 避免这样做
        // $this->someOperationThatMaySuspend();
    }
}
```

### 5. 内存限制

**问题**：PHP 内存限制过低，可能导致大量 Fiber 运行时出现内存不足

**影响**：可能导致 `OutOfMemoryError` 或不可预期的行为

**解决方案**：
- 增加 PHP 内存限制（`memory_limit`）
- 减少单个 Fiber 中的内存使用
- 控制并发 Fiber 的数量
- 定期释放不再使用的资源

**检测和处理内存限制的代码示例**：

```php
// 检查当前内存限制
$memoryLimit = ini_get('memory_limit');
echo "Current memory limit: $memoryLimit\n";

// 如果内存限制过低，尝试增加
if (str_ends_with($memoryLimit, 'M') && intval($memoryLimit) < 256) {
    echo "Warning: Memory limit may be too low for fiber operations\n";
    echo "Consider increasing it to at least 256M in php.ini\n";
    
    // 运行时尝试增加（可能需要 PHP 配置允许）
    @ini_set('memory_limit', '256M');
}

// 在 Fiber 代码中定期检查内存使用
$task = function() {
    // 执行一些操作
    
    // 检查内存使用
    $memoryUsed = memory_get_usage() / 1024 / 1024; // MB
    if ($memoryUsed > 100) { // 如果使用了超过100MB内存
        echo "Warning: High memory usage in fiber ($memoryUsed MB)\n";
        // 尝试释放资源
        gc_collect_cycles();
    }
};
```

### 6. 多线程安全问题

**问题**：PHP 不是真正的多线程语言，但在某些情况下（如使用 pthread 扩展）可能会出现线程安全问题

**影响**：可能导致数据损坏或不可预期的行为

**解决方案**：
- 避免在多线程环境中使用 Fiber
- 如果必须使用，确保正确同步访问共享资源
- 了解 PHP 的线程安全限制

**检测线程安全问题的代码示例**：

```php
use Kode\Fibers\Support\Environment;

// 检查是否启用了线程安全
if (Environment::isThreadSafe()) {
    echo "Warning: Running in thread-safe mode. Use caution with shared resources.\n";
}

// 在多线程环境中使用互斥锁保护共享资源
$mutex = new \Mutex();
$sharedData = [];

$task = function() use ($mutex, &$sharedData) {
    // 获取锁
    $mutex->lock();
    try {
        // 安全地访问共享资源
        $sharedData[] = generateSomeData();
    } finally {
        // 确保释放锁
        $mutex->unlock();
    }
};
```

## 性能优化建议

### 1. 选择合适的 PHP 版本

- 对于生产环境，推荐使用 PHP 8.2 或更高版本
- PHP 8.4 提供了更好的 Fiber 支持，特别是在析构函数方面

### 2. 安装可选扩展

为了获得最佳性能，建议安装以下可选扩展：

- **Swoole** 或 **OpenSwoole**：提供高性能异步 I/O 支持
- **Swow**：另一个高性能网络框架
- **Event**：提供事件循环支持

**检测和建议安装扩展的代码示例**：

```php
use Kode\Fibers\Support\Environment;

// 检查是否安装了推荐的扩展
$recommendedExtensions = ['swoole', 'swow', 'event'];
$missingExtensions = [];

foreach ($recommendedExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "Recommended extensions not installed: " . implode(', ', $missingExtensions) . "\n";
    echo "Consider installing them for better performance:\n";
    echo "  - For Swoole: pecl install swoole\n";
    echo "  - For Swow: pecl install swow\n";
    echo "  - For Event: pecl install event\n";
}
```

### 3. 优化 PHP 配置

以下 PHP 配置选项可能会影响 Fiber 的性能：

| 配置项 | 建议值 | 说明 |
|--------|--------|------|
| `memory_limit` | `256M` 或更高 | 为大量 Fiber 提供足够的内存 |
| `opcache.enable` | `1` | 启用 OPcache 提高性能 |
| `opcache.jit` | `1235` | 启用 JIT 编译提高性能 |
| `max_execution_time` | `0`（无限制）或较大值 | 避免中断长时间运行的任务 |
| `realpath_cache_size` | `256K` 或更大 | 提高文件路径解析性能 |

**检测和优化 PHP 配置的代码示例**：

```php
// 检查并优化 PHP 配置
function optimizePhpConfig() {
    $configs = [
        'memory_limit' => ['min' => '256M', 'recommended' => '512M'],
        'max_execution_time' => ['min' => 30, 'recommended' => 0],
        'realpath_cache_size' => ['min' => '128K', 'recommended' => '256K']
    ];
    
    $recommendations = [];
    
    foreach ($configs as $key => $values) {
        $current = ini_get($key);
        // 简化的比较逻辑，实际应用中可能需要更复杂的单位转换
        if ($current < $values['min']) {
            $recommendations[] = "Increase '$key' from '$current' to at least '{$values['recommended']}'";
        }
    }
    
    return $recommendations;
}

$recommendations = optimizePhpConfig();
if (!empty($recommendations)) {
    echo "PHP Configuration Recommendations:\n";
    foreach ($recommendations as $rec) {
        echo "  - $rec\n";
    }
    echo "Update these settings in your php.ini file for better performance.\n";
}
```

## 环境检测集成到应用中

### 应用启动时的环境检查

建议在应用启动时执行环境检查，及早发现潜在问题：

```php
// 在应用入口文件中
use Kode\Fibers\Support\Environment;

// 注册启动时的环境检查
register_shutdown_function(function() {
    // 只在开发环境显示详细的环境问题
    if (env('APP_ENV') === 'development') {
        $issues = Environment::diagnose();
        if (!empty($issues)) {
            echo "\n===== Kode/Fibers Environment Issues =====\n";
            foreach ($issues as $issue) {
                echo "[{$issue['severity']}] {$issue['type']}: {$issue['message']}\n";
            }
            echo "==========================================\n\n";
        }
    }
});

// 对于严重问题，可以选择在开发环境中抛出异常
if (env('APP_ENV') === 'development') {
    $criticalIssues = Environment::getCriticalIssues();
    if (!empty($criticalIssues)) {
        throw new \RuntimeException(
            "Critical environment issues detected:\n" .
            implode('\n', array_map(fn($i) => "- {$i['message']}", $criticalIssues))
        );
    }
}
```

### 定期环境健康检查

对于长时间运行的应用（如守护进程），建议定期执行环境健康检查：

```php
use Kode\Fibers\Support\Environment;
use Kode\Fibers\Facades\Fiber;

// 启动一个定期执行环境检查的 Fiber
Fiber::run(function() {
    while (true) {
        // 执行环境检查
        $issues = Environment::diagnose();
        
        // 记录新发现的问题
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'critical' || $issue['severity'] === 'warning') {
                logWarning("Environment issue: {$issue['message']}");
            }
        }
        
        // 每小时检查一次
        sleep(3600);
    }
});
```

## 环境报告生成

Kode/Fibers 提供了生成详细环境报告的功能，这对于排查问题非常有用：

```php
use Kode\Fibers\Support\Environment;

// 生成详细的环境报告
$report = Environment::generateReport();

// 保存报告到文件
file_put_contents('fibers-environment-report.txt', $report);

// 或者直接输出
 echo $report;
```

环境报告示例：

```
===== Kode/Fibers Environment Report =====
Generated: 2023-10-15 14:30:22

System Information:
  OS: Linux 5.15.0-76-generic
  PHP Version: 8.2.10
  PHP SAPI: fpm-fcgi
  CPU Cores: 8
  Memory Limit: 512M
  Thread Safety: disabled

Kode/Fibers Configuration:
  Version: 1.0.0
  Fiber Pool Size: 32 (CPU * 4)
  PHP >= 8.4: false
  Safe Destruct Mode: enabled

Disabled Functions:
  proc_open
  shell_exec

Fiber Unsafe Functions in Use:
  set_time_limit

Recommended Actions:
  1. Enable 'proc_open' and 'shell_exec' for full functionality
  2. Avoid using 'set_time_limit' in fiber code
  3. Consider upgrading to PHP 8.4 for improved fiber destructor support

===== End of Report =====
```

## 下一步

- 查看 [最佳实践](best-practices.md) 文档了解更多使用建议
- 查看 [API 参考](api-reference.md) 文档了解完整的 API
- 查看 [框架集成](framework-integration.md) 文档了解如何在不同框架中使用