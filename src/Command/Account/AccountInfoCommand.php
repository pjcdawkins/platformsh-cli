<?php
namespace Platformsh\Cli\Command\Account;

use Platformsh\Cli\Command\PlatformCommand;
use Platformsh\Cli\Util\PropertyFormatter;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AccountInfoCommand extends PlatformCommand
{
    /** @var PropertyFormatter */
    protected $formatter;

    protected function configure()
    {
        $this
          ->setName('account:info')
          ->setDescription('View your account information')
          ->addArgument('property', InputArgument::OPTIONAL, 'The name of the property to view');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $info = $this->getClient()
          ->getAccountInfo();
        $include = ['uuid', 'first_name', 'last_name', 'display_name', 'mail'];
        $info = array_intersect_key($info, array_combine($include, $include));

        $this->formatter = new PropertyFormatter();

        if ($property = $input->getArgument('property')) {
            if (!isset($info[$property])) {
                $this->stdErr->writeln("Property not found: <error>$property</error>");
                return 1;
            }

            $output->writeln($this->formatter->format($info[$property], $property));
            return 0;
        }

        $this->listProperties($info, $output);
        $this->stdErr->writeln("\nLog in to another account with: <info>platform login</info>");
        $this->stdErr->writeln("Log out with: <info>platform logout</info>");
        $this->stdErr->writeln("View your account at: <info>https://accounts.platform.sh/</info>");

        return 0;
    }

    /**
     * @param array           $info
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function listProperties(array $info, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(array("Property", "Value"));
        foreach ($info as $key => $value) {
            $table->addRow(array($key, $this->formatter->format($value, $key)));
        }
        $table->render();

        return 0;
    }

}
