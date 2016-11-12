<?php

namespace Platformsh\Cli\Command;

use Platformsh\Cli\Exception\RootNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LegacyMigrateCommand extends CommandBase
{

    protected function configure()
    {
        $this
            ->setName('legacy-migrate')
            ->setDescription('Migrate from the legacy file structure')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Do not create a backup of the project.');
        $cliName = self::$config->get('application.name');
        $localDir = self::$config->get('local.local_dir');
        $this->setHelp(<<<EOF
Before version 3.x, the {$cliName} required a project to have a "repository"
directory containing the Git repository, "builds", "shared" and others. From
version 3, the Git repository itself is treated as the project. Metadata is
stored inside the repository (in {$localDir}) and ignored by Git.

This command will migrate from the old file structure to the new one.
EOF
        );
    }

    public function isHiddenInList()
    {
        return $this->localProject->getLegacyProjectRoot() ? false : true;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $legacyRoot = $this->localProject->getLegacyProjectRoot();
        if (!$legacyRoot) {
            if ($this->getProjectRoot()) {
                $this->stdErr->writeln('This project is already compatible with the ' . self::$config->get('application.name') . ' version 3.x.');

                return 0;
            }
            throw new RootNotFoundException();
        }

        $cwd = getcwd();

        /** @var \Platformsh\Cli\Service\FilesystemHelper $fsHelper */
        $fsHelper = $this->getHelper('fs');

        $repositoryDir = $legacyRoot . '/repository';
        if (!is_dir($repositoryDir)) {
            $this->stdErr->writeln('Directory not found: <error>' . $repositoryDir . '</error>');

            return 1;
        }
        elseif (!is_dir($repositoryDir . '/.git')) {
            $this->stdErr->writeln('Not a Git repository: <error>' . $repositoryDir . '</error>');

            return 1;
        }

        if (!$input->getOption('no-backup')) {
            $backup = rtrim($legacyRoot, '\\/') . '-backup.tar.gz';
            if (file_exists($backup)) {
                $this->stdErr->writeln('Backup destination already exists: <error>' . $backup . '</error>');
                $this->stdErr->writeln('Move (or delete) the backup, then run <comment>' . self::$config->get('application.executable') . ' legacy-migrate</comment> to continue.');

                return 1;
            }

            $this->stdErr->writeln('Backing up entire project to: ' . $backup);
            $fsHelper->archiveDir($legacyRoot, $backup);
        }

        $this->stdErr->writeln('Creating directory: ' . self::$config->get('local.local_dir'));
        $this->localProject->ensureLocalDir($repositoryDir);

        if (file_exists($legacyRoot . '/shared')) {
            $this->stdErr->writeln('Moving "shared" directory.');
            if (is_dir($repositoryDir . '/' . self::$config->get('local.shared_dir'))) {
                $fsHelper->copyAll($legacyRoot . '/shared', $repositoryDir . '/' . self::$config->get('local.shared_dir'));
                $fsHelper->remove($legacyRoot . '/shared');
            }
            else {
                rename($legacyRoot . '/shared', $repositoryDir . '/' . self::$config->get('local.shared_dir'));
            }
        }

        if (file_exists($legacyRoot . '/' . self::$config->get('local.project_config_legacy'))) {
            $this->stdErr->writeln('Moving project config file.');
            $fsHelper->copy($legacyRoot . '/' . self::$config->get('local.project_config_legacy'), $legacyRoot . '/' . self::$config->get('local.project_config'));
            $fsHelper->remove($legacyRoot . '/' . self::$config->get('local.project_config_legacy'));
        }

        if (file_exists($legacyRoot . '/.build-archives')) {
            $this->stdErr->writeln('Removing old build archives.');
            $fsHelper->remove($legacyRoot . '/.build-archives');
        }

        if (file_exists($legacyRoot . '/builds')) {
            $this->stdErr->writeln('Removing old builds.');
            $fsHelper->remove($legacyRoot . '/builds');
        }

        if (is_link($legacyRoot . '/www')) {
            $this->stdErr->writeln('Removing old "www" symlink.');
            $fsHelper->remove($legacyRoot . '/www');
            $this->stdErr->writeln('');
            $this->stdErr->writeln('After running <comment>' . self::$config->get('application.executable') . ' build</comment>, your web root will be at: <comment>' . self::$config->get('local.web_root') . '</comment>');
            $this->stdErr->writeln('You may need to update your local web server configuration.');
            $this->stdErr->writeln('');
        }

        $this->stdErr->writeln('Moving repository to be the new project root (this could take some time)...');
        $fsHelper->copyAll($repositoryDir, $legacyRoot, [], true);
        $fsHelper->remove($repositoryDir);

        if (!is_dir($legacyRoot . '/.git')) {
            $this->stdErr->writeln('Error: not found: <error>' . $legacyRoot . '/.git</error>');

            return 1;
        }
        elseif (file_exists($legacyRoot . '/' . self::$config->get('local.project_config_legacy'))) {
            $this->stdErr->writeln('Error: file still exists: <error>' . $legacyRoot . '/' . self::$config->get('local.project_config_legacy') . '</error>');

            return 1;
        }

        $this->stdErr->writeln("\n<info>Migration complete</info>\n");

        if (strpos($cwd, $repositoryDir) === 0) {
            $this->stdErr->writeln('Type this to refresh your shell:');
            $this->stdErr->writeln('    <comment>cd ' . $legacyRoot . '</comment>');
        }

        return 0;
    }
}
