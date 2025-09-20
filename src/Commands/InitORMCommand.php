<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

/**
 * 初始化ORM配置命令
 */
class InitORMCommand extends Command
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->setName('orm:init')
             ->setDescription('Initialize ORM configuration')
             ->addOption('driver', 'd', 'Database driver (mysql, pgsql, sqlite)', 'mysql')
             ->addOption('host', 'H', 'Database host', 'localhost')
             ->addOption('port', 'p', 'Database port', '3306')
             ->addOption('database', null, 'Database name', 'test')
             ->addOption('username', 'u', 'Database username', 'root')
             ->addOption('password', 'P', 'Database password', '');
    }

    /**
     * 执行命令
     *
     * @param array $input 输入参数
     * @return int 返回码
     */
    public function handle(array $input = []): int
    {
        echo "Initializing ORM configuration...\n";

        // 获取选项值
        $driver = $input['options']['driver'] ?? 'mysql';
        $host = $input['options']['host'] ?? 'localhost';
        $port = $input['options']['port'] ?? '3306';
        $database = $input['options']['database'] ?? 'test';
        $username = $input['options']['username'] ?? 'root';
        $password = $input['options']['password'] ?? '';

        // 创建配置目录
        $configDir = __DIR__ . '/../../config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // 生成配置文件内容
        $config = [
            'orm' => [
                'default' => 'default',
                'connections' => [
                    'default' => [
                        'driver' => $driver,
                        'host' => $host,
                        'port' => (int)$port,
                        'database' => $database,
                        'username' => $username,
                        'password' => $password,
                        'charset' => 'utf8',
                        'collation' => 'utf8_unicode_ci',
                        'prefix' => '',
                    ]
                ]
            ]
        ];

        // 写入配置文件
        $configFile = $configDir . '/orm.php';
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        if (file_put_contents($configFile, $content) !== false) {
            echo "ORM configuration file created successfully at: {$configFile}\n";
            return 0;
        } else {
            echo "Error: Failed to create ORM configuration file.\n";
            return 1;
        }
    }
}
