<?php

namespace Nova\Fibers\Tests\Commands;

use PHPUnit\Framework\TestCase;
use Nova\Fibers\Commands\RunExampleCommand;

/**
 * @covers \Nova\Fibers\Commands\RunExampleCommand
 */
class RunExampleCommandTest extends TestCase
{
    protected function setUp(): void
    {
        // 检查是否安装了symfony/console依赖
        if (!class_exists('Symfony\Component\Console\Application')) {
            $this->markTestSkipped('symfony/console is not installed');
        }
    }
    public function testRunBasicExample(): void
    {
        // 使用反射来避免直接依赖symfony/console
        $applicationClass = 'Symfony\Component\Console\Application';
        $commandTesterClass = 'Symfony\Component\Console\Tester\CommandTester';

        $application = new $applicationClass();
        $command = new RunExampleCommand();
        $command->setName('example:run');
        $application->add($command);

        $commandTester = new $commandTesterClass($command);

        // Test running the basic example
        $commandTester->execute([
            'command' => $command->getName(),
            'example' => 'basic'
        ]);

        // Check that the command executed successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Check that the output contains expected text
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Running basic example...', $output);
        $this->assertStringContainsString('completed successfully', $output);
    }

    public function testRunAdvancedExample(): void
    {
        // 使用反射来避免直接依赖symfony/console
        $applicationClass = 'Symfony\Component\Console\Application';
        $commandTesterClass = 'Symfony\Component\Console\Tester\CommandTester';

        $application = new $applicationClass();
        $command = new RunExampleCommand();
        $command->setName('example:run');
        $application->add($command);

        $commandTester = new $commandTesterClass($command);

        // Test running the advanced example
        $commandTester->execute([
            'command' => $command->getName(),
            'example' => 'advanced'
        ]);

        // Check that the command executed successfully
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Check that the output contains expected text
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Running advanced example...', $output);
        $this->assertStringContainsString('completed successfully', $output);
    }

    public function testRunInvalidExample(): void
    {
        // 使用反射来避免直接依赖symfony/console
        $applicationClass = 'Symfony\Component\Console\Application';
        $commandTesterClass = 'Symfony\Component\Console\Tester\CommandTester';

        $application = new $applicationClass();
        $command = new RunExampleCommand();
        $command->setName('example:run');
        $application->add($command);

        $commandTester = new $commandTesterClass($command);

        // Test running an invalid example
        $commandTester->execute([
            'command' => $command->getName(),
            'example' => 'invalid'
        ]);

        // Check that the command failed
        $this->assertEquals(1, $commandTester->getStatusCode());

        // Check that the output contains error message
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('not found', $output);
    }
}
