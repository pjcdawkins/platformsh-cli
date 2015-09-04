<?php
namespace Platformsh\Cli\Command;

use Platformsh\Cli\Exception\RootNotFoundException;
use Platformsh\Cli\Helper\GitHelper;
use Platformsh\Cli\Helper\ShellHelper;
use Platformsh\Cli\Local\LocalBuild;
use Platformsh\Cli\Local\LocalProject;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ForceRebuildCommand extends PlatformCommand
{

    protected function configure()
    {
        $this
          ->setName('force-rebuild')
          ->setDescription('Force rebuilding the application on Platform.sh')
          ->addArgument('app', InputArgument::IS_ARRAY, 'Specify application(s) to build')
          ->addOption('message', 'm', InputOption::VALUE_OPTIONAL, 'The commit message')
          ->addOption('no-push', 'P', InputOption::VALUE_NONE, 'Do not push the commit to Platform.sh');
        $this->setHelp("Platform.sh only rebuilds applications if their files have changed."
         . "\n\nThis command makes a dummy change to a <comment>.force-rebuild</comment> file in the application.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!($root = $this->getProjectRoot()) || !($environment = $this->getCurrentEnvironment())) {
            throw new RootNotFoundException();
        }

        $repoDir = $root . '/' . LocalProject::REPOSITORY_DIR;
        $appRoots = array();
        if ($specifiedApps = $input->getArgument('app')) {
            $builder = new LocalBuild();
            $apps = $builder->getApplications($repoDir);
            foreach ($apps as $identifier => $appRoot) {
                $appConfig = $builder->getAppConfig($appRoot);
                $appIdentifier = isset($appConfig['name']) ? $appConfig['name'] : $identifier;
                if (in_array($appIdentifier, $specifiedApps)) {
                    $appRoots[$appIdentifier] = $appRoot;
                    continue;
                }
            }
            if ($notFounds = array_diff($specifiedApps, array_keys($appRoots))) {
                foreach ($notFounds as $notFound) {
                    $this->stdErr->writeln("Application not found: <comment>$notFound</comment>");
                }
                return 1;
            }
        }
        else {
            $appRoots[] = $root . '/' . LocalProject::REPOSITORY_DIR;
        }

        $message = $input->getOption('message') ?: "Rebuild application\n\nCommitted using the Platform.sh CLI";

        $shellHelper = new ShellHelper($this->stdErr);
        $gitHelper = new GitHelper($shellHelper);
        $gitHelper->setDefaultRepositoryDir($repoDir);

        $result = $gitHelper->execute(array('diff-index', '--quiet', 'HEAD', '--'));
        if ($result !== true) {
            $this->stdErr->writeln("Cannot force rebuild: there are files already staged in the repository.");
            return 1;
        }

        /** @var \Platformsh\Cli\Helper\PlatformQuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        if (!$questionHelper->confirm("Make a new commit in the application?", $input, $this->stdErr)) {
            return 1;
        }

        foreach ($appRoots as $appRoot) {
            $this->makeFileChange($appRoot, '.force-rebuild');
            $gitHelper->execute(array('add', '-f', "$appRoot/.force-rebuild"), null, true, false);
        }

        $gitHelper->execute(array('commit', '-m', $message), null, true, false);

        if ($input->getOption('no-push') || !$questionHelper->confirm("Push to the 'platform' remote?", $input, $this->stdErr)) {
            return 0;
        }

        try {
            $gitHelper->execute(array('push', 'platform'), null, true, false);
        }
        catch (\Exception $e) {
            $this->stdErr->writeln("Failed to push the change to Platform.sh.");
            $this->stdErr->writeln("You could run '<comment>git pull --rebase</comment>' to ensure your repository is up-to-date.");
            return 1;
        }

        return 0;
    }

    /**
     * @param string $dir
     * @param string $basename
     */
    protected function makeFileChange($dir, $basename)
    {
        $filename = "$dir/$basename";
        $data = "1\n\n# This file is used by the Platform.sh CLI 'force-rebuild' command.\n";
        if (file_exists($filename)) {
            $data = file_get_contents($filename);
            $lines = explode("\n", $data, 2);
            $data = implode("\n", array($lines[0] + 1, $lines[1]));
        }
        file_put_contents($filename, $data);
    }
}
