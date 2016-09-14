<?php
namespace Platformsh\Cli\Command\Project;

use Platformsh\Cli\Api;
use Platformsh\Cli\Command\CommandBase;
use Platformsh\Cli\Console\AdaptiveTableCell;
use Platformsh\Cli\Util\PropertyFormatter;
use Platformsh\Cli\Util\Table;
use Platformsh\Client\Model\Project;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectListCommand extends CommandBase
{

    protected function configure()
    {
        $this
            ->setName('project:list')
            ->setAliases(['projects'])
            ->setDescription('Get a list of all active projects')
            ->addOption('pipe', null, InputOption::VALUE_NONE, 'Output a simple list of project IDs')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Filter by region hostname')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Filter by title')
            ->addOption('refresh', null, InputOption::VALUE_REQUIRED, 'Whether to refresh the list', 1)
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'A property to sort by', 'title')
            ->addOption('properties', 'P', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The properties to list', ['id', 'title', 'url'])
            ->addOption('reverse', null, InputOption::VALUE_NONE, 'Sort in reverse (descending) order');
        Table::addFormatOption($this->getDefinition());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $refresh = $input->hasOption('refresh') && $input->getOption('refresh');

        // Fetch the list of projects.
        $projects = $this->api()->getProjects($refresh ? true : null);

        // Filter the projects by hostname.
        if ($host = $input->getOption('host')) {
            $projects = array_filter($projects, function (Project $project) use ($host) {
                return $host === parse_url($project->getUri(), PHP_URL_HOST);
            });
        }

        // Filter the projects by title.
        if ($title = $input->getOption('title')) {
            $projects = array_filter($projects, function (Project $project) use ($title) {
                return (stripos($project->title, $title) > -1);
            });
        }

        // Sort the list of projects.
        if ($input->getOption('sort')) {
            $this->api()->sortResources($projects, $input->getOption('sort'));
        }
        if ($input->getOption('reverse')) {
            $projects = array_reverse($projects, true);
        }

        $properties = $input->getOption('properties');
        if (count($properties) === 1
            && isset($properties[0])
            && strpos($properties[0], ',') !== false) {
            $properties = explode(',', $properties[0]);
        }
        $propertyFormatter = new PropertyFormatter($input);

        if ($input->getOption('pipe')) {
            $output->writeln(array_keys($projects));

            return 0;
        }

        $table = new Table($input, $output);

        $rows = [];
        foreach ($projects as $project) {
            $row = [];
            foreach ($properties as $property) {
                if ($property === 'url') {
                    $value = $project->getLink('#ui');
                } else {
                    $value = Api::getNestedProperty($project, $property);
                }
                $row[$property] = $propertyFormatter->format($value, $property);
            }
            if (isset($row['id'])) {
                $row['id'] = new AdaptiveTableCell($row['id'], ['wrap' => false]);
            }
            $rows[] = $row;
        }

        $headers = [];
        foreach ($properties as $property) {
            switch ($property) {
                case 'id':
                case 'url':
                    $headers[] = strtoupper($property);
                    break;

                case 'title':
                    $headers[] = ucfirst($property);
                    break;

                default:
                    $headers[] = $property;
            }
        }

        if ($table->formatIsMachineReadable()) {
            $table->render($rows, $headers);

            return 0;
        }

        if (!count($projects)) {
            $this->stdErr->writeln('You do not have any ' . self::$config->get('service.name') . ' projects yet.');
        } else {
            $this->stdErr->writeln("Your projects are: ");

            $table->render($rows, $headers);

            $this->stdErr->writeln("\nGet a project by running <info>" . self::$config->get('application.executable') . " get [id]</info>");
            $this->stdErr->writeln("List a project's environments by running <info>" . self::$config->get('application.executable') . " environments</info>");
        }

        return 0;
    }
}
