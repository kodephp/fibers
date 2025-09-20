<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

use Nova\Fibers\Support\Environment;

/**
 * 运行示例命令类
 * 
 * 用于运行项目中的示例代码
 */
class RunExampleCommand extends Command
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->setName('example:run')
             ->setDescription('Run example code')
             ->addArgument('example', 'Example name to run');
    }

    /**
     * 执行命令
     * 
     * @param array $input 输入参数
     * @return int 退出码
     */
    public function handle(array $input = []): int
    {
        $example = $input['arguments']['example'] ?? null;
        
        if ($example === null) {
            echo "Error: Example name is required!\n";
            return 1;
        }
        
        $exampleFile = __DIR__ . '/../../examples/' . $example . '.php';
        
        if (!file_exists($exampleFile)) {
            echo "Error: Example '{$example}' not found!\n";
            return 1;
        }
        
        if (!Environment::supportsFibers()) {
            echo "Error: Fibers are not supported in this environment!\n";
            return 1;
        }
        
        echo "Running example: {$example}\n";
        
        try {
            require $exampleFile;
            echo "Success: Example '{$example}' completed successfully!\n";
            return 0;
        } catch (\Throwable $e) {
            echo "Error: Example '{$example}' failed with error: " . $e->getMessage() . "\n";
            return 1;
        }
    }
}