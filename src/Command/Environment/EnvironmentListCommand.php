<?php
namespace Platformsh\Cli\Command\Environment;

use Platformsh\Cli\Api;
use Platformsh\Cli\Command\CommandBase;
use Platformsh\Cli\Util\PropertyFormatter;
use Platformsh\Cli\Util\Table;
use Platformsh\Client\Model\Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnvironmentListCommand extends CommandBase
{

    protected $children = [];

    /** @var Environment */
    protected $currentEnvironment;
    protected $mapping = [];
    protected $properties = ['id', 'title', 'status'];

    /** @var PropertyFormatter */
    protected $propertyFormatter;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('environment:list')
            ->setAliases(['environments'])
            ->setDescription('Get a list of environments')
            ->addOption('no-inactive', 'I', InputOption::VALUE_NONE, 'Do not show inactive environments')
            ->addOption('pipe', null, InputOption::VALUE_NONE, 'Output a simple list of environment IDs.')
            ->addOption('refresh', null, InputOption::VALUE_REQUIRED, 'Whether to refresh the list.', 1)
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'A property to sort by', 'title')
            ->addOption('properties', 'P', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The properties to list', $this->properties)
            ->addOption('reverse', null, InputOption::VALUE_NONE, 'Sort in reverse (descending) order');
        Table::addFormatOption($this->getDefinition());
        $this->addProjectOption();
    }

    /**
     * Build a tree out of a list of environments.
     *
     * @param Environment[] $environments
     * @param string        $parent
     *
     * @return array
     */
    protected function buildEnvironmentTree(array $environments, $parent = null)
    {
        $children = [];
        foreach ($environments as $environment) {
            if ($environment->parent === $parent) {
                $this->children[$environment->id] = $this->buildEnvironmentTree(
                    $environments,
                    $environment->id
                );
                $children[$environment->id] = $environment;
            }
        }

        return $children;
    }

    /**
     * Recursively build rows of the environment table.
     *
     * @param Environment[] $tree
     * @param bool $machineReadable
     * @param int $indentAmount
     *
     * @return array
     */
    protected function buildEnvironmentRows($tree, $machineReadable = false, $indentAmount = 0)
    {
        $rows = [];
        foreach ($tree as $environment) {
            $row = [];

            foreach ($this->properties as $property) {
                $row[$property] = $this->propertyFormatter->format(
                    Api::getNestedProperty($environment, $property),
                    $property
                );
            }

            if (isset($row['id'])) {
                if (!$machineReadable) {
                    $row['id'] = str_repeat('   ', $indentAmount) . $row['id'];
                }
                if (!$machineReadable && $this->currentEnvironment
                    && $environment->id === $this->currentEnvironment->id) {
                    $row['id'] .= "<info>*</info>";
                }
            }
            if (isset($row['title']) && ($branch = array_search($environment->id, $this->mapping))) {
                $row['title'] .= '(' . $branch . ')';
            }
            if (isset($row['status'])) {
                $row['status'] = $this->formatEnvironmentStatus($row['status']);
            }

            $rows[] = $row;
            $rows = array_merge(
                $rows,
                $this->buildEnvironmentRows(
                    $this->children[$environment->id],
                    $machineReadable,
                    $indentAmount + 1
                )
            );
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);

        $refresh = $input->hasOption('refresh') && $input->getOption('refresh');

        $environments = $this->api()->getEnvironments($this->getSelectedProject(), $refresh ? true : null);

        if ($input->getOption('no-inactive')) {
            $environments = array_filter($environments, function ($environment) {
                return $environment->status !== 'inactive';
            });
        }

        if (!count($environments)) {
            $this->stdErr->writeln('No environment(s) found.');

            return 1;
        }

        $this->properties = $input->getOption('properties');
        if (count($this->properties) === 1
            && isset($this->properties[0])
            && strpos($this->properties[0], ',') !== false) {
            $this->properties = explode(',', $this->properties[0]);
        }
        $this->propertyFormatter = new PropertyFormatter($input);

        if ($input->getOption('sort')) {
            $this->api()->sortResources($environments, $input->getOption('sort'));
        }
        if ($input->getOption('reverse')) {
            $environments = array_reverse($environments, true);
        }

        if ($input->getOption('pipe')) {
            $output->writeln(array_keys($environments));

            return 0;
        }

        $project = $this->getSelectedProject();
        $this->currentEnvironment = $this->getCurrentEnvironment($project);

        if (($currentProject = $this->getCurrentProject()) && $currentProject == $project) {
            $projectConfig = $this->getProjectConfig($this->getProjectRoot());
            if (isset($projectConfig['mapping'])) {
                $this->mapping = $projectConfig['mapping'];
            }
        }

        $tree = $this->buildEnvironmentTree($environments);

        // To make the display nicer, we move all the children of master
        // to the top level.
        if (isset($tree['master'])) {
            $tree += $this->children['master'];
            $this->children['master'] = [];
        }

        // Add orphaned environments (those whose parents do not exist) to the
        // tree.
        foreach ($environments as $id => $environment) {
            if (!empty($environment->parent) && !isset($environments[$environment->parent])) {
                $tree += [$id => $environment];
            }
        }

        $headers = [];
        foreach ($this->properties as $property) {
            switch ($property) {
                case 'id':
                    $headers[] = strtoupper($property);
                    break;

                case 'title':
                    $headers[] = ucfirst($property);
                    break;

                default:
                    $headers[] = $property;
            }
        }

        $table = new Table($input, $output);

        if ($table->formatIsMachineReadable()) {
            $table->render($this->buildEnvironmentRows($tree, true), $headers);

            return 0;
        }

        $this->stdErr->writeln("Your environments are: ");

        $table->render($this->buildEnvironmentRows($tree), $headers);

        if (!$this->currentEnvironment) {
            return 0;
        }

        $this->stdErr->writeln("<info>*</info> - Indicates the current environment\n");

        $currentEnvironment = $this->currentEnvironment;

        $this->stdErr->writeln("Check out a different environment by running <info>" . self::$config->get('application.executable') . " checkout [id]</info>");

        if ($currentEnvironment->operationAvailable('branch')) {
            $this->stdErr->writeln(
                "Branch a new environment by running <info>" . self::$config->get('application.executable') . " environment:branch [new-name]</info>"
            );
        }
        if ($currentEnvironment->operationAvailable('activate')) {
            $this->stdErr->writeln(
                "Activate the current environment by running <info>" . self::$config->get('application.executable') . " environment:activate</info>"
            );
        }
        if ($currentEnvironment->operationAvailable('delete')) {
            $this->stdErr->writeln("Delete the current environment by running <info>" . self::$config->get('application.executable') . " environment:delete</info>");
        }
        if ($currentEnvironment->operationAvailable('backup')) {
            $this->stdErr->writeln(
                "Make a snapshot of the current environment by running <info>" . self::$config->get('application.executable') . " snapshot:create</info>"
            );
        }
        if ($currentEnvironment->operationAvailable('merge')) {
            $this->stdErr->writeln("Merge the current environment by running <info>" . self::$config->get('application.executable') . " environment:merge</info>");
        }
        if ($currentEnvironment->operationAvailable('synchronize')) {
            $this->stdErr->writeln(
                "Sync the current environment by running <info>" . self::$config->get('application.executable') . " environment:synchronize</info>"
            );
        }

        return 0;
    }

    /**
     * @param string $status
     *
     * @return string
     */
    protected function formatEnvironmentStatus($status)
    {
        if ($status == 'dirty') {
            $status = 'In progress';
        }

        return ucfirst($status);
    }
}
