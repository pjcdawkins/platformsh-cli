<?php
namespace CommerceGuys\Platform\Cli\Local;

use CommerceGuys\Platform\Cli\Helper\GitHelper;
use CommerceGuys\Platform\Cli\Helper\ShellHelper;
use CommerceGuys\Platform\Cli\Helper\ShellHelperInterface;
use CommerceGuys\Platform\Cli\Local\Toolstack\ToolstackInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Parser;

class LocalBuild
{

    protected $settings;

    /** @var ShellHelperInterface */
    protected $shellHelper;

    /**
     * @return ToolstackInterface[]
     */
    public function getToolstacks()
    {
        return array(
          new Toolstack\Drupal(),
          new Toolstack\Symfony(),
        );
    }

    /**
     * @param array                $settings
     */
    public function __construct(array $settings = array())
    {
        $this->settings = $settings;
        $this->shellHelper = new ShellHelper();
    }

    /**
     * @param string          $projectRoot
     * @param OutputInterface $output
     *
     * @return bool
     */
    public function buildProject($projectRoot, OutputInterface $output)
    {
        $repositoryRoot = $this->getRepositoryRoot($projectRoot);
        $success = true;
        foreach ($this->getApplications($repositoryRoot) as $appRoot) {
            $success = $this->buildApp($appRoot, $projectRoot, $output) && $success;
        }
        if (empty($this->settings['noClean'])) {
            $output->writeln("Cleaning up...");
            $this->clean($projectRoot, 3);
            $this->cleanArchives($projectRoot);
        }
        return $success;
    }

    /**
     * Get a list of applications in the repository.
     *
     * @param string $repositoryRoot The absolute path to the repository.
     *
     * @return string[]    A list of directories containing applications.
     */
    public function getApplications($repositoryRoot)
    {
        // @todo: Determine multiple project roots, perhaps using Finder again
        return array($repositoryRoot);
    }

    /**
     * Get the application's configuration, parsed from its YAML definition.
     *
     * @param string $appRoot The absolute path to the application.
     *
     * @return array
     */
    public function getAppConfig($appRoot)
    {
        $config = array();
        if (file_exists($appRoot . '/.platform.app.yaml')) {
            $parser = new Parser();
            $config = (array) $parser->parse(file_get_contents($appRoot . '/.platform.app.yaml'));
        }
        if (!isset($config['name'])) {
            $dir = basename(dirname($appRoot));
            if ($dir != 'repository') {
                $config['name'] = $dir;
            }
        }

        return $config;
    }

    /**
     * Get the toolstack for a particular application.
     *
     * @param string $appRoot   The absolute path to the application.
     * @param mixed  $appConfig The application's configuration.
     *
     * @throws \Exception   If a specified toolstack is not found.
     *
     * @return ToolstackInterface|false
     */
    public function getToolstack($appRoot, array $appConfig = array())
    {
        $toolstackChoice = false;
        if (isset($appConfig['toolstack'])) {
            $toolstackChoice = $appConfig['toolstack'];
        }
        foreach (self::getToolstacks() as $toolstack) {
            if (($toolstackChoice && $toolstack->getKey() == $toolstackChoice)
              || $toolstack->detect($appRoot)
            ) {
                return $toolstack;
            }
        }
        if ($toolstackChoice) {
            throw new \Exception("Toolstack not found: $toolstackChoice");
        }

        return false;
    }

    /**
     * @var string $projectRoot
     * @return string
     */
    protected function getRepositoryRoot($projectRoot)
    {
        return $projectRoot . '/repository';
    }

    /**
     * @param string $appRoot
     *
     * @return string|false
     */
    protected function getTreeId($appRoot)
    {
        $hashes = array();
        $helper = new GitHelper();
        $tree = $helper->execute(array('ls-tree', 'HEAD', '.'), $appRoot, true);
        $tree = preg_replace('#^|\n[^\n]+?\.platform\n|$#', "\n", $tree);
        $hashes[] = sha1($tree);
        // Get the hashes of untracked files.
        $untracked = $helper->execute(array('ls-files', '--others', '--exclude-standard', '-z'), $appRoot);
        foreach (explode("\n", $untracked) as $filename) {
            if (is_dir($filename) || strpos($filename, '.platform/') === 0) {
                continue;
            }
            $hashes[] = sha1_file("$appRoot/$filename");
        }
        return sha1(implode(' ', $hashes));
    }

    /**
     * @param string          $appRoot
     * @param string          $projectRoot
     * @param OutputInterface $output
     *
     * @return bool
     */
    protected function buildApp($appRoot, $projectRoot, OutputInterface $output)
    {
        $appConfig = $this->getAppConfig($appRoot);
        $verbose = $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;

        $appName = isset($appConfig['name']) ? $appConfig['name'] : false;

        $buildName = date('Y-m-d--H-i-s') . '--' . $this->settings['environmentId'];
        $buildDir = $projectRoot . '/builds/' . $buildName;

        $toolstack = $this->getToolstack($appRoot, $appConfig);
        if (!$toolstack) {
            $output->writeln("<comment>Could not detect toolstack for directory: $appRoot</comment>");

            return false;
        }

        $toolstack->prepare($buildDir, $appRoot, $projectRoot, $this->settings);

        $archive = false;
        $treeId = $this->getTreeId($appRoot);
        if ($treeId) {
            if ($verbose) {
                $output->writeln("Tree ID: $treeId");
            }
            $archive = $projectRoot . '/.build-archives/' . $treeId . '.tar.gz';
        }

        if ($archive && file_exists($archive)) {
            $message = "Extracting archive";
            if ($appName) {
                $message .= " for application <info>$appName</info>";
            }
            $message .= '...';
            $output->writeln($message);
            $this->extractBuild($archive, $buildDir);
        } else {
            $message = "Building application";
            if ($appName) {
                $message .= " <info>$appName</info>";
            }
            $message .= " using the toolstack <info>" . $toolstack->getKey() . "</info>";
            $output->writeln($message);

            $toolstack->setOutput($output);

            $toolstack->build();

            if ($archive) {
                if ($verbose) {
                    $output->writeln("Saving archive to: $archive");
                }
                $this->archiveBuild($buildDir, $archive);
            }
        }

        $docRoot = $projectRoot . '/www';
        if ($verbose) {
            $output->writeln("Installing...");
        }
        $toolstack->install($docRoot);

        $this->warnAboutHooks($appConfig, $output);

        $message = "Build complete";
        if ($appName) {
            $message .= " for <info>$appName</info>";
        }
        $output->writeln($message);

        if ($verbose) {
            $output->writeln("The application has been symlinked to: $docRoot");
        }

        return true;
    }

    /**
     * Warn the user that the CLI will not run build/deploy hooks.
     *
     * @param array           $appConfig
     * @param OutputInterface $output
     *
     * @return bool
     */
    protected function warnAboutHooks(array $appConfig, OutputInterface $output)
    {
        if (empty($appConfig['hooks']['build'])) {
            return false;
        }
        $indent = '        ';
        $output->writeln("<comment>You have defined the following hook(s). The CLI cannot run them locally.</comment>");
        foreach (array('build', 'deploy') as $hookType) {
            if (empty($appConfig['hooks'][$hookType])) {
                continue;
            }
            $output->writeln("    $hookType: |");
            $hooks = (array) $appConfig['hooks'][$hookType];
            $asString = implode("\n", array_map('trim', $hooks));
            $withIndent = $indent . str_replace("\n", "\n$indent", $asString);
            $output->writeln($withIndent);
        }

        return true;
    }

    public function cleanArchives($projectRoot, $ttl = 604800)
    {
        $dir = $projectRoot . '/.build-archives';
        if (!is_dir($dir)) {
            return array(0, 0, 0);
        }
        $fs = new Filesystem();
        $handle = opendir($dir);
        $now = time();
        $num = 0;
        $numDeleted = 0;
        $numKept = 0;
        try {
            while ($entry = readdir($handle)) {
                if ($entry[0] == '.') {
                    continue;
                }
                $num++;
                $filename = $dir . '/' . $entry;
                if ($now - filemtime($filename) > $ttl) {
                    $fs->remove($filename);
                    $numDeleted++;
                }
                else {
                    $numKept++;
                }
            }
        }
        catch (IOException $e) {
        }
        closedir($handle);
        return array($num, $numDeleted, $numKept);
    }

    public function clean($projectRoot, $keep = 5, OutputInterface $output = null)
    {
        $output = $output ?: new NullOutput();
        $buildsDir = $projectRoot . '/builds';

        // Collect directories.
        $builds = array();
        $handle = opendir($buildsDir);
        while ($entry = readdir($handle)) {
            if (strpos($entry, '.') !== 0) {
                $builds[] = $entry;
            }
        }

        $count = count($builds);

        if (!$count) {
            return array(0, 0, 0);
        }

        // Remove old builds.
        sort($builds);
        $numDeleted = 0;
        $numKept = 0;
        $fs = new Filesystem();
        foreach ($builds as $build) {
            if ($count - $numDeleted > $keep) {
                $output->writeln("Deleting: $build");
                $fs->remove($buildsDir . '/' . $build);
                $numDeleted++;
            }
            else {
                $numKept++;
            }
        }

        return array($count, $numDeleted, $numKept);
    }

    protected function archiveBuild($buildDir, $destination)
    {
        if (!file_exists($buildDir)) {
            throw new \RuntimeException("Build incomplete");
        }
        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }
        return (bool) $this->shellHelper
          ->execute(array('tar', '-czp', '-C' . $buildDir, '-f' . $destination, '.'), null, true);
    }

    protected function extractBuild($archive, $destination)
    {
        if (!file_exists($archive)) {
            throw new \InvalidArgumentException("Archive not found: $archive");
        }
        if (!is_writable(dirname($destination))) {
            throw new \InvalidArgumentException("Cannot extract archive to: $destination");
        }
        mkdir($destination);

        return (bool) $this->shellHelper
          ->execute(array('tar', '-xzp', '-C' . $destination, '-f' . $archive), null, true);
    }
}
