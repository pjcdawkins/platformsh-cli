<?php

namespace Platformsh\Cli\Command;

use Humbug\SelfUpdate\Updater;
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
          ->addOption('manifest', null, InputOption::VALUE_OPTIONAL, 'The manifest file location')
          ->addOption('no-key', null, InputOption::VALUE_NONE, 'Skip key verification');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manifest = $input->getOption('manifest') ?: 'https://platform.sh/cli/manifest.json';

        $currentVersion = $this->getApplication()->getVersion();

        $localPhar = \Phar::running(false);
        if (!$localPhar) {
            $this->stdErr->writeln('This instance of the CLI was not installed as a Phar archive.');
            return 1;
        }

        // Use the GitHub / Packagist strategy.
        $hasPubKey = !$input->getOption('no-key');
        $updater = new Updater($localPhar, $hasPubKey);
        $updater->setStrategy(Updater::STRATEGY_GITHUB);
        /** @var \Humbug\SelfUpdate\Strategy\GithubStrategy $strategy */
        $strategy = $updater->getStrategy();
        $strategy->setPackageName('platformsh/cli');
        $strategy->setPharName('platform.phar');
        $strategy->setCurrentLocalVersion($currentVersion);

        $updated = $updater->update();
        if ($updated) {
            $newVersion = $updater->getNewVersion();
            $this->stdErr->writeln("Successfully updated from <info>$currentVersion</info> to <info>$newVersion</info>");
        }
        else {
            $this->stdErr->writeln("No updates found. The Platform.sh CLI is up-to-date.");
        }
    }
}
