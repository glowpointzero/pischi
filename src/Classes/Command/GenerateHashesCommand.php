<?php
namespace Glowpointzero\Pischi\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateHashesCommand extends AbstractCommand
{
    const COMMAND_NAME = 'generatehashes';
    const COMMAND_DESCRIPTION = 'Generates a hashes file for the given directory and all its subdirectories & files.';
    const COMMAND_ALIASES = ['hash'];

    protected $configuration = [
        'hashFile' => 'hashes.pischi.json',
        'sourceDirectory' => './',
        'excludedPaths' => [],
        'excludedPatterns' => []
    ];
    protected $defaultConfigurationFileName = 'hashing.pischi.json';
    protected $loadedConfigurationPath = '';
    protected $optionNamesOverridingConfiguration = ['sourceDirectory', 'hashFile'];
    protected $configurationValidation = [
        'hashFile' => ['fileDoesNotExist'],
        'sourceDirectory' => ['directoryExists']
    ];
    
    public function configure()
    {
        parent::configure();

        $this->addOption(
            'configuration',
            'c',
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Path to the hashing configuration json file.'
        );
        
        $this->addOption(
            'sourceDirectory',
            'd|directory',
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Path to the source directory the hashes should be generated of. Overrides value set by the configuration json.'
        );

        $this->addOption(
            'hashFile',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Path to the file where the generated hashes will be stored. Overrides value set by the configuration json.'
        );
        
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $sourceDirectory = realpath($this->configuration['sourceDirectory']);
        $targetFile = $this->configuration['hashFile'];
        
        $this->io->processingStart(sprintf('Generating hashes for directory "%s", saving it into "%s"', $sourceDirectory, $targetFile));
        
        $hasher = new \Glowpointzero\Pischi\Hasher();
        $fileHashes = $hasher->generateFileHashesRecursively(
            $sourceDirectory,
            $this->configuration
        );

        $this->fileSystem->dumpFile($targetFile, json_encode($fileHashes));
        
        $this->io->processingEnd('Done.');
    }
}
