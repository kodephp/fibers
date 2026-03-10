<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Support\Environment;
use Kode\Fibers\Support\CpuInfo;

echo "Kode/Fibers 环境诊断示例\n";
echo "========================\n\n";

// 检查PHP版本
echo "PHP版本信息:\n";
echo "  PHP版本: " . PHP_VERSION . "\n";
echo "  是否支持Fiber: " . (version_compare(PHP_VERSION, '8.1.0') >= 0 ? '是' : '否') . "\n";
if (version_compare(PHP_VERSION, '8.1.0') < 0) {
    die("  错误: 需要PHP 8.1或更高版本\n");
}

echo "  析构函数中Fiber限制: " . (PHP_VERSION_ID >= 80400 ? '无限制' : '限制(需安全模式)') . "\n\n";

// 获取CPU信息
echo "CPU信息:\n";
$cpuCount = CpuInfo::get();
echo "  CPU核心数: {$cpuCount}\n";
echo "  推荐纤程池大小: " . min($cpuCount * 4, 32) . "\n\n";

// 环境诊断
echo "环境诊断:\n";
$issues = Environment::diagnose();

if (empty($issues)) {
    echo "  ✓ 环境良好，无已知问题\n";
} else {
    foreach ($issues as $issue) {
        echo "  ⚠ {$issue['type']}: {$issue['message']}\n";
        if (isset($issue['recommendation'])) {
            echo "    建议: {$issue['recommendation']}\n";
        }
    }
}

echo "\n";

// 检查禁用函数
echo "禁用函数检查:\n";
$disabledFunctions = explode(',', ini_get('disable_functions'));
$disabledFunctions = array_map('trim', $disabledFunctions);
$requiredFunctions = ['pcntl_fork', 'proc_open', 'exec'];

foreach ($requiredFunctions as $function) {
    if (in_array($function, $disabledFunctions)) {
        echo "  ⚠ {$function} 已被禁用\n";
    } else {
        echo "  ✓ {$function} 可用\n";
    }
}

echo "\n";

// 检查扩展
echo "扩展检查:\n";
$extensions = ['sockets', 'pcntl', 'posix'];
foreach ($extensions as $extension) {
    if (extension_loaded($extension)) {
        echo "  ✓ {$extension} 扩展已安装\n";
    } else {
        echo "  ⚠ {$extension} 扩展未安装\n";
    }
}

echo "\n";

echo "环境诊断示例完成!\n";