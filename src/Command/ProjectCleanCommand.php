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
        $result = $builder->clean($projectRoot, $input->getOption('keep'), $output);

        if (!$result[0]) {
            $output->writeln("There are no builds to delete");
        }
        else {
            if ($result[1]) {
                $output->writeln("Deleted <info>{$result[1]}</info> build(s)");
            }
            if ($result[2]) {
                $output->writeln("Kept <info>{$result[2]}</info> build(s)");
            }
        }

        $archivesResult = $builder->cleanArchives($projectRoot);
        if ($archivesResult[1]) {
            $output->writeln("Deleted <info>{$archivesResult[1]}</info> archive(s)");
        }
    }

}
