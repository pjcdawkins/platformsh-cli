<?php

namespace Platformsh\Cli\Command;

use Platformsh\Cli\Service\ActivityMonitor;
use Platformsh\Client\Model\Environment;
use Platformsh\Client\Model\Project;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WelcomeCommand extends CommandBase
{
    protected $hiddenInList = true;
    protected $local = true;

    protected function configure()
    {
        $this
            ->setName('welcome')
            ->setDescription('Welcome to ' . $this->config()->get('service.name'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->stdErr->writeln("Welcome to " . $this->config()->get('service.name') . "!\n");

        $envPrefix = $this->config()->get('service.env_prefix');
        $onContainer = getenv($envPrefix . 'PROJECT') && getenv($envPrefix . 'BRANCH');
        $executable = $this->config()->get('application.executable');

        if ($project = $this->getCurrentProject()) {
            $this->welcomeForLocalProjectDir($project);
        } elseif ($onContainer) {
            $this->welcomeOnContainer();
        } else {
            $this->defaultWelcome();

            if ($this->api()->isLoggedIn()) {
                $this->stdErr->writeln("Manage your SSH keys by running <info>$executable ssh-keys</info>");
            }
        }

        $this->stdErr->writeln("\nTo view all commands, run: <info>$executable list</info>");
    }

    /**
     * Display default welcome message, when not in a project directory.
     */
    private function defaultWelcome()
    {
        // The project is not known. Show all projects.
        $this->runOtherCommand('projects', ['--refresh' => 0]);
        $this->stdErr->writeln('');
    }

    /**
     * Display welcome for a local project directory.
     *
     * @param \Platformsh\Client\Model\Project $project
     */
    private function welcomeForLocalProjectDir(Project $project)
    {
        $this->stdErr->writeln(sprintf('Project: %s', $this->api()->getProjectLabel($project)));

        if ($environment = $this->getCurrentEnvironment($project)) {
            $this->welcomeForCurrentEnvironment($project, $environment);
        } else {
            $this->stdErr->writeln(sprintf("Dashboard: <info>%s</info>", $project->getLink('#ui')));

            // Show the environments.
            $this->runOtherCommand('environments', [
                '--refresh' => 0,
                '--project' => $project->id,
            ]);

            $this->stdErr->writeln('');
        }
        $executable = $this->config()->get('application.executable');
        $this->stdErr->writeln("List other projects by running <info>$executable pro</info>");
    }

    /**
     * Display welcome when a current environment is selected.
     *
     * @param Project $project
     * @param Environment $environment
     */
    private function welcomeForCurrentEnvironment(Project $project, Environment $environment)
    {
        $url = $project->getLink('#ui');
        // Console links lack the /environments path component.
        if ($this->config()->has('detection.console_domain') && parse_url($url, PHP_URL_HOST) === $this->config()->get('detection.console_domain')) {
            $url .= '/' . rawurlencode($environment->id);
        } else {
            $url .= '/environments/' . rawurlencode($environment->id);
        }

        $this->stdErr->writeln(sprintf('Current environment: %s', $this->api()->getEnvironmentLabel($environment)));
        $this->stdErr->writeln(sprintf('Dashboard: <info>%s</info>', $url));
        $this->stdErr->writeln('');

        /** @var \Platformsh\Cli\Service\ActivityLoader $loader */
        $loader = $this->getService('activity_loader');
        $activities = $loader->load($environment, 5, null, null, true);
        $executable = $this->config()->get('application.executable');
        if ($activities) {
            /** @var \Platformsh\Cli\Service\Table $table */
            $table = $this->getService('table');
            /** @var \Platformsh\Cli\Service\PropertyFormatter $formatter */
            $formatter = $this->getService('property_formatter');

            $headers = ['Date & time', 'Description', 'State'];

            $this->stdErr->writeln(sprintf('Recent activities, in ascending order:'));

            $rows = [];
            foreach ($activities as $activity) {
                $date = $activity['updated_at'] ? $activity['updated_at'] : $activity['created_at'];
                $rows[] = [
                    $formatter->format($date, 'updated_at'),
                    ActivityMonitor::getFormattedDescription($activity, !$table->formatIsMachineReadable()),
                    $activity->isComplete() ? ActivityMonitor::formatResult($activity->result) : ActivityMonitor::formatState($activity->state),
                ];
            }

            $table->render($rows, $headers);

            $this->stdErr->writeln("\nList other activities by running <info>$executable act</info>");
        }

        $this->stdErr->writeln("List other environments by running <info>$executable env</info>");
    }

    /**
     * Warn the user if a project is suspended.
     *
     * @param \Platformsh\Client\Model\Project $project
     */
    private function warnIfSuspended(Project $project)
    {
        if ($project->isSuspended()) {
            $messages = [];
            $messages[] = '<comment>This project is suspended.</comment>';
            if ($project->owner === $this->api()->getMyAccount()['id']) {
                $messages[] = '<comment>Update your payment details to re-activate it: '
                    . $this->config()->get('service.accounts_url')
                    . '</comment>';
            }
            $messages[] = '';
            $this->stdErr->writeln($messages);
        }
    }

    /**
     * Display welcome when the user is in a cloud container environment.
     */
    private function welcomeOnContainer()
    {
        $envPrefix = $this->config()->get('service.env_prefix');
        $executable = $this->config()->get('application.executable');

        $projectId = getenv($envPrefix . 'PROJECT');
        $environmentId = getenv($envPrefix . 'BRANCH');
        $appName = getenv($envPrefix . 'APPLICATION_NAME');

        $project = false;
        $environment = false;
        if ($this->api()->isLoggedIn()) {
            $project = $this->api()->getProject($projectId);
            if ($project && $environmentId) {
                $environment = $this->api()->getEnvironment($environmentId, $project);
            }
        }

        if ($project) {
            $this->stdErr->writeln('Project: ' . $this->api()->getProjectLabel($project));
            if ($environment) {
                $this->stdErr->writeln('Environment: ' . $this->api()->getEnvironmentLabel($environment));
            }
            if ($appName) {
                $this->stdErr->writeln('Application name: <info>' . $appName . '</info>');
            }

            $this->warnIfSuspended($project);
        } else {
            $this->stdErr->writeln('Project ID: <info>' . $projectId . '</info>');
            if ($environmentId) {
                $this->stdErr->writeln('Environment ID: <info>' . $environmentId . '</info>');
            }
            if ($appName) {
                $this->stdErr->writeln('Application name: <info>' . $appName . '</info>');
            }
        }

        $this->stdErr->writeln('');
        $examples = [];
        if (getenv($envPrefix . 'APPLICATION')) {
            $examples[] = "To view application config, run: <info>$executable app:config</info>";
        }
        if (getenv($envPrefix . 'RELATIONSHIPS')) {
            $examples[] = "To view relationships, run: <info>$executable relationships</info>";
        }
        if (getenv($envPrefix . 'ROUTES')) {
            $examples[] = "To view routes, run: <info>$executable routes</info>";
        }
        if (getenv($envPrefix . 'VARIABLES')) {
            $examples[] = "To view variables, run: <info>$executable decode \${$envPrefix}VARIABLES</info>";
        }
        if (!empty($examples)) {
            $this->stdErr->writeln('Local environment commands:');
            $this->stdErr->writeln('');
            $this->stdErr->writeln(preg_replace('/^/m', '  ', implode("\n", $examples)));
            $this->stdErr->writeln('');
        }
    }
}
