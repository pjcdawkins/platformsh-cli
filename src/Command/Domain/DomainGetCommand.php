<?php
namespace Platformsh\Cli\Command\Domain;

use Platformsh\Cli\Service\QuestionHelper;
use Platformsh\Cli\Util\PropertyFormatter;
use Platformsh\Cli\Util\Table;
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
            ->setDescription('Show detailed information for a domain')
            ->addArgument('name', InputArgument::OPTIONAL, 'The domain name')
            ->addOption('property', 'P', InputOption::VALUE_REQUIRED, 'The domain property to view');
        Table::addFormatOption($this->getDefinition());
        PropertyFormatter::configureInput($this->getDefinition());
        $this->addProjectOption();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);
        $project = $this->getSelectedProject();

        $domainName = $input->getArgument('name');
        if (empty($domainName)) {
            if (!$input->isInteractive()) {
                $this->stdErr->writeln('The domain name is required.');
                return 1;
            }

            $domains = $project->getDomains();
            $options = [];
            foreach ($domains as $domain) {
                $options[$domain->name] = $domain->name;
            }
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $domainName = $questionHelper->choose($options, 'Enter a number to choose a domain:');
        }

        $domain = $project->getDomain($domainName);
        if (!$domain) {
            $this->stdErr->writeln('Domain not found: <error>' . $domainName . '</error>');
            return 1;
        }

        $propertyFormatter = new PropertyFormatter($input);

        if ($property = $input->getOption('property')) {
            $value = $this->api()->getNestedProperty($domain, $property);
            $output->writeln($propertyFormatter->format($value, $property));

            return 0;
        }

        $values = [];
        $properties = [];
        foreach ($domain->getProperties() as $name => $value) {
            // Hide the deprecated (irrelevant) property 'wildcard'.
            if ($name === 'wildcard') {
                continue;
            }
            $properties[] = $name;
            $values[] = $propertyFormatter->format($value, $name);
        }
        $table = new Table($input, $output);
        $table->renderSimple($values, $properties);

        $this->stdErr->writeln('');
        $this->stdErr->writeln('To update a domain, run: <info>' . self::$config->get('application.executable') . ' domain:update [domain-name]</info>');
        $this->stdErr->writeln('To delete a domain, run: <info>' . self::$config->get('application.executable') . ' domain:delete [domain-name]</info>');

        return 0;
    }
}
