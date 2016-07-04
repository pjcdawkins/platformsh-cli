<?php

namespace Platformsh\Cli\Util;

use Symfony\Component\Console\Output\OutputInterface;

class Bot extends ConsoleAnimation
{
    /**
     * @param OutputInterface $output
     * @param string          $signature
     */
    public function __construct(OutputInterface $output, $signature = '')
    {
        $filenames = [
            CLI_ROOT . '/resources/bot/bot1',
            CLI_ROOT . '/resources/bot/bot2',
            CLI_ROOT . '/resources/bot/bot3',
            CLI_ROOT . '/resources/bot/bot4',
        ];

        $indent = 4;
        if (strlen($signature) > 0) {
            $signatureIndent = str_repeat(' ', $indent + 5 - floor(strlen($signature) / 2));
            $signature = "\n" . $signatureIndent . '<info>' . $signature . '</info>';
        }

        // The frames are the contents of each file, with each line indented.
        $frames = array_map(function ($filename) use ($indent, $signature) {
            return Util::indent(file_get_contents($filename), $indent)
                . $signature;
        }, $filenames);

        parent::__construct($output, $frames);
    }
}
