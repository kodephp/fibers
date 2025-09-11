<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * 初始化分布式调度器配置命令
 */
class InitSchedulerCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('scheduler:init')
            ->setDescription('Initialize distributed scheduler configuration')
            ->setHelp('This command initializes the configuration for the distributed scheduler')
            ->addOption(
                'cluster-nodes',
                'n',
                InputOption::VALUE_REQUIRED,
                'Number of cluster nodes',
                '3'
            )
            ->addOption(
                'node-address',
                'a',
                InputOption::VALUE_REQUIRED,
                'Node address',
                '127.0.0.1'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'Port number',
                '8000'
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
        $output->writeln('<info>Initializing distributed scheduler configuration...</info>');

        // 获取选项值
        $clusterNodes = $input->getOption('cluster-nodes');
        $nodeAddress = $input->getOption('node-address');
        $port = $input->getOption('port');

        // 创建配置目录
        $configDir = __DIR__ . '/../../config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // 生成配置文件内容
        $config = [
            'scheduler' => [
                'type' => 'distributed',
                'local' => [
                    'pool_size' => 64,
                    'max_exec_time' => 30
                ],
                'distributed' => [
                    'cluster_nodes' => (int)$clusterNodes,
                    'node_address' => $nodeAddress,
                    'port' => (int)$port,
                    'discovery' => [
                        'type' => 'static',
                        'nodes' => []
                    ]
                ]
            ]
        ];

        // 生成节点列表
        for ($i = 0; $i < $clusterNodes; $i++) {
            $config['scheduler']['distributed']['discovery']['nodes'][] = [
                'id' => "node_{$i}",
                'address' => $nodeAddress,
                'port' => (int)$port + $i
            ];
        }

        // 写入配置文件
        $configFile = $configDir . '/scheduler.php';
        file_put_contents($configFile, "<?php\n\nreturn " . var_export($config, true) . ";\n");

        $output->writeln("<info>Configuration file created at: {$configFile}</info>");

        // 显示配置内容
        $output->writeln("\n<comment>Generated configuration:</comment>");
        $output->writeln(json_encode($config, JSON_PRETTY_PRINT));

        $output->writeln("\n<info>Distributed scheduler configuration initialized successfully!</info>");

        return Command::SUCCESS;
    }
}
