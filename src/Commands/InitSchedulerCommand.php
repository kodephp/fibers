<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 初始化分布式调度器配置命令
 */
class InitSchedulerCommand extends Command
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->setName('scheduler:init')
             ->setDescription('Initialize distributed scheduler configuration')
             ->addOption('cluster-nodes', 'n', 'Number of cluster nodes', '3')
             ->addOption('node-address', 'a', 'Node address', '127.0.0.1')
             ->addOption('port', 'p', 'Base port number', '8000');
    }

    /**
     * 执行命令
     *
     * @param array $input 输入参数
     * @return int 返回码
     */
    public function handle(array $input = []): int
    {
        echo "Initializing distributed scheduler configuration...\n";

        // 获取选项值
        $clusterNodes = $input['options']['cluster-nodes'] ?? '3';
        $nodeAddress = $input['options']['node-address'] ?? '127.0.0.1';
        $port = $input['options']['port'] ?? '8000';

        // 创建配置目录
        $configDir = getcwd() . '/config';
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
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        if (file_put_contents($configFile, $content) !== false) {
            echo "Configuration file created successfully at: {$configFile}\n";
            echo "\nGenerated configuration:\n";
            $displayConfig = $config;
            echo json_encode($displayConfig, JSON_PRETTY_PRINT) . "\n";
            echo "\nDistributed scheduler configuration initialized successfully!\n";
            return 0;
        } else {
            echo "Error: Failed to create configuration file.\n";
            return 1;
        }
    }
}
