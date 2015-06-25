<?php

namespace Platformsh\Cli\Command;

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends Command
{
    protected function configure()
    {
        $this
          ->setName('self-update')
          ->setDescription('Updates platform.phar to the latest version')
          ->addOption('manifest', null, InputOption::VALUE_OPTIONAL, 'The manifest file location');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manifest = $input->getOption('manifest') ?: 'https://platform.sh/cli/manifest.json';

        $manager = new Manager(Manifest::loadFile($manifest));
        $manager->update($this->getApplication()->getVersion(), true);
    }
}
