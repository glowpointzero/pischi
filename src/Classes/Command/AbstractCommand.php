<?php
namespace Glowpointzero\Pischi\Command;

use Glowpointzero\Pischi\Console\Component\Filesystem;
use Glowpointzero\Pischi\Console\Style;
use Symfony\Component\Console\Command\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    const COMMAND_NAME = '';
    const COMMAND_DESCRIPTION = '';
    const COMMAND_ALIASES = [];

    /**
     * @var array Command-specific configuration, possibly loaded via json
     */
    protected $configuration = [];
    /**
     * @var array File name to look out for, if none is specified via CLI
     */
    protected $defaultConfigurationFileName = '';
    /**
     * @var string Path the current configuration in $configuration is loaded frm.
     */
    protected $loadedConfigurationPath = '';
    /**
     * @var array An optional list of CLI options that will replace any configuration values that go by the same name.
     */
    protected $optionNamesOverridingConfiguration = [];
    /**
     * @var array Optional validation options for configuration values.
     *            Valid validation options are 'fileExist', 'fileDoesNotExist'
     *            (used for directories as well), and 'directoryExists'.
     *            Example: ['myConfigurationValue' => ['fileExists']]
     */
    protected $configurationValidation = [];
    
    /**
     * @var Style
     */
    protected $io;

    /**
     * @var InputInterface
     */
    protected $inputInterface;

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * Configure the current command => set up
     * some local properties.
     */
    protected function configure()
    {
        $this->fileSystem = new \Glowpointzero\Pischi\Console\Component\Filesystem();

        $this
            ->setName($this::COMMAND_NAME)
            ->setAliases($this::COMMAND_ALIASES)
            ->setDescription($this::COMMAND_DESCRIPTION)
        ;
    }

    /**
     * Initializes the current command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // Assign input / output interfaces
        $this->inputInterface = $input;
        $this->io = new Style($input, $output);

        // Output title of the current command
        $this->io->title('Running ' . $this->getName() . ' ...');
    }

    /**
     * The 'interact' method
     * - loads configuration .json, in case the current instance has
     *   a 'configuration' option at all.
     * - overrides $this->configuration['foo'] with the according
     *   CLI option (if set)
     * - triggers the configuration validation.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        // Load/verify configuration, if the instance of the command features a configuration.
        $configurationFileInput = $input->getOption('configuration') ? $input->getOption('configuration') : $this->defaultConfigurationFileName;

        while (!$this->loadedConfigurationPath && $input->hasOption('configuration')) {
            $absoluteConfigurationFilePath = realpath($configurationFileInput);
            $configurationFileExists = $absoluteConfigurationFilePath && is_file($absoluteConfigurationFilePath);
            
            if (!$configurationFileExists) {
                $this->io->error(sprintf('Configuration file "%s" could not be found.', $configurationFileInput));
                $configurationFileInput = $this->io->ask('Indicate your configuration file');
                continue;
            }
            
            $configurationContents = @file_get_contents($absoluteConfigurationFilePath);
            if (!$configurationContents) {
                $this->io->error(sprintf('Configuration file "%s" could not be loaded.', $configurationFileInput));
                $configurationFileInput = $this->io->ask('Indicate a new configuration file');
                continue;
            }
            
            $loadedConfiguration = json_decode($configurationContents, true);
            if (!is_array($loadedConfiguration)) {
                $this->io->error(sprintf('Configuration file "%s" does not seem to be valid json.', $configurationFileInput));
                $configurationFileInput = $this->io->ask('Indicate a new configuration file');
                continue;
            }
            
            $this->configuration = array_merge($this->configuration, $loadedConfiguration);
            $this->loadedConfigurationPath = $absoluteConfigurationFilePath;
        }
        
        // Override configuration settings with certain, previously defined options
        foreach ($this->optionNamesOverridingConfiguration as $optionName)
        {
            if ($input->getOption($optionName)) {
                $this->configuration[$optionName] = $input->getOption($optionName);
            }
        }
        
        $this->validateConfiguration();
    }

    /**
     * Validates the current configuration stored in $this->configuration
     * according to the configuration validation in $this->configurationValidation
     */
    protected function validateConfiguration()
    {
        foreach ($this->configurationValidation as $configurationParameter => $validationMethods)
        {
            $configurationValue = $this->configuration[$configurationParameter];
            foreach ($validationMethods as $validationType) {
                $hasBeenValidated = false;
                $validates = true;
                while (!$validates || !$hasBeenValidated) {
                    $hasBeenValidated = true;
                    $validates = true;
                    
                    $valueExistsInFilesystem = false;
                    $valueExistsAsFile = false;
                    $alternativeValueSuggestions = [];
                    
                    if (substr($validationType, 0, 4) === 'file' || substr($validationType, 0, 9) === 'directory') {
                        $valueExistsInFilesystem = realpath($configurationValue) ? true : false;
                        $valueExistsAsFile = $valueExistsInFilesystem && is_file(realpath($configurationValue));
                        $valueExistsAsDirectory = $valueExistsInFilesystem && is_dir(realpath($configurationValue));
                    }

                    if ($validationType === 'fileExists' && !$valueExistsAsFile) {
                        $validates = false;

                        $this->io->error(
                            sprintf(
                                'The configuration parameter "%s" (%s) does not point to a file using the current working directory.',
                                $configurationParameter,
                                $configurationValue
                            )
                        );
                        
                        $alternativePath = realpath(dirname($this->loadedConfigurationPath) . DIRECTORY_SEPARATOR . $configurationValue);
                        if ($alternativePath) {
                            $alternativeValueSuggestions[] = $alternativePath;
                        }
                    }

                    if ($validationType === 'fileDoesNotExist' && $valueExistsInFilesystem) {
                        $validates = false;
                        
                        $this->io->error(
                            sprintf(
                                'The configuration parameter "%s" (%s) points to an existing file/directory, but shouldn\'t (using the current working directory). Delete it or reset the parameter.',
                                $configurationParameter,
                                $configurationValue
                            )
                        );

                        $alternativeValueSuggestions[] = dirname($this->loadedConfigurationPath) . DIRECTORY_SEPARATOR . $configurationValue;
                    }

                    if ($validationType === 'directoryExists' && !$valueExistsAsDirectory) {
                        $validates = false;
                        
                        $this->io->error(
                            sprintf(
                                'The configuration parameter "%s" (%s) does not point to a directory using the current working directory.',
                                $configurationParameter,
                                $configurationValue
                            )
                        );
                        
                        $alternativePath = realpath(dirname($this->loadedConfigurationPath) . DIRECTORY_SEPARATOR . $configurationValue);
                        if ($alternativePath) {
                            $alternativeValueSuggestions[] = $alternativePath;
                        }
                    }
                    
                    if (!$validates) {
                        $configurationValue = false;
                        $this->io->say(sprintf('Please (re)set the configuration parameter "%s"', $configurationParameter));
                        
                        if ($alternativeValueSuggestions) {
                            $alternativeValueSuggestions[] = 'Reset manually';
                            $configurationValue = $this->io->choice('Here are some suggestions', $alternativeValueSuggestions);
                            if (array_search($configurationValue, $alternativeValueSuggestions) === count($alternativeValueSuggestions)-1) {
                                $configurationValue = false;
                            }
                        }
                        
                        if (!$configurationValue) {
                            $configurationValue = $this->io->ask('New value');
                        }
                        
                        $this->configuration[$configurationParameter] = $configurationValue;
                    }
                }
            }
        }
    }

    /**
     * Takes the given configuration parameters and - if they don't exist
     * as file, try to resolve them relative to the loaded configuration file.
     *
     * @param array $configurationParameterNames
     */
    protected function makeInvalidPathsRelativeToConfiguration(array $configurationParameterNames = [])
    {
        foreach ($configurationParameterNames as $parameterName) {
            if (empty($this->fusingConfiguration[$parameterName])
                || $this->fileSystem->isAbsolutePath($this->fusingConfiguration[$parameterName])) {
                continue;
            }

            $absolutePathCurrentWorkingDirectory = realpath($this->fusingConfiguration[$parameterName]);
            if ($absolutePathCurrentWorkingDirectory) {
                $this->fusingConfiguration[$parameterName] = $absolutePathCurrentWorkingDirectory;
                continue;
            }

            if (!$this->loadedConfigurationFilePath) {
                continue;
            }

            $absolutePathRelativeToConf = realpath(
                dirname($this->loadedConfigurationFilePath)
                . DIRECTORY_SEPARATOR
                . $this->fusingConfiguration[$parameterName]
            );

            if ($absolutePathRelativeToConf) {
                $this->fusingConfiguration[$parameterName] = $absolutePathRelativeToConf;
            }
        }
    }
}
