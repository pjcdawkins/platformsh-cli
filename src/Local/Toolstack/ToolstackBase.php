<?php

namespace CommerceGuys\Platform\Cli\Local\Toolstack;

use CommerceGuys\Platform\Cli\Helper\FilesystemHelper;
use CommerceGuys\Platform\Cli\Helper\ShellHelper;
use CommerceGuys\Platform\Cli\Helper\ShellHelperInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ToolstackBase implements ToolstackInterface
{

    protected $settings = array();
    protected $appRoot;
    protected $projectRoot;
    protected $buildDir;
    protected $absoluteLinks = false;

    /** @var OutputInterface */
    protected $output;

    /** @var FilesystemHelper */
    protected $fsHelper;

    /** @var ShellHelperInterface */
    protected $shellHelper;

    public function __construct(FilesystemHelper $fsHelper = null, ShellHelperInterface $shellHelper = null)
    {
        $this->fsHelper = $fsHelper ?: new FilesystemHelper();
        $this->shellHelper = $shellHelper ?: new ShellHelper();
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        $this->shellHelper->setOutput($output);
    }

    public function prepare($buildDir, $appRoot, $projectRoot, array $settings)
    {
        $this->appRoot = $appRoot;
        $this->projectRoot = $projectRoot;
        $this->settings = $settings;

        $this->buildDir = $buildDir;

        $this->absoluteLinks = !empty($settings['absoluteLinks']);
        $this->fsHelper->setRelativeLinks(!$this->absoluteLinks);
    }

}
