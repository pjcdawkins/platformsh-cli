<?php

namespace CommerceGuys\Platform\Cli;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

class PlatformInput extends ArgvInput
{

    public $platformAlias;

    /**
     * @inheritdoc
     */
    public function __construct(array $argv = null, InputDefinition $definition = null)
    {
        if (null === $argv) {
            $argv = $_SERVER['argv'];
        }

        foreach ($argv as $argument) {
            if ($argument[0] === '@' && strlen($argument) > 1) {
                $this->platformAlias = substr($argument, 1);
            }
        }

        parent::__construct($argv, $definition);
    }

}
