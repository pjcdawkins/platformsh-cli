<?php

namespace CommerceGuys\Platform\Cli\Command;

use CommerceGuys\Platform\Cli\Local\LocalBuild;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectCleanCommand extends PlatformCommand
{

    protected function configure()
    {
        $this
            ->setName('project:clean')
            ->setAliases(array('clean'))
            ->setDescription('Remove old project builds')
            ->addOption(
                'keep',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of builds to keep.',
                5
            );
    }

    public function isLocal() {
        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectRoot = $this->getProjectRoot();
        if (empty($projectRoot)) {
            $output->writeln("<error>You must run this command from a project folder.</error>");
            return;
        }

        $builder = new LocalBuild(array());
        $builder->clean($projectRoot, $input->getOption('keep'), $output);
    }

}
