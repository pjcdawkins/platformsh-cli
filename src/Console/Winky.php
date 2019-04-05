<?php

namespace Platformsh\Cli\Console;

use Symfony\Component\Console\Output\OutputInterface;

class Winky extends Animation
{
    /**
     * @param OutputInterface $output
     * @param string          $signature
     */
    public function __construct(OutputInterface $output, $signature = '')
    {
        $dir = CLI_ROOT . '/resources/winky';
        if ($output->isDecorated()) {
            $dir .= '-decorated';
        }

        $sources = [];
        $sources['normal'] = file_get_contents($dir . '/normal');
        $sources['wink'] = file_get_contents($dir . '/wink');
        $sources['twitch'] = file_get_contents($dir . '/twitch');

        $indent = '      ';
        if (strlen($signature) > 0) {
            $signatureIndent = str_repeat(' ', strlen($indent) + 9 - floor(strlen($signature) / 2));
            $signature = "\n" . $signatureIndent . $signature;
        }

        $sources = array_map(function ($source) use ($indent, $signature) {
            return "\n" . preg_replace('/^/m', $indent, $source) . $signature . "\n";
        }, $sources);

        if ($output->isDecorated()) {
            foreach ($sources as &$source) {
                $source = str_replace('\\033', "\033", $source);
            }
        }

        // Build the list of frames.
        $frames = [];
        for ($i = 1; $i <= 16; $i++) {
            if ($i === 8) {
                $frames[] = $sources['wink'];
            } elseif ($i === 16) {
                $frames[] = $sources['twitch'];
            } else {
                $frames[] = $sources['normal'];
            }
        }

        parent::__construct($output, $frames, 180000);
    }
}
