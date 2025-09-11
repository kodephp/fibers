<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 纤程状态命令
 *
 * @package Nova\Fibers\Commands
 */
class StatusCommand extends Command
{
    /**
     * StatusCommand 构造函数
     */
    public function __construct()
    {
        $this->setName('status')
             ->setDescription('Show current fiber status');
    }

    /**
     * 执行命令
     *
     * @param array $input 输入参数
     * @return int 退出码
     */
    public function handle(array $input = []): int
    {
        echo "Fiber Status Information:\n";
        echo str_repeat('-', 30) . "\n";

        // 显示 PHP 版本信息
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Fiber Support: " . ($this->checkFiberSupport() ? "Available" : "Not Available") . "\n";

        // 显示环境信息
        $this->showEnvironmentInfo();

        // 显示纤程池信息（如果存在）
        $this->showFiberPoolInfo();

        return 0;
    }

    /**
     * 检查 Fiber 支持
     *
     * @return bool
     */
    protected function checkFiberSupport(): bool
    {
        return class_exists(\Fiber::class);
    }

    /**
     * 显示环境信息
     *
     * @return void
     */
    protected function showEnvironmentInfo(): void
    {
        echo "\nEnvironment Information:\n";
        echo "  OS: " . PHP_OS . "\n";
        echo "  SAPI: " . PHP_SAPI . "\n";

        // 显示禁用函数
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $fiberRelated = array_filter($disabledFunctions, function ($func) {
            return stripos($func, 'fiber') !== false ||
                   stripos($func, 'pcntl') !== false ||
                   stripos($func, 'exec') !== false;
        });

        if (!empty($fiberRelated)) {
            echo "  Disabled Functions (Fiber-related):\n";
            foreach ($fiberRelated as $func) {
                echo "    - {$func}\n";
            }
        }
    }

    /**
     * 显示纤程池信息
     *
     * @return void
     */
    protected function showFiberPoolInfo(): void
    {
        // 这里可以扩展以显示实际的纤程池信息
        // 目前只是一个占位符
        echo "\nFiber Pool Information:\n";
        echo "  Note: Fiber pool statistics would be shown here when implemented.\n";

        // 如果有 FiberPool 类，可以在这里获取实际信息
        if (class_exists(\Nova\Fibers\Core\FiberPool::class)) {
            echo "  FiberPool class: Available\n";
        } else {
            echo "  FiberPool class: Not found\n";
        }
    }
}
