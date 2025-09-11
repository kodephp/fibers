<?php

namespace Nova\Fibers\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunExampleCommand extends Command
{
    protected static $defaultName = 'example:run';
    protected static $defaultDescription = 'Run example files to demonstrate nova/fibers functionality';

    protected function configure(): void
    {
        $this
            ->setName('example:run')
            ->setDescription('Run example files to demonstrate nova/fibers functionality')
            ->addArgument('example', InputArgument::OPTIONAL, 'The example to run (basic|advanced)', 'basic')
            ->setHelp('This command runs example files to demonstrate the functionality of the nova/fibers package.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $example = $input->getArgument('example');

        $io->title('Nova/Fibers Example Runner');

        // Determine the example file to run
        $exampleFile = match ($example) {
            'basic' => __DIR__ . '/../../examples/basic_usage_example.php',
            'advanced' => __DIR__ . '/../../examples/advanced_features_example.php',
            default => null,
        };

        // Check if the example file exists
        if (!$exampleFile || !file_exists($exampleFile)) {
            $io->error("Example '{$example}' not found.");
            return Command::FAILURE;
        }

        $io->text("Running {$example} example...");
        $io->newLine();

        // Run the example file
        try {
            // Start output buffering to capture any output from the example file
            ob_start();

            // Include the example file to execute it
            include $exampleFile;

            // Get the captured output
            $exampleOutput = ob_get_contents();

            // Clean the output buffer
            ob_end_clean();

            // Display the captured output
            if (!empty($exampleOutput)) {
                $io->writeln($exampleOutput);
            }

            $io->newLine();
            $io->success("Example '{$example}' completed successfully.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            // Clean the output buffer in case of exception
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $io->error("Error running example: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
