<?php
namespace Platformsh\Cli\Command\Domain;

use GuzzleHttp\Exception\ClientException;
use Platformsh\Cli\Util\PropertyFormatter;
use Platformsh\Cli\Util\Table;
use Platformsh\Cli\Util\Util;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DomainGetCommand extends DomainCommandBase
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('domain:get')
            ->addArgument('id', InputArgument::OPTIONAL, 'The domain to view. Leave blank to choose from a list.')
            ->addOption('property', 'P', InputOption::VALUE_OPTIONAL, 'The domain property to view')
            ->setDescription('View a single domain');
        Table::addFormatOption($this->getDefinition());
        $this->addProjectOption();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);

        $project = $this->getSelectedProject();

        $search = $input->getArgument('id');

        try {
            $domain = $project->getDomain($search);

            if ($domain === false) {
                $this->stdErr->writeln("Domain not found: <info>$search</info>");

                return 1;
            }
        }
        catch (ClientException $e) {
            $this->handleApiException($e, $project);
            return 1;
        }

        if ($property = $input->getOption('property')) {
            $value = Util::getNestedArrayValue($domain->getData(), explode('.', $property), $exists);
            if (!$exists) {
                $this->stdErr->writeln("Domain property not found: <error>$property</error>");

                return 1;
            }

            $formatter = new PropertyFormatter();
            $output->writeln($formatter->format($value, $property));

            return 0;
        }

        $table = new Table($input, $output);

        $info = [];
        $formatter = new PropertyFormatter();
        foreach ($domain->getProperties() as $property => $value) {
            $info[$property] = $formatter->format($value, $property);
        }

        // Do not list confusing properties.
        unset(
            $info['wildcard'] // @todo remove this in the API
        );

        if (!$table->formatIsMachineReadable()) {
            $info = array_map(function ($value) {
                return wordwrap($value, 82, "\n", true);
            }, $info);
        }

        $table->renderSimple(array_values($info), array_keys($info));

        return 0;
    }
}
