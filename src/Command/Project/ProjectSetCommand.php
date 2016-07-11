<?php
namespace Platformsh\Cli\Command\Project;

use Platformsh\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectSetCommand extends CommandBase
{

    protected function configure()
    {
        $this
            ->setName('project:set')
            ->setDescription('Set the project for further commands in this environment')
            ->addArgument('project', InputArgument::OPTIONAL, 'The project ID')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, "The project's API hostname");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $current = $this->getShellProject();
        $projectOption = $input->getArgument('project');
        if ($projectOption === 'none' || $projectOption === null) {
            if (!$current || self::$config->putShellTmp(null)) {
                $this->stdErr->writeln('No project is set for this session');

                return 0;
            }

            return 1;
        }

        $result = $this->parseProjectId($projectOption);
        $hostOption = $input->getOption('host') ?: $result['host'];
        $project = $this->api()->getProject($result['projectId'], $hostOption, true);
        if (!$project) {
            $this->stdErr->writeln("Project not found: <error>$projectOption</error>");
            self::$config->putShellTmp(null);

            return 1;
        }

        if ($current->id === $project->id || self::$config->putShellTmp($project->getUri())) {
            $this->stdErr->writeln('Default project set for this shell session: <info>' . $project->id . '</info>');
            $this->stdErr->writeln('');
            $this->stdErr->writeln('Projects are selected in this order of precedence:');
            $this->stdErr->writeln('  1. The --project or -p option');
            $this->stdErr->writeln('  2. The current directory');
            $this->stdErr->writeln('  3. The shell session default');

            return 0;
        }

        return 1;
    }
}
