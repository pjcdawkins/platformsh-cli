<?php

namespace CommerceGuys\Platform\Cli\Command;

use CommerceGuys\Platform\Cli\Helper\ArgvHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GitCommand extends PlatformCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('git')
            ->addArgument('cmd', InputArgument::OPTIONAL, 'A Git command to run.', 'status')
            ->setDescription("Run a Git command in the project's repository");
        $this->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = $this->getProjectRoot();
        if (!$root) {
            throw new \Exception('This can only be run from inside a project directory');
        }

        $command = $input->getArgument('cmd');
        if ($input instanceof ArgvInput) {
            $helper = new ArgvHelper();
            $command = $helper->getPassedCommand($this, $input);
        }

        if (!$command) {
            $command = 'status';
        }

        $command = 'git ' . $command;

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln("Running command: <info>$command</info>");
        }

        chdir($root . '/repository');
        passthru($command, $returnVar);
        return $returnVar;
    }

}
