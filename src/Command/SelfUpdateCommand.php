<?php

namespace Platformsh\Cli\Command;

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends PlatformCommand
{
    protected function configure()
    {
        $this
          ->setName('self-update')
          ->setAliases(array('up'))
          ->setDescription('Update the CLI to the latest version')
          ->addOption('manifest', null, InputOption::VALUE_OPTIONAL, 'The manifest file location');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manifest = $input->getOption('manifest') ?: 'https://platform.sh/cli/manifest.json';

        $currentVersion = $this->getApplication()->getVersion();

        $manager = new Manager(Manifest::loadFile($manifest));
        $updated = $manager->update($this->getApplication()->getVersion(), true);
        if ($updated) {
            $newVersion = $this->getApplication()->getVersion();
            $this->stdErr->writeln("Successfully updated from <info>$currentVersion</info> to <info>$newVersion</info>");
        }
        else {
            $this->stdErr->writeln("No updates found. The Platform.sh CLI is up-to-date.");
        }
    }
}
