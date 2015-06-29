<?php

namespace Platformsh\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SelfBuildCommand extends PlatformCommand
{
    protected function configure()
    {
        $this
          ->setName('self-build')
          ->setDescription('Build a new package of the CLI')
          ->addOption('manifest', null, InputOption::VALUE_OPTIONAL, 'The manifest file to update')
          ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'The URL where the package will be published');
        $this->setHiddenInList();
    }

    public function isLocal()
    {
        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manifestFilename = $input->getOption('manifest');
        if ($manifestFilename) {
            if (!is_writable(dirname($manifestFilename))) {
                $this->stdErr->writeln("Not writable: <info>$manifestFilename</info>");
                return 1;
            }
        }

        /** @var \Platformsh\Cli\Helper\ShellHelper $shellHelper */
        $shellHelper = $this->getHelper('shell');
        if (!$shellHelper->commandExists('box')) {
            $this->stdErr->writeln('Command not found: <error>box</error>');
            $this->stdErr->writeln('The Box utility is required to build new CLI packages. Try:');
            $this->stdErr->writeln('  composer global require kherge/box:~2.5');
            return 1;
        }

        $phar = CLI_ROOT . '/platform.phar';

        if (file_exists($phar)) {
            /** @var \Platformsh\Cli\Helper\PlatformQuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            if (!$questionHelper->confirm("File exists: <comment>$phar</comment>. Overwrite?", $input, $this->stdErr)) {
                return 1;
            }
        }

        $this->stdErr->writeln("Building Phar package using Box: $phar");

        $shellHelper->setOutput($output);
        $shellHelper->execute(array('box', 'build'), CLI_ROOT, true, true);

        if (!file_exists($phar)) {
            $this->stdErr->writeln("File not found: <error>$phar</error>");
            return 1;
        }

        $sha1 = sha1_file($phar);
        $version = $this->getApplication()->getVersion();

        $this->stdErr->writeln("Package built: <info>$phar</info>");
        $this->stdErr->writeln("SHA1: $sha1");
        $this->stdErr->writeln("Version: $version");
        if (!$manifestFilename) {
            return 0;
        }

        $manifest = array();
        if (file_exists($manifestFilename)) {
            $manifest = json_decode(file_get_contents($manifestFilename), true);
        }

        $manifestItem = array(
          'name' => 'platform.phar',
          'sha1' => $sha1,
          'version' => $version,
        );
        if ($url = $input->getOption('url')) {
            $manifestItem['url'] = str_replace('{version}', $version, $url);
        }
        array_unshift($manifest, $manifestItem);

        $success = file_put_contents($manifestFilename, json_encode($manifest, JSON_PRETTY_PRINT));
        if ($success) {
            $this->stdErr->writeln("Manifest file updated: <info>$manifestFilename</info>");
            return 0;
        }
        else {
            $this->stdErr->writeln("Failed to update manifest: <error>$manifestFilename</error>");
            return 1;
        }
    }
}
