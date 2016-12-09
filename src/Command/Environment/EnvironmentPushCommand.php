<?php
namespace Platformsh\Cli\Command\Environment;

use Platformsh\Cli\Command\CommandBase;
use Platformsh\Cli\Exception\RootNotFoundException;
use Platformsh\Cli\Util\SshUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvironmentPushCommand extends CommandBase
{

    protected function configure()
    {
        $this
            ->setName('environment:push')
            ->setAliases(['push'])
            ->setDescription('Push code to an environment')
            ->addArgument('src', InputArgument::OPTIONAL, 'The source ref: a branch name or commit hash', 'HEAD')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Allow non-fast-forward updates')
            ->addOption('force-with-lease', null, InputOption::VALUE_NONE, 'Allow non-fast-forward updates, if the remote-tracking branch is up to date')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do everything except actually send the updates')
            ->addOption('no-wait', null, InputOption::VALUE_NONE, 'After pushing, do not wait for build or deploy');
        SshUtil::configureInput($this->getDefinition());
        $this->addProjectOption()
            ->addEnvironmentOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input, true);
        $projectRoot = $this->getProjectRoot();
        if (!$projectRoot) {
            throw new RootNotFoundException();
        }

        /** @var \Platformsh\Cli\Helper\GitHelper $gitHelper */
        $gitHelper = $this->getHelper('git');
        $gitHelper->setDefaultRepositoryDir($projectRoot);

        $source = $input->getArgument('src');

        if ($this->hasSelectedEnvironment()) {
            $target = $this->getSelectedEnvironment()->id;
        } elseif ($currentBranch = $gitHelper->getCurrentBranch()) {
            $target = $currentBranch;
        } else {
            $this->stdErr->writeln('Could not determine target environment name.');
            return 1;
        }

        /** @var \Platformsh\Cli\Helper\QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        if ($target === 'master'
            && !$questionHelper->confirm(
                'Are you sure you want to push to the <comment>master</comment> (production) branch?'
            )) {
            return 1;
        }

        if (strpos($source, ':') !== false) {
            $this->stdErr->writeln('Invalid ref: ' . $source);
            return 1;
        }

        $gitArgs = [
            'push',
            $this->getSelectedProject()->getGitUrl(),
            $source . ':' . $target,
        ];

        foreach (['force-with-lease', 'dry-run'] as $option) {
            if ($input->getOption($option)) {
                $gitArgs[] = ' --' . $option;
            }
        }

        $sshUtil = new SshUtil($input, $output);

        $extraSshOptions = [];
        $env = [];
        if ($input->getOption('no-wait')) {
            $extraSshOptions[] = 'SendEnv PLATFORMSH_PUSH_NO_WAIT';
            $env['PLATFORMSH_PUSH_NO_WAIT'] = '1';
        }

        if (!empty($extraSshOptions)) {
            $gitHelper->setSshCommand($sshUtil->getSshCommand($extraSshOptions));
        }

        return $gitHelper->execute($gitArgs, null, true, false, $env) ? 0 : 1;
    }
}
