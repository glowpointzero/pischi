<?php
namespace Glowpointzero\Pischi\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InfoCommand extends AbstractCommand
{
    const COMMAND_NAME = 'info';
    const COMMAND_DESCRIPTION = 'Display application info and help';

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $applicationNameAndVersion = $this->getApplication()->getName() . '   |   ' . $this->getApplication()->getVersion();
        $this->io->title($applicationNameAndVersion);
        $this->getApplication()->find('help')->run($input, $output);
    }
}
