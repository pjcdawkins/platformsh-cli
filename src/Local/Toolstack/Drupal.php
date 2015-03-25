<?php

namespace Platformsh\Cli\Local\Toolstack;

use Platformsh\Cli\Helper\DrushHelper;
use Platformsh\Cli\Local\LocalProject;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class Drupal extends ToolstackBase
{

    protected $drushFlags = array();

    public function getKey()
    {
        return 'php:drupal';
    }

    /**
     * Detect if there are any Drupal applications in a folder.
     *
     * @param string $directory
     * @param mixed  $depth
     *
     * @return bool
     */
    public static function isDrupal($directory, $depth = '< 2')
    {
        $finder = new Finder();

        // Look for at least one Drush make file.
        $finder->in($directory)
               ->files()
               ->depth($depth)
               ->name('project.make')
               ->name('project-core.make')
               ->name('drupal-org.make')
               ->name('drupal-org-core.make');
        foreach ($finder as $file) {
            return true;
        }

        // Check whether there is an index.php file whose first few lines
        // contain the word "Drupal".
        $finder->in($directory)
               ->files()
               ->depth($depth)
               ->name('index.php');
        foreach ($finder as $file) {
            $f = fopen($file, 'r');
            $beginning = fread($f, 3178);
            fclose($f);
            if (strpos($beginning, 'Drupal') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detect($appRoot)
    {
        return self::isDrupal($appRoot, 0);
    }

    public function build()
    {
        $this->setUpDrushFlags();

        $profiles = glob($this->appRoot . '/*.profile');
        if (count($profiles) > 1) {
            throw new \Exception("Found multiple files ending in '*.profile' in the directory.");
        } elseif (count($profiles) == 1) {
            $profileName = strtok(basename($profiles[0]), '.');
            $this->buildInProfileMode($profileName);
        }
        elseif (file_exists($this->appRoot . '/project.make')) {
            $this->buildInProjectMode($this->appRoot . '/project.make');
        } else {
            $this->getLogger()->notice("Building in vanilla mode: you are missing out!");
            $this->buildDir = $this->appRoot;
            $this->specialDestinations = array();
            $this->preventArchive = true;

            $this->copyGitIgnore('drupal/gitignore-vanilla');

            $this->checkIgnored('sites/default/settings.local.php');
            $this->checkIgnored('sites/default/files');
        }

        $this->symLinkSpecialDestinations();
    }

    /**
     * Check that an application file is ignored in .gitignore.
     *
     * @param string $filename
     * @param string $suggestion
     */
    protected function checkIgnored($filename, $suggestion = null)
    {
        $repositoryDir = $this->projectRoot . '/' . LocalProject::REPOSITORY_DIR;
        $relative = $this->fsHelper->makePathRelative($this->appRoot . '/' . $filename, $repositoryDir);
        if (!$this->gitHelper->execute(array('check-ignore', $relative), $repositoryDir)) {
            $suggestion = $suggestion ?: $relative;
            $this->getLogger()->warning("You should exclude this file using .gitignore: $suggestion");
        }
    }

    /**
     * Set up options to pass to the drush commands.
     */
    protected function setUpDrushFlags()
    {
        $this->drushFlags = array();
        $this->drushFlags[] = '--yes';
        if (!empty($this->settings['verbosity'])) {
            $verbosity = $this->settings['verbosity'];
            if ($verbosity === OutputInterface::VERBOSITY_QUIET) {
                $this->drushFlags[] = '--quiet';
            } elseif ($verbosity === OutputInterface::VERBOSITY_DEBUG) {
                $this->drushFlags[] = '--debug';
            } elseif ($verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                $this->drushFlags[] = '--verbose';
            }
        }

        if (!empty($this->settings['drushWorkingCopy'])) {
            $this->drushFlags[] = '--working-copy';
        }

        if (!empty($this->settings['noCache'])) {
            $this->drushFlags[] = '--no-cache';
        } else {
            $this->drushFlags[] = '--cache-duration-releasexml=300';
        }

        $concurrency = isset($this->settings['drushConcurrency']) ? $this->settings['drushConcurrency'] : 8;
        $this->drushFlags[] = '--concurrency=' . $concurrency;
    }

    /**
     * Build in 'project' mode, i.e. just using a Drush make file.
     *
     * @param string $projectMake
     */
    protected function buildInProjectMode($projectMake)
    {
        $drushHelper = new DrushHelper();
        $drushHelper->setLogger($this->getLogger());
        $drushHelper->ensureInstalled();
        $args = array_merge(
          array('make', $projectMake, $this->buildDir),
          $this->drushFlags
        );
        $drushHelper->execute($args, null, true, false);

        $this->processSettingsPhp();

        $this->ignoredFiles[] = 'project.make';
        $this->ignoredFiles[] = 'settings.local.php';
        $this->specialDestinations['sites.php'] = '{webroot}/sites';

        // Symlink, non-recursively, all files from the app into the
        // 'sites/default' directory.
        $this->fsHelper->symlinkAll(
          $this->appRoot,
          $this->buildDir . '/sites/default',
          true,
          false,
          array_merge($this->ignoredFiles, array_keys($this->specialDestinations))
        );
    }

    /**
     * Build in 'profile' mode: the application contains a site profile.
     *
     * @param string $profileName
     *
     * @throws \Exception
     */
    protected function buildInProfileMode($profileName)
    {
        $drushHelper = new DrushHelper();
        $drushHelper->setLogger($this->getLogger());
        $drushHelper->ensureInstalled();

        // Find the contrib make file.
        if (file_exists($this->appRoot . '/project.make')) {
            $projectMake = $this->appRoot . '/project.make';
        } elseif (file_exists($this->appRoot . '/drupal-org.make')) {
            $projectMake = $this->appRoot . '/drupal-org.make';
        } else {
            throw new \Exception("Couldn't find a project.make or drupal-org.make in the directory.");
        }

        // Find the core make file.
        if (file_exists($this->appRoot . '/project-core.make')) {
            $projectCoreMake = $this->appRoot . '/project-core.make';
        } elseif (file_exists($this->appRoot . '/drupal-org-core.make')) {
            $projectCoreMake = $this->appRoot . '/drupal-org-core.make';
        } else {
            throw new \Exception("Couldn't find a project-core.make or drupal-org-core.make in the directory.");
        }

        $args = array_merge(
          array('make', $projectCoreMake, $this->buildDir),
          $this->drushFlags
        );
        $drushHelper->execute($args, null, true, false);

        $profileDir = $this->buildDir . '/profiles/' . $profileName;
        mkdir($profileDir, 0755, true);

        $this->getLogger()->info("Building the profile: <info>$profileName</info>");

        $args = array_merge(
          array('make', '--no-core', '--contrib-destination=.', $projectMake),
          $this->drushFlags
        );
        $drushHelper->execute($args, $profileDir, true, false);

        $this->getLogger()->info("Symlinking existing app files to the profile");

        $this->ignoredFiles[] = basename($projectMake);
        $this->ignoredFiles[] = basename($projectCoreMake);
        $this->ignoredFiles[] = 'settings.local.php';

        $this->specialDestinations['settings*.php'] = '{webroot}/sites/default';
        $this->specialDestinations['sites.php'] = '{webroot}/sites';

        $this->processSettingsPhp();

        // Symlink recursively; skip existing files (built by Drush make) for
        // example 'modules/contrib', but include files from the app such as
        // 'modules/custom'.
        $this->fsHelper->symlinkAll(
          $this->appRoot,
          $profileDir,
          true,
          true,
          array_merge($this->ignoredFiles, array_keys($this->specialDestinations))
        );
    }

    /**
     * Handle a custom settings.php file for project and profile mode.
     *
     * If the user has a custom settings.php file, and we symlink it into
     * sites/default, then it will probably fail to pick up
     * settings.local.php from the right place. So we need to copy the
     * settings.php instead of symlinking it.
     *
     * See https://github.com/platformsh/platformsh-cli/issues/175
     */
    protected function processSettingsPhp()
    {
        $settingsPhpFile = $this->appRoot . '/settings.php';
        if (file_exists($settingsPhpFile)) {
            $this->getLogger()->notice("Found a custom settings.php file: $settingsPhpFile");
            copy($settingsPhpFile, $this->buildDir . '/sites/default/settings.php');
            $this->getLogger()->warning(
              "Your settings.php file has been copied (not symlinked) into the build directory."
              . "\nYou will need to rebuild if you edit this file."
            );
            $this->ignoredFiles[] = 'settings.php';
        }
    }

    public function install()
    {
        $buildDir = $this->buildDir;
        $sitesDefault = $buildDir . '/sites/default';
        $resources = CLI_ROOT . '/resources/drupal';
        $shared = $this->getSharedDir();

        // The build has been done, create a settings.php if it is missing.
        $this->fsHelper->copy($resources . '/settings.php', $sitesDefault . '/settings.php');

        // Create the shared/settings.local.php if it doesn't exist. Everything
        // in shared will be symlinked into sites/default.
        $this->fsHelper->copy($resources . '/settings.local.php', $shared . '/settings.local.php');

        // Create a shared/files directory.
        if (!file_exists($shared . '/files')) {
            mkdir($shared . '/files');
            // Group write access is potentially useful and probably harmless.
            chmod($shared . '/files', 0775);
        }

        // Symlink all files and folders from shared.
        $this->fsHelper->symlinkAll($shared, $sitesDefault);
    }

}
