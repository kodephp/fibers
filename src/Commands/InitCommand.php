<?php

namespace Nova\Fibers\Commands;

use Nova\Fibers\Support\ConfigLocator;

/**
 * InitCommand - 初始化命令
 * 
 * 用于生成框架特定的配置文件
 */
class InitCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('init')
             ->setDescription('Initialize configuration file for the current framework')
             ->setHelp('This command generates a configuration file for the detected framework environment.');
    }

    /**
     * 执行命令
     *
     * @param InputInterface $input 输入接口
     * @param OutputInterface $output 输出接口
     * @return int 命令执行结果
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Initializing Nova Fibers configuration...</info>');
        
        // 检测当前框架环境
        $framework = $this->detectFramework();
        
        if ($framework === null) {
            $output->writeln('<comment>Unable to detect framework. Generating default configuration...</comment>');
            $framework = 'default';
        } else {
            $output->writeln("<info>Detected framework: {$framework}</info>");
        }
        
        // 生成配置文件
        $result = $this->generateConfigFile($framework, $output);
        
        if ($result) {
            $output->writeln('<info>Configuration file generated successfully!</info>');
            return self::SUCCESS;
        } else {
            $output->writeln('<error>Failed to generate configuration file.</error>');
            return self::FAILURE;
        }
    }

    /**
     * 检测当前框架环境
     *
     * @return string|null 框架名称或null（未检测到）
     */
    private function detectFramework(): ?string
    {
        // 检查Laravel
        if (class_exists('\Illuminate\Foundation\Application')) {
            return 'laravel';
        }
        
        // 检查Symfony
        if (class_exists('\Symfony\Component\HttpKernel\Kernel')) {
            return 'symfony';
        }
        
        // 检查Yii3
        if (class_exists('\Yiisoft\Yii\Console\Application') || 
            class_exists('\Yiisoft\Yii\Web\Application')) {
            return 'yii3';
        }
        
        // 检查ThinkPHP
        if (defined('THINK_VERSION') || defined('THINK_PATH')) {
            return 'thinkphp';
        }
        
        return null;
    }

    /**
     * 生成配置文件
     *
     * @param string $framework 框架名称
     * @param OutputInterface $output 输出接口
     * @return bool 是否成功生成
     */
    private function generateConfigFile(string $framework, OutputInterface $output): bool
    {
        // 确定目标路径
        $targetPath = $this->getConfigTargetPath($framework);
        
        if ($targetPath === null) {
            $output->writeln('<error>Unable to determine target path for configuration file.</error>');
            return false;
        }
        
        // 检查文件是否已存在
        if (file_exists($targetPath)) {
            $output->writeln("<comment>Configuration file already exists at: {$targetPath}</comment>");
            $confirm = $this->askConfirmation($input, $output, 'Do you want to overwrite it? (y/N): ', false);
            
            if (!$confirm) {
                $output->writeln('<info>Operation cancelled.</info>');
                return true;
            }
        }
        
        // 生成配置文件
        $result = ConfigLocator::generateFrameworkConfig($framework, $targetPath);
        
        if ($result) {
            $output->writeln("<info>Configuration file created at: {$targetPath}</info>");
        } else {
            $output->writeln("<error>Failed to create configuration file at: {$targetPath}</error>");
        }
        
        return $result;
    }

    /**
     * 获取配置文件目标路径
     *
     * @param string $framework 框架名称
     * @return string|null 目标路径或null（无法确定）
     */
    private function getConfigTargetPath(string $framework): ?string
    {
        $cwd = getcwd();
        
        switch ($framework) {
            case 'laravel':
                return $cwd . '/config/fibers.php';
                
            case 'symfony':
                return $cwd . '/config/packages/fibers.yaml';
                
            case 'yii3':
                return $cwd . '/config/fibers.php';
                
            case 'thinkphp':
                return $cwd . '/config/fibers.php';
                
            default:
                return $cwd . '/config/fibers.php';
        }
    }

    /**
     * 询问用户确认
     *
     * @param InputInterface $input 输入接口
     * @param OutputInterface $output 输出接口
     * @param string $question 问题
     * @param bool $default 默认答案
     * @return bool 用户答案
     */
    private function askConfirmation(InputInterface $input, OutputInterface $output, string $question, bool $default = true): bool
    {
        // 简单实现，实际应用中可能需要更复杂的交互
        $output->write($question);
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        $answer = trim($line);
        
        if (empty($answer)) {
            return $default;
        }
        
        return strtolower($answer[0]) === 'y';
    }
}