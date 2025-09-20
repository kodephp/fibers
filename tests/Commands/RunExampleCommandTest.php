<?php

declare(strict_types=1);

namespace Nova\Fibers\Tests\Commands;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Commands\RunExampleCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * RunExampleCommand 测试类
 *
 * @package Nova\Fibers\Tests\Commands
 * @covers \Nova\Fibers\Commands\RunExampleCommand
 */
class RunExampleCommandTest extends TestCase
{
    /**
     * 测试运行高级示例
     *
     * @covers \Nova\Fibers\Commands\RunExampleCommand::execute
     * @return void
     */
    public function testRunAdvancedExample(): void
    {
        if (!class_exists(Application::class)) {
            $this->markTestSkipped('Symfony Console is not available.');
        }

        $application = new Application();
        $application->add(new RunExampleCommand());

        $command = $application->find('run:example');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'example' => 'advanced_features_example'
        ]);

        // 验证命令执行成功
        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * 测试运行基础示例
     *
     * @covers \Nova\Fibers\Commands\RunExampleCommand::execute
     * @return void
     */
    public function testRunBasicExample(): void
    {
        if (!class_exists(Application::class)) {
            $this->markTestSkipped('Symfony Console is not available.');
        }

        $application = new Application();
        $application->add(new RunExampleCommand());

        $command = $application->find('run:example');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'example' => 'basic_usage_example'
        ]);

        // 验证命令执行成功
        $this->assertSame(0, $commandTester->getStatusCode());
    }

    /**
     * 测试运行无效示例
     *
     * @covers \Nova\Fibers\Commands\RunExampleCommand::execute
     * @return void
     */
    public function testRunInvalidExample(): void
    {
        if (!class_exists(Application::class)) {
            $this->markTestSkipped('Symfony Console is not available.');
        }

        $application = new Application();
        $application->add(new RunExampleCommand());

        $command = $application->find('run:example');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'example' => 'invalid_example'
        ]);

        // 验证命令执行失败
        $this->assertNotSame(0, $commandTester->getStatusCode());
    }
}