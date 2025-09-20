<?php

namespace Nova\Fibers\Commands;

use Nova\Fibers\FiberManager;
use Nova\Fibers\Core\FiberPool;

/**
 * StatusCommand - 纤程状态查看命令
 * 
 * 用于查看当前运行的纤程ID和状态信息
 */
class StatusCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('status')
             ->setDescription('Show current fiber status and IDs')
             ->setHelp('This command displays information about active fibers and their IDs.');
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
        $output->writeln('<info>Current Fiber Status:</info>');
        
        // 获取纤程池实例
        $pool = FiberManager::pool();
        
        if ($pool === null) {
            $output->writeln('<comment>No active fiber pool found.</comment>');
            return self::SUCCESS;
        }
        
        // 显示池信息
        $output->writeln("Pool Size: " . $pool->getSize());
        $output->writeln("Active Fibers: " . $pool->getActiveFiberCount());
        $output->writeln("Idle Fibers: " . $pool->getIdleFiberCount());
        
        // 显示通道信息
        $this->showChannelInfo($output);
        
        return self::SUCCESS;
    }

    /**
     * 显示通道信息
     *
     * @param OutputInterface $output 输出接口
     * @return void
     */
    private function showChannelInfo(OutputInterface $output): void
    {
        // 这里需要访问ChannelManager或者通过FiberManager获取通道信息
        // 由于当前设计中没有ChannelManager，我们暂时显示一个提示
        $output->writeln("\n<comment>Channel information is not yet implemented.</comment>");
        
        // 如果将来实现了ChannelManager，可以这样显示：
        /*
        $channels = ChannelManager::getAllChannels();
        if (empty($channels)) {
            $output->writeln("No active channels.");
        } else {
            $output->writeln("Active Channels:");
            foreach ($channels as $name => $channel) {
                $output->writeln("  - {$name}: " . $channel->getLength() . " items");
            }
        }
        */
    }
}
