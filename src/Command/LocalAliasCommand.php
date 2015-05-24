<?php

namespace Platformsh\Cli\Command;

use Platformsh\Cli\Exception\RootNotFoundException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class LocalAliasCommand extends PlatformCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
          ->setName('local:alias')
          ->addArgument('name', InputArgument::REQUIRED, 'Set a new alias name')
          ->setDescription('Create an alias for the current project');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getProjectRoot()) {
            throw new RootNotFoundException();
        }

        $this->validateInput($input, $output);

        $name = $input->getArgument('name');
        $name = preg_replace('#[/ @]+#', '-', ltrim($name, '@'));

        $newAlias = [
            'project' => [
              'id' => $this->getSelectedProject()->id,
              'root' => $this->getProjectRoot(),
            ],
        ];

        $host = parse_url($this->getSelectedProject()->getUri(), PHP_URL_HOST);
        if ($host) {
            $newAlias['project']['host'] = $host;
        }

        $newAliasFile = $this->homeDir . '/.platformsh/aliases/' . $name . '.yaml';

        $yaml = new Yaml();

        if (file_exists($newAliasFile)) {
            $output->writeln("File exists: $newAliasFile");
            $questionHelper = $this->getHelper('question');
            if (!$questionHelper->confirm("Overwrite?", $input, $output)) {
                return 1;
            }
            $currentAlias = (array) $yaml->parse(file_get_contents($newAliasFile));
            $newAlias = array_replace_recursive($currentAlias, $newAlias);
        }

        $newAliasYaml = $yaml->dump($newAlias);

        $fs = new Filesystem();
        $fs->dumpFile($newAliasFile, $newAliasYaml);

        $output->writeln("Created alias <info>@$name</info> in file: $newAliasFile");

        return 0;
    }
}
