<?php

declare(strict_types=1);

namespace Nova\Fibers\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

/**
 * 初始化ORM配置命令
 */
class InitORMCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('orm:init')
            ->setDescription('Initialize ORM configuration')
            ->setHelp('This command initializes the configuration for the Fiber-aware ORM')
            ->addOption(
                'driver',
                'd',
                InputOption::VALUE_REQUIRED,
                'Database driver (mysql, pgsql, sqlite)',
                'mysql'
            )
            ->addOption(
                'host',
                'H',
                InputOption::VALUE_REQUIRED,
                'Database host',
                'localhost'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'Database port',
                '3306'
            )
            ->addOption(
                'database',
                null,
                InputOption::VALUE_REQUIRED,
                'Database name',
                'test'
            )
            ->addOption(
                'username',
                'u',
                InputOption::VALUE_REQUIRED,
                'Database username',
                'root'
            )
            ->addOption(
                'password',
                'P',
                InputOption::VALUE_REQUIRED,
                'Database password',
                ''
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
        $output->writeln('<info>Initializing ORM configuration...</info>');

        // 获取选项值
        $driver = $input->getOption('driver');
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $database = $input->getOption('database');
        $username = $input->getOption('username');
        $password = $input->getOption('password');

        // 如果密码未通过选项提供，询问用户
        if (empty($password)) {
            $question = new Question('Database password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);

            $helper = $this->getHelper('question');
            $password = $helper->ask($input, $output, $question);
        }

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
        file_put_contents($configFile, "<?php\n\nreturn " . var_export($config, true) . ";\n");

        $output->writeln("<info>ORM configuration file created at: {$configFile}</info>");

        // 显示配置内容（隐藏密码）
        $displayConfig = $config;
        $displayConfig['orm']['connections']['default']['password'] = '********';
        $output->writeln("\n<comment>Generated configuration:</comment>");
        $output->writeln(json_encode($displayConfig, JSON_PRETTY_PRINT));

        $output->writeln("\n<info>ORM configuration initialized successfully!</info>");

        return Command::SUCCESS;
    }
}
