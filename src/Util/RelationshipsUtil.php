<?php

namespace Platformsh\Cli\Util;

use Platformsh\Cli\CliConfig;
use Platformsh\Cli\Service\QuestionHelper;
use Platformsh\Cli\Service\Shell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RelationshipsUtil
{

    protected $output;
    protected $shellHelper;
    protected $config;

    /**
     * @param OutputInterface $output
     * @param Shell           $shellHelper
     * @param CliConfig       $config
     */
    public function __construct(OutputInterface $output, Shell $shellHelper = null, CliConfig $config = null)
    {
        $this->output = $output;
        $this->shellHelper = $shellHelper ?: new Shell($output);
        $this->config = $config ?: new CliConfig();
    }

    /**
     * @param string          $sshUrl
     * @param InputInterface  $input
     *
     * @return array|false
     */
    public function chooseDatabase($sshUrl, InputInterface $input)
    {
        $relationships = $this->getRelationships($sshUrl);

        // Filter to find database (mysql and pgsql) relationships.
        $relationships = array_filter($relationships, function (array $relationship) {
            foreach ($relationship as $key => $service) {
                if ($service['scheme'] === 'mysql' || $service['scheme'] === 'pgsql') {
                    return true;
                }
            }

            return false;
        });

        if (empty($relationships)) {
            $this->output->writeln('No databases found');
            return false;
        }

        $questionHelper = new QuestionHelper($input, $this->output);
        $choices = [];
        $separator = '.';
        foreach ($relationships as $name => $relationship) {
            $serviceCount = count($relationship);
            foreach ($relationship as $key => $service) {
                $choices[$name . $separator . $key] = $name . ($serviceCount > 1 ? '.' . $key : '');
            }
        }
        $choice = $questionHelper->choose($choices, 'Enter a number to choose a database:');
        list($name, $key) = explode($separator, $choice, 2);
        $database = $relationships[$name][$key];

        // Add metadata about the database.
        $database['_relationship_name'] = $name;
        $database['_relationship_key'] = $key;

        return $database;
    }

    /**
     * @param string $sshUrl
     *
     * @return array
     */
    public function getRelationships($sshUrl)
    {
        $args = ['ssh', $sshUrl, 'echo $' . $this->config->get('service.env_prefix') . 'RELATIONSHIPS'];
        $result = $this->shellHelper->execute($args, null, true);

        return json_decode(base64_decode($result), true);
    }
}
