<?php
namespace Platformsh\Cli\Command\Project;

use Cocur\Slugify\Slugify;
use Platformsh\Cli\Command\CommandBase;
use Platformsh\Cli\Helper\GitHelper;
use Platformsh\Cli\Helper\ShellHelper;
use Platformsh\Cli\Local\LocalBuild;
use Platformsh\Cli\Local\LocalProject;
use Platformsh\Cli\Local\Toolstack\Drupal;
use Platformsh\Client\Model\Environment;
use Platformsh\Client\Model\Project;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectGetCommand extends CommandBase
{

    protected function configure()
    {
        $this
          ->setName('project:get')
          ->setAliases(array('get'))
          ->setDescription('Clone and build a project locally')
          ->addArgument(
            'id',
            InputArgument::OPTIONAL,
            'The project ID'
          )
          ->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            'The directory to clone to. Defaults to the project title'
          )
          ->addOption(
            'environment',
            'e',
            InputOption::VALUE_REQUIRED,
            "The environment ID to clone. Defaults to 'master'"
          )
          ->addOption(
            'no-build',
            null,
            InputOption::VALUE_NONE,
            "Do not build the retrieved project"
          )
          ->addOption(
            'host',
            null,
            InputOption::VALUE_REQUIRED,
            "The project's API hostname"
          );
        $this->addExample('Clone the project "abc123" into the directory "my-project"', 'abc123 my-project');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectId = $input->getArgument('id');
        if (empty($projectId)) {
            if ($input->isInteractive() && ($projects = $this->getProjects(true))) {
                $projectId = $this->offerProjectChoice($projects, $input);
            } else {
                $this->stdErr->writeln("<error>You must specify a project.</error>");

                return 1;
            }
        }
        $project = $this->getProject($projectId, $input->getOption('host'), true);
        if (!$project) {
            $this->stdErr->writeln("<error>Project not found: $projectId</error>");

            return 1;
        }

        /** @var \Platformsh\Cli\Helper\PlatformQuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $directory = $input->getArgument('directory');
        if (empty($directory)) {
            $slugify = new Slugify();
            $directory = $project->title ? $slugify->slugify($project->title) : $project->id;
            $directory = $questionHelper->askInput('Directory', $input, $this->stdErr, $directory);
        }

        if ($projectRoot = $this->getProjectRoot()) {
            if (strpos(realpath(dirname($directory)), $projectRoot) === 0) {
                $this->stdErr->writeln("<error>A project cannot be cloned inside another project.</error>");

                return 1;
            }
        }

        /** @var \Platformsh\Cli\Helper\FilesystemHelper $fsHelper */
        $fsHelper = $this->getHelper('fs');

        // Create the directory structure.
        $existed = false;
        if (file_exists($directory)) {
            $existed = true;
            $this->stdErr->writeln("The directory <error>$directory</error> already exists");
            if (file_exists($directory . '/' . LocalProject::PROJECT_CONFIG) && $questionHelper->confirm("Overwrite?", $input, $this->stdErr, false)) {
                $fsHelper->remove($directory);
            }
            else {
                return 1;
            }
        }
        mkdir($directory);
        $projectRoot = realpath($directory);
        if (!$projectRoot) {
            throw new \Exception("Failed to create project directory: $directory");
        }

        if ($existed) {
            $this->stdErr->writeln("Re-created project directory: <info>$directory</info>");
        }
        else {
            $this->stdErr->writeln("Created new project directory: <info>$directory</info>");
        }

        $local = new LocalProject();
        $hostname = parse_url($project->getUri(), PHP_URL_HOST) ?: null;
        $local->createProjectFiles($projectRoot, $project->id, $hostname);

        $environments = $this->getEnvironments($project, true);

        $environmentOption = $input->getOption('environment');
        if ($environmentOption) {
            if (!isset($environments[$environmentOption])) {
                $this->stdErr->writeln("Environment not found: <error>$environmentOption</error>");

                return 1;
            }
            $environment = $environmentOption;
        } elseif (count($environments) === 1) {
            $environment = key($environments);
        } elseif ($environments && $input->isInteractive()) {
            $environment = $this->offerEnvironmentChoice($environments, $input);
        } else {
            $environment = 'master';
        }

        // Prepare to talk to the Platform.sh repository.
        $gitUrl = $project->getGitUrl();
        $repositoryDir = $projectRoot . '/' . LocalProject::REPOSITORY_DIR;

        $gitHelper = new GitHelper(new ShellHelper($this->stdErr));
        $gitHelper->ensureInstalled();

        // First check if the repo actually exists.
        $repoHead = $gitHelper->execute(array('ls-remote', $gitUrl, 'HEAD'), false);
        if ($repoHead === false) {
            // The ls-remote command failed.
            $fsHelper->rmdir($projectRoot);
            $this->stdErr->writeln('<error>Failed to connect to the Platform.sh Git server</error>');

            // Suggest SSH key commands.
            $sshKeys = [];
            try {
                $sshKeys = $this->getClient(false)->getSshKeys();
            }
            catch (\Exception $e) {
                // Ignore exceptions.
            }

            if (!empty($sshKeys)) {
                $this->stdErr->writeln('');
                $this->stdErr->writeln('Please check your SSH credentials');
                $this->stdErr->writeln('You can list your keys with: <comment>platform ssh-keys</comment>');
            }
            else {
                $this->stdErr->writeln('You probably need to add an SSH key, with: <comment>platform ssh-key:add</comment>');
            }

            return 1;
        } elseif (is_bool($repoHead)) {
            // The repository doesn't have a HEAD, which means it is empty.
            // We need to create the folder, run git init, and attach the remote.
            mkdir($repositoryDir);
            // Initialize the repo and attach our remotes.
            $this->stdErr->writeln("Initializing empty project repository");
            $gitHelper->execute(array('init'), $repositoryDir, true);
            $this->stdErr->writeln("Adding Platform.sh Git remote");
            $local->ensureGitRemote($repositoryDir, $gitUrl);
            $this->stdErr->writeln("Your repository has been initialized and connected to <info>Platform.sh</info>!");
            $this->stdErr->writeln(
              "Commit and push to the <info>$environment</info> branch and Platform.sh will build your project automatically"
            );

            return 0;
        }

        // We have a repo! Yay. Clone it.
        $cloneArgs = array('--branch', $environment, '--origin', 'platform');
        $cloned = $gitHelper->cloneRepo($gitUrl, $repositoryDir, $cloneArgs);
        if (!$cloned) {
            // The clone wasn't successful. Clean up the folders we created
            // and then bow out with a message.
            $fsHelper->rmdir($projectRoot);
            $this->stdErr->writeln('<error>Failed to clone Git repository</error>');
            $this->stdErr->writeln('Please check your SSH credentials or contact Platform.sh support');

            return 1;
        }

        $local->ensureGitRemote($repositoryDir, $gitUrl);
        $this->setProjectRoot($projectRoot);

        $this->stdErr->writeln('');
        $this->stdErr->writeln("The project <info>{$project->title}</info> was successfully downloaded to: <info>$directory</info>");

        // Ensure that Drush aliases are created.
        if (Drupal::isDrupal($projectRoot . '/' . LocalProject::REPOSITORY_DIR)) {
            $this->stdErr->writeln('');
            $this->runOtherCommand('local:drush-aliases', array(
              // The default Drush alias group is the final part of the
              // directory path.
              '--group' => basename($directory),
            ), $input);
        }

        // Allow the build to be skipped.
        if ($input->getOption('no-build')) {
            return 0;
        }

        // Always skip the build if the cloned repository is empty ('.', '..',
        // '.git' being the only found items)
        if (count(scandir($repositoryDir)) <= 3) {
            return 0;
        }

        // Launch the first build.
        $this->stdErr->writeln('');
        $this->stdErr->writeln('Building the project locally for the first time. Run <info>platform build</info> to repeat this.');
        $builder = new LocalBuild(array('environmentId' => $environment), $output);
        $success = $builder->buildProject($projectRoot);

        return $success ? 0 : 1;
    }

    /**
     * @param Environment[]   $environments
     * @param InputInterface  $input
     *
     * @return string
     *   The chosen environment ID.
     */
    protected function offerEnvironmentChoice(array $environments, InputInterface $input)
    {
        // Create a list starting with "master".
        $default = 'master';
        $environmentList = array($default => $environments[$default]->title);
        foreach ($environments as $environment) {
            $id = $environment->id;
            if ($id != $default) {
                $environmentList[$id] = $environment->title;
            }
        }
        if (count($environmentList) === 1) {
            return key($environmentList);
        }

        $text = "Enter a number to choose which environment to check out:";

        return $this->getHelper('question')
                    ->choose($environmentList, $text, $input, $this->stdErr, $default);
    }

    /**
     * @param Project[]       $projects
     * @param InputInterface  $input
     *
     * @return string
     *   The chosen project ID.
     */
    protected function offerProjectChoice(array $projects, InputInterface $input)
    {
        $projectList = array();
        foreach ($projects as $project) {
            $projectList[$project->id] = $project->id . ' (' . $project->title . ')';
        }
        $text = "Enter a number to choose which project to clone:";

        return $this->getHelper('question')
                    ->choose($projectList, $text, $input, $this->stdErr);
    }

}
