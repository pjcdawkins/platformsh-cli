<?php

namespace CommerceGuys\Platform\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectCleanCommand extends PlatformCommand
{

    protected function configure()
    {
        $this->setName('project:clean')->setAliases(['clean'])->setDescription('Remove project builds.')->addOption(
            'number',
            'c',
            InputOption::VALUE_OPTIONAL,
            'Number of builds to keep.',
            5
          );
    }

    public function isLocal()
    {
        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectRoot = $this->getProjectRoot();
        if (empty($projectRoot)) {
            $output->writeln("<error>You must run this command from a project folder.</error>");

            return;
        }

        $buildsDir = $projectRoot . '/builds';
        if ($this->dir_empty($buildsDir)) {
            $output->writeln("<error>There are no builds to clean.</error>");

            return;
        }

        // Collect directories.
        $builds = [];
        $handle = opendir($buildsDir);
        while ($entry = readdir($handle)) {
            if (strpos($entry, '.') !== 0) {
                $builds[] = $entry;
            }
        }

        $count = count($builds);

        if (!$count) {
            $output->writeln("There are no builds to delete.");
            return;
        }

        // Remove old builds.
        sort($builds);
        $numDeleted = 0;
        $numKept = 0;
        $keep = (int) $input->getOption('keep');
        foreach ($builds as $build) {
            if ($count - $numDeleted > $keep) {
                $output->writeln("Deleting: $build");
                $this->getHelper('fs')->rmdir($projectRoot . '/builds/' . $build);
                $numDeleted++;
            }
            else {
                $numKept++;
            }
        }

        if ($numDeleted) {
            $output->writeln("Deleted <info>$numDeleted</info> build(s).");
        }

    /**
     * Check if directory contains files.
     *
     * @return boolean False if there are no files in directory.
     */
    private function dir_empty($dir)
    {
        if (!is_readable($dir)) {
            return true;
        }
        $handle = opendir($dir);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                return false;
            }
        }

        return true;
    }

}
