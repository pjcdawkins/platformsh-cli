<?php

namespace Platformsh\Cli\Console;

use Symfony\Component\Console\Input\ArgvInput as BaseArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

class ArgvInput extends BaseArgvInput
{

    protected $alias;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $argv = null, InputDefinition $definition = null)
    {
        if (null === $argv) {
            $argv = $_SERVER['argv'];
        }

        foreach ($argv as $key => $arg) {
            if ($arg[0] === '@') {
                if (isset($this->alias)) {
                    throw new \InvalidArgumentException("Alias already defined");
                }
                $this->alias = substr($arg, 1);
                unset($argv[$key]);
            }
        }

        parent::__construct($argv, $definition);
    }

    /**
     * Get the passed alias.
     *
     * @return string|false
     */
    public function getAlias()
    {
        return $this->alias ?: false;
    }
}
