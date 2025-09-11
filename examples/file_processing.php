<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Core\FiberPool;
use Nova\Fibers\Facades\Fiber;
use Nova\Fibers\Channel\Channel;

/**
 * 文件处理示例
 * 
 * 此脚本演示了如何使用 nova/fibers 包进行并发文件处理
 */

echo "Nova Fibers File Processing Example\n";
echo str_repeat("=", 50) . "\n";

// 创建示例文件
echo "Creating sample files...\n";
$sampleDir = __DIR__ . '/sample_files';
if (!is_dir($sampleDir)) {
    mkdir($sampleDir, 0755, true);
}

// 创建示例文件内容
$sampleContents = [
    "This is the content of file 1. It contains some sample text for processing.\n",
    "File 2 has different content with more text to process and analyze.\n",
    "The third file contains even more content for our file processing example.\n",
    "File number four with additional content for concurrent processing.\n",
    "Finally, the fifth file with its own unique content for processing.\n"
];

// 创建示例文件
for ($i = 0; $i < 5; $i++) {
    $filename = "{$sampleDir}/file_" . ($i + 1) . ".txt";
    file_put_contents($filename, $sampleContents[$i] . str_repeat("Line {$i} repeated.\n", 100));
    echo "Created: file_" . ($i + 1) . ".txt\n";
}

echo "\nProcessing files concurrently...\n";

// 定义文件处理函数
function processFile($filepath) {
    $filename = basename($filepath);
    echo "Processing {$filename}...\n";
    
    // 读取文件内容
    $content = file_get_contents($filepath);
    
    // 模拟处理时间
    usleep(rand(100000, 500000)); // 0.1-0.5秒
    
    // 分析文件内容
    $lines = substr_count($content, "\n");
    $words = str_word_count($content);
    $chars = strlen($content);
    
    // 模拟处理结果
    $result = [
        'filename' => $filename,
        'lines' => $lines,
        'words' => $words,
        'characters' => $chars,
        'processed_at' => date('Y-m-d H:i:s')
    ];
    
    echo "Completed processing {$filename}\n";
    return $result;
}

// 获取所有示例文件
$files = glob("{$sampleDir}/file_*.txt");

// 使用纤程池并发处理文件
$start = microtime(true);

try {
    $pool = new FiberPool(['size' => 3]);
    
    $tasks = array_map(function ($file) {
        return fn() => processFile($file);
    }, $files);
    
    $results = $pool->concurrent($tasks);
    
    $elapsed = microtime(true) - $start;
    
    echo "\nAll files processed in " . number_format($elapsed, 2) . " seconds\n";
    echo "Processing results:\n";
    
    foreach ($results as $result) {
        echo "  - {$result['filename']}: {$result['lines']} lines, {$result['words']} words, {$result['characters']} chars\n";
    }
} catch (Exception $e) {
    echo "File processing failed: " . $e->getMessage() . "\n";
}

// 演示使用通道进行文件处理
echo "\nProcessing files using Channel communication...\n";

// 创建通道
$channel = Channel::make('file-processing', 5);

// 启动文件处理生产者
Fiber::run(function () use ($files, $channel) {
    foreach ($files as $file) {
        $channel->push($file);
        echo "Queued file for processing: " . basename($file) . "\n";
    }
    $channel->close();
    echo "All files queued for processing.\n";
});

// 启动文件处理消费者
$processingResults = [];
for ($i = 0; $i < 2; $i++) { // 2个并发消费者
    Fiber::run(function () use ($channel, &$processingResults, $i) {
        while (($file = $channel->pop(1)) !== null) {
            $result = processFile($file);
            $processingResults[] = $result;
            echo "Consumer {$i} processed: {$result['filename']}\n";
        }
        echo "Consumer {$i} finished.\n";
    });
}

// 等待一段时间确保所有文件都被处理
usleep(3000000); // 3秒

echo "\nChannel-based processing completed.\n";
echo "Processed " . count($processingResults) . " files.\n";

// 清理示例文件
echo "\nCleaning up sample files...\n";
foreach ($files as $file) {
    unlink($file);
}
rmdir($sampleDir);

echo "\n" . str_repeat("=", 50) . "\n";
echo "File Processing Example Completed!\n";