<?php

namespace CommerceGuys\Platform\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvironmentCheckoutCommand extends EnvironmentCommand
{

    protected function configure()
    {
        $this
            ->setName('environment:checkout')
            ->setAliases(array('checkout'))
            ->setDescription('Checkout an environment.')
            ->addArgument(
                'environment',
                InputArgument::REQUIRED,
                'The environment to checkout. For example: "sprint2"'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environmentId = $input->getArgument('environment');
        $projectRoot = $this->getProjectRoot();
        if (empty($projectRoot)) {
            $output->writeln("<error>You must run this command from a project folder.</error>");
            return;
        }
        if (!$this->getEnvironment($environmentId)) {
            $output->writeln("<error>Environment not found: $environmentId</error>");
            return;
        }
        $repositoryDir = $projectRoot . '/repository';
        passthru("cd $repositoryDir && git fetch origin && git checkout $environmentId");
    }
}
