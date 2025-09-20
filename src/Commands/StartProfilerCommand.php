<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 启动Web Profiler面板命令
 */
class StartProfilerCommand extends Command
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->setName('profiler:start')
             ->setDescription('Start the web profiler panel')
             ->addOption('host', 'H', 'Host address to bind to', '127.0.0.1')
             ->addOption('port', 'p', 'Port number to listen on', '8080')
             ->addOption('docroot', 'd', 'Document root directory', getcwd() . '/public');
    }

    /**
     * 执行命令
     *
     * @param array $input 输入参数
     * @return int 返回码
     */
    public function handle(array $input = []): int
    {
        $host = $input['options']['host'] ?? '127.0.0.1';
        $port = $input['options']['port'] ?? '8080';
        $docroot = $input['options']['docroot'] ?? getcwd() . '/public';

        echo "Starting web profiler panel...\n";
        echo "Host: {$host}:{$port}\n";
        echo "Document root: {$docroot}\n";
        echo "Press Ctrl+C to stop the server\n\n";

        // 创建public目录
        if (!is_dir($docroot)) {
            mkdir($docroot, 0755, true);
        }

        // 创建index.php文件
        $indexPath = $docroot . '/index.php';
        $indexContent = <<<'PHP'
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Nova\Fibers\Profiler\FiberProfiler;
use Nova\Fibers\Profiler\WebProfilerPanel;

// 启用分析器
FiberProfiler::enable();

// 渲染Profiler面板
echo WebProfilerPanel::render();
PHP;

        if (file_put_contents($indexPath, $indexContent) === false) {
            echo "Error: Failed to create index.php file.\n";
            return 1;
        }

        // 启动内置Web服务器
        $command = "php -S {$host}:{$port} -t {$docroot}";
        echo "Executing: {$command}\n";

        passthru($command, $returnCode);

        return $returnCode;
    }
}
