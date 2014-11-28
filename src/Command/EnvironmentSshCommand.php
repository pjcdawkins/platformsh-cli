<?php

namespace CommerceGuys\Platform\Cli\Command;

use CommerceGuys\Platform\Cli\Model\Environment;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class EnvironmentSshCommand extends PlatformCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('environment:ssh')
            ->setAliases(array('ssh'))
            ->addArgument('cmd', InputArgument::OPTIONAL, 'A command to run on the environment')
            ->addOption('project', null, InputOption::VALUE_OPTIONAL, 'The project ID')
            ->addOption('environment', null, InputOption::VALUE_OPTIONAL, 'The environment ID')
            ->addOption('pipe', NULL, InputOption::VALUE_NONE, "Output the SSH URL only")
            ->setDescription('SSH to the current environment');

        // Skip validation so that any command can be passed through to SSH.
        $this->ignoreValidationErrors();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->validateInput($input, $output)) {
            return 1;
        }

        $environment = new Environment($this->environment);
        $sshUrl = $environment->getSshUrl();

        if ($input->getOption('pipe') || !$this->isTerminal($output)) {
            $output->write($sshUrl);
            return 0;
        }

        $remoteCommand = $this->getRemoteSshCommand($input);
        if ($remoteCommand) {
            $command = "ssh -qt $sshUrl $remoteCommand";
        }
        else {
            $command = "ssh " . escapeshellarg($sshUrl);
        }

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $output->writeln("Running command: $command");
        }

        passthru($command, $returnVar);
        return $returnVar;
    }

    protected function getSshOptions()
    {

    }

    /**
     * @param InputInterface $input
     *
     * @return string|bool
     */
    protected function getRemoteSshCommand(InputInterface $input)
    {
        if (!$input instanceof ArgvInput) {
            return false;
        }
        $tokens = $_SERVER['argv'];
        // Strip out the application name.
        array_shift($tokens);
        $args = array();
        $seenFirstArgument = false;
        foreach ($tokens as $token) {
            // Ignore everything before 'ssh'.
            if ($input->getFirstArgument() === $token) {
                $seenFirstArgument = true;
                continue;
            }
            elseif (!$seenFirstArgument) {
                continue;
            }
            $args[] = $input->escapeToken($token);
        }
        $command = implode(' ', $args);
        return $command;
    }

    /**
     * Check whether a command-line option is valid for SSH.
     *
     * @param string $option
     *
     * @return bool
     */
    protected function isSshOption($option)
    {
        $pattern = '/^\-[1246AaCfgKkMNnqsTtVvXxYy]$/';
        return (bool) preg_match($pattern, $option);
    }

}
