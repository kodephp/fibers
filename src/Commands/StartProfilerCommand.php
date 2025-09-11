<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * 启动Web Profiler面板命令
 */
class StartProfilerCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('profiler:start')
            ->setDescription('Start the web profiler panel')
            ->setHelp('This command starts a built-in web server to display the Fiber Profiler panel')
            ->addOption(
                'host',
                'H',
                InputOption::VALUE_REQUIRED,
                'Host to bind to',
                '127.0.0.1'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'Port to listen on',
                '8080'
            )
            ->addOption(
                'docroot',
                'd',
                InputOption::VALUE_REQUIRED,
                'Document root',
                __DIR__ . '/../../public'
            );
    }

    /**
     * 执行命令
     *
     * @param InputInterface $input 输入
     * @param OutputInterface $output 输出
     * @return int 返回码
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $docroot = $input->getOption('docroot');

        $output->writeln("<info>Starting web profiler panel...</info>");
        $output->writeln("<comment>Host: {$host}:{$port}</comment>");
        $output->writeln("<comment>Document root: {$docroot}</comment>");
        $output->writeln("<comment>Press Ctrl+C to stop the server</comment>\n");

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

        file_put_contents($indexPath, $indexContent);

        // 启动内置Web服务器
        $command = "php -S {$host}:{$port} -t {$docroot}";
        $output->writeln("<comment>Executing: {$command}</comment>");

        passthru($command, $returnCode);

        return $returnCode;
    }
}
