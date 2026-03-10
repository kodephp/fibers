<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kode\Fibers\Commands\InitCommand;

echo "Kode/Fibers InitCommand 演示\n";
echo "============================\n\n";

// 演示支持的框架
echo "支持的框架:\n";
$reflection = new \ReflectionClass(InitCommand::class);
$supportedFrameworks = $reflection->getConstant('SUPPORTED_FRAMEWORKS');

foreach ($supportedFrameworks as $key => $name) {
    echo "  - {$key}: {$name}\n";
}

echo "\n";

// 演示框架验证功能
echo "框架验证演示:\n";

// 创建 InitCommand 实例
$command = new InitCommand();

// 使用反射访问私有方法
$validateMethod = (new \ReflectionClass($command))->getMethod('validateFramework');
$validateMethod->setAccessible(true);

$getProviderMethod = (new \ReflectionClass($command))->getMethod('getProviderClass');
$getProviderMethod->setAccessible(true);

$generateConfigMethod = (new \ReflectionClass($command))->getMethod('generateConfigContent');
$generateConfigMethod->setAccessible(true);

$frameworksToTest = ['laravel', 'symfony', 'invalid'];

foreach ($frameworksToTest as $framework) {
    echo "测试框架: {$framework}\n";
    
    // 捕获输出
    ob_start();
    $validatedFramework = $validateMethod->invoke($command, $framework);
    $output = ob_get_contents();
    ob_end_clean();
    
    echo "  验证结果: {$validatedFramework}\n";
    if (!empty($output)) {
        echo "  输出信息: " . trim($output) . "\n";
    }
    
    // 获取提供者类
    $providerClass = $getProviderMethod->invoke($command, $validatedFramework);
    echo "  服务提供者类: {$providerClass}\n";
    
    echo "\n";
}

echo "演示完成!\n";