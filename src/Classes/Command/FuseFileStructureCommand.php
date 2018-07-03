<?php
namespace Glowpointzero\Pischi\Command;

use Glowpointzero\Pischi\Fuser;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FuseFileStructureCommand extends AbstractCommand
{
    const COMMAND_NAME = 'fusefilestructure';
    const COMMAND_DESCRIPTION = 'Fuses two given file structures by the rules of the given configuration.';
    const COMMAND_ALIASES = ['fuse'];

    protected $configuration = [
        'sourceDirectory' => '',
        'targetDirectory' => './',
        'hashFile' => '',
        'defaultFusingStrategy' => Fuser::DEFAULT_FUSING_STRATEGY,
        'fusingStrategies' => []
    ];
    protected $defaultConfigurationFileName = 'fusing.pischi.json';
    protected $loadedConfigurationPath = '';
    protected $optionNamesOverridingConfiguration = ['sourceDirectory', 'targetDirectory', 'hashFile'];
    protected $configurationValidation = [
        'hashFile' => ['fileExists'],
        'sourceDirectory' => ['directoryExists'],
        'targetDirectory' => ['directoryExists']
    ];
    
    public function configure()
    {
        parent::configure();

        $this->addOption(
            'configuration',
            'c',
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Path to the configuration file.'
        );

        $this->addOption(
            'hashFile',
            'hashes',
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Hash file path. Overrides pre-configured value set by the configuration file.'
        );

        $this->addOption(
            'sourceDirectory',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Source directory path. Overrides value set by the configuration json.'
        );

        $this->addOption(
            'targetDirectory',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Target directory path. Overrides value set by the configuration json.'
        );

        $this->addOption(
            'dryRun',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            'When set, no files will be replaced.'
        );
    }
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = (bool) $input->getOption('dryRun');
        $fileHashes = [];
        if (!empty($this->configuration['hashFile'])) {
            $fileHashes = json_decode(@file_get_contents($this->configuration['hashFile']), true);
        }
        
        $this->io->say('Current configuration:');
        $configurationValues = [];
        foreach ($this->configuration as $configurationKey => $configurationValue) {
            if (is_array($configurationValue)) {
                $configurationValue = json_encode($configurationValue);
            }
            $configurationValues[] = $configurationKey . PHP_EOL . $configurationValue . PHP_EOL;
        }
        $this->io->say(implode(PHP_EOL, $configurationValues));
        
        $this->io->confirm('Continue with this configuration?');
        
        if (!$dryRun) {
            $this->io->warning(sprintf(
                'Ooh, you seem to mean serious business. Files will probably get replaced and/or deleted! Really fuse %s into %s',
                PHP_EOL . $this->configuration['sourceDirectory'] . PHP_EOL,
                PHP_EOL . realpath($this->configuration['targetDirectory']) . PHP_EOL
            ));
            $reallyFuse = $this->io->confirm(
                'Fuse these two paths',
                false
            );
            if (!$reallyFuse) {
                return 1;
            }
        }
        
        $fuser = new Fuser();
        $fuser->fuse(
            $this->io,
            $this->configuration['sourceDirectory'],
            $this->configuration['targetDirectory'],
            $fileHashes,
            $this->configuration['defaultFusingStrategy'],
            $this->configuration['fusingStrategies'],
            $dryRun
        );
    }
}
