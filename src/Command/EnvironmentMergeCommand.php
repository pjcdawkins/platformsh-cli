<?php

namespace CommerceGuys\Platform\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvironmentMergeCommand extends EnvironmentCommand
{

    protected function configure()
    {
        $this
            ->setName('environment:merge')
            ->setAliases(array('merge'))
            ->setDescription('Merge an environment')
            ->addArgument('environment', InputArgument::OPTIONAL, 'The environment to merge');
        $this->addProjectOption()->addEnvironmentOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->validateInput($input, $output)) {
            return 1;
        }

        $environmentId = $this->environment['id'];

        if (!$this->operationAvailable('merge')) {
            $output->writeln("Operation not available: The environment <error>$environmentId</error> can't be merged.");
            return 1;
        }

        $parentId = $this->environment['parent'];

        if (!$this->getHelper('question')->confirm("Are you sure you want to merge <info>$environmentId</info> with its parent, <info>$parentId</info>?", $input, $output)) {
            return 0;
        }

        $client = $this->getPlatformClient($this->environment['endpoint']);
        $client->mergeEnvironment();
        // Reload the stored environments.
        $this->getEnvironments($this->project, true);

        $output->writeln("The environment <info>$environmentId</info> has been merged with <info>$parentId</info>.");
        return 0;
    }
}
