<?php

namespace Platformsh\Cli\Command\Project;

use Platformsh\Cli\Command\PlatformCommand;
use Platformsh\Client\Model\Subscription;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectCreateCommand extends PlatformCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
          ->setName('project:create')
          ->setDescription('Create a new project')
          ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'The project title')
          ->addOption('region', null, InputOption::VALUE_OPTIONAL, 'The project region')
          ->addOption('plan', null, InputOption::VALUE_OPTIONAL, 'The subscription plan');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \Platformsh\Cli\Helper\PlatformQuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        if (!$title = $input->getOption('title')) {
            $title = $questionHelper->askInput("Project title", $input, $this->stdErr);
        }
        if (!$region = $input->getOption('region')) {
            $regionKey = $questionHelper->choose(Subscription::$availableRegions, 'Enter a number to choose a region:', $input, $this->stdErr);
            $region = Subscription::$availableRegions[$regionKey];
        }
        if (!$plan = $input->getOption('plan')) {
            $planKey = $questionHelper->choose(Subscription::$availablePlans, 'Enter a number to choose a plan:', $input, $this->stdErr);
            $plan = Subscription::$availablePlans[$planKey];
        }
        $storage = $questionHelper->askInput("Storage per environment (GiB)", $input, $this->stdErr, 5, function ($value) {
            if (!is_int($value) || $value < 1 || $value > 1024) {
                throw new \RuntimeException("Invalid storage: $value");
            }

            return $value;
        });
        $environments = $questionHelper->askInput("Environments", $input, $this->stdErr, 3, function ($value) {
            if (!is_int($value) || $value < 1 || $value > 50) {
                throw new \RuntimeException("Invalid number of environments: $value");
            }

            return $value;
        });

        $client = $this->getClient();
        $subscription = $client->createSubscription($region, $plan, $title, $storage * 1024, $environments);

        $this->clearProjectsCache();

        $this->stdErr->writeln("Created subscription <comment>{$subscription->id}</comment>");
        $this->stdErr->writeln("Activating subscription");

        $progress = new ProgressBar($this->stdErr);
        $subscription->wait(function () use ($progress) {
            $progress->advance();
        });
        $progress->finish();
        // The progress bar needs a blank line.
        $this->stdErr->writeln("");

        if (!$subscription->isActive()) {
            $this->stdErr->writeln("<error>The subscription failed to activate</error>");
            return 1;
        }

        $this->stdErr->writeln("The project is now ready");
        $this->stdErr->writeln("  Region: <info>{$subscription->project_region}</info>");
        $this->stdErr->writeln("  Project ID: <info>{$subscription->project_id}</info>");
        $this->stdErr->writeln("  Project title: <info>{$subscription->project_title}</info>");
        $this->stdErr->writeln("  URL: <info>{$subscription->project_ui}</info>");
        return 0;
    }
}
