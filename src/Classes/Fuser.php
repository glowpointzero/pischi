<?php
namespace Glowpointzero\Pischi;

use Glowpointzero\Pischi\Console\Component\Filesystem;

class Fuser
{
    protected $hashCache = [];

    const DEFAULT_FUSING_STRATEGY = [
        'hashMismatch' => 'ask',
        'expectExistingTarget' => true
    ];

    public function fuse(
        \Glowpointzero\Pischi\Console\Style $outputInterface,
        string $sourceDirectory,
        string $targetDirectory,
        array $hashes,
        array $defaultFusingStrategy = [],
        array $fusingStrategies = [],
        $dryRun = true
    ) {

        $fileSystem = new Filesystem();
        $sourceFiles = $fileSystem->getFilestructureRecursively($sourceDirectory);
        $pathsProcessedAsDirectory = [];
        
        foreach ($sourceFiles as $relativeFilePath) {

            // Check, whether the current file path is located in a previously
            // swapped directory and therefore doesn't need to be processed anymore.
            foreach ($pathsProcessedAsDirectory as $directoryPath) {
                if (substr($relativeFilePath, 0, strlen($directoryPath)) === $directoryPath) {
                    continue 2;
                }
            }

            $fusingStrategy = self::consolidateFusingStrategy(
                $relativeFilePath,
                $defaultFusingStrategy,
                $fusingStrategies
            );            
            
            $this->fuseSinglePath(
                $outputInterface,
                $relativeFilePath,
                $hashes,
                $sourceDirectory,
                $targetDirectory,
                $fusingStrategy,
                $pathsProcessedAsDirectory,
                $dryRun
            );
        }
    }

    /**
     * Retrieves a definite fusing strategy for a given file or directory.
     *
     * @param type $filePath
     * @param type $defaultFusingStrategy
     * @param type $fusingStrategies
     * @return type
     */
    public static function consolidateFusingStrategy($filePath, $defaultFusingStrategy, $fusingStrategies)
    {
        $fusingStrategy = array_merge(
            self::DEFAULT_FUSING_STRATEGY,
            $defaultFusingStrategy
        );
        if (array_key_exists($filePath, $fusingStrategies)) {
            $fusingStrategy = array_merge($fusingStrategy, $fusingStrategies[$filePath]);
        }
        foreach ($fusingStrategies as $fusingStrategyKey => $fusingStrategySettings) {
            $fusingStrategyHasRegexMatcher = preg_match('/^\/.*\/[a-zA-Z]*$/', $fusingStrategyKey);
            if ($fusingStrategyHasRegexMatcher && preg_match($fusingStrategyKey, $filePath)) {
                $fusingStrategy = array_merge($fusingStrategy, $fusingStrategySettings);
            }
        }
        
        // If the target file/directory is not expected to exist, the
        // hash for it probably doesn't exist either. We can safely
        // assume this and ignore any hash mismatches.
        if (!$fusingStrategy['expectExistingTarget']) {
            $fusingStrategy['hashMismatch'] = 'ignore';
        }
        
        return $fusingStrategy;
    }

    protected function fuseSinglePath(
        \Glowpointzero\Pischi\Console\Style $outputInterface,
        string $relativeFilePath,
        array $hashes,
        string $sourceDirectory,
        string $targetDirectory,
        array $fusingStrategy,
        array &$pathsProcessedAsDirectory,
        bool $dryRun
    ) {
        $fileSystem = new Filesystem();

        $currentTargetHashes = $this->getHashesForDirectory($targetDirectory, $outputInterface);
        $nonMatchingPathHashes = [];

        Hasher::hashesForPathMatch($relativeFilePath, $hashes, $currentTargetHashes, $nonMatchingPathHashes);

        // Initialize source & target file/directory
        $absoluteSourceFilePath = realpath($sourceDirectory . '/' . $relativeFilePath);
        $targetFilePath = $targetDirectory . '/' . $relativeFilePath;
        $absoluteTargetFilePath = realpath($targetFilePath);
        $targetExists = $absoluteTargetFilePath !== false;
        $targetIsADirectory = $targetExists && is_dir($absoluteTargetFilePath);
        $targetTypeString = $targetIsADirectory ? 'directory' : 'file';
        $sourceFileIsADirectory = is_dir($absoluteSourceFilePath);

        if (!$targetExists && $fusingStrategy['expectExistingTarget']) {
            $continuationOptions = [
                sprintf('Skip this %s.', $targetTypeString),
                sprintf('Create the %s in the target directory.', $targetTypeString)
            ];
            $continuationChoiceIndex = false;
            while (!is_int($continuationChoiceIndex)) {
                $outputInterface->warning(sprintf(
                    'The %s "%s" doesn\'t exist in the target directory, but - '
                    . 'according to the fusing configuration - is expected to.',
                    $targetTypeString,
                    $relativeFilePath
                ));
                $continuationChoice = $outputInterface->choice('How to continue?', $continuationOptions);
                $continuationChoiceIndex = array_search($continuationChoice, $continuationOptions);
            }
            if ($continuationChoiceIndex === 0) {
                return false;
            }
        }

        // Copy *directory* into empty target - Hashes do *NOT* need to match here!
        if (!$targetExists && $sourceFileIsADirectory) {
            $pathsProcessedAsDirectory[] = $relativeFilePath;
            $outputInterface->say(sprintf('Creating & copying directory "%s"', $relativeFilePath), null, null, false);
            if (!$dryRun) {
                $fileSystem->mkdir($targetFilePath);
                $fileSystem->mirror($absoluteSourceFilePath, $targetFilePath);
            }
            return true;
        }
        // Copy *file* into empty target - Hashes do *NOT* need to match here!
        if (!$targetExists) {
            $outputInterface->say(sprintf('Copying "%s"', $relativeFilePath), null, null, false);
            if (!$dryRun) {
                $fileSystem->copy($absoluteSourceFilePath, $targetFilePath);
            }
            return true;
        }

        $swapDirectory = isset($fusingStrategy['swapDirectory']) && $fusingStrategy['swapDirectory'];
        if ($swapDirectory) {
            $pathsProcessedAsDirectory[] = $relativeFilePath;
        }
        // Skip directory, if it shouldn't just be swapped,
        // files should/will be handled individually.
        if ($sourceFileIsADirectory && !$swapDirectory) {
            return null;
        }

        $warningsConfirmed = $this->makeUserAwareOfAnyConflicts(
            $outputInterface,
            $relativeFilePath,
            $fusingStrategy,
            $nonMatchingPathHashes,
            $sourceFileIsADirectory,
            $targetIsADirectory
        );

        if (!$warningsConfirmed) {
            return false;
        }

        // Swap whole directory
        if ($sourceFileIsADirectory) {
            $outputInterface->say(sprintf('Replacing (swapping) directory "%s"', $relativeFilePath), null, null, false);
            if (!$dryRun) {
                $fileSystem->remove($absoluteTargetFilePath);

                $fileSystem->mirror(
                    $absoluteSourceFilePath,
                    $absoluteTargetFilePath
                );
            }

            return true;
        }

        $outputInterface->say(sprintf('Replacing file "%s"', $relativeFilePath), null, null, false);
        if (!$dryRun) {
            $fileSystem->copy($absoluteSourceFilePath, $absoluteTargetFilePath, true);
        }

        return true;
    }

    /**
     * Retrieves all file hashes for a given directory recursively.
     * Generates the hashes, if none are available in the runtime-cache ($this->hashCache)
     *
     * @param $directoryName
     * @param Console\Style $outputInterface
     * @return mixed
     */
    protected function getHashesForDirectory($directoryName, \Glowpointzero\Pischi\Console\Style $outputInterface)
    {
        $hasher = new Hasher();
        if (!isset($this->hashCache[$directoryName])) {
            $outputInterface->processingStart(sprintf('Generating hashes for %s recursively. This might take a solid minute or two!', $directoryName));
            $this->hashCache[$directoryName] = $hasher->generateFileHashesRecursively($directoryName);
            $outputInterface->processingEnd(sprintf('Done', $directoryName));
        }
        return $this->hashCache[$directoryName];
    }

    /**
     * This method should be called to check the integrity of the
     * source/target to merge. It checks whether both are of the
     * same type (directory, file) and compares hashes. If anything
     * seems off, the user gets notified and may decide how to
     * continue.
     *
     * @param \Glowpointzero\Pischi\Console\Style $outputInterface
     * @param string $relativeFilePath
     * @param array $fusingStrategy
     * @param array $nonMatchingPathHashses
     * @param bool $sourceFileIsADirectory
     * @param bool $targetIsADirectory
     * @return boolean
     */
    protected function makeUserAwareOfAnyConflicts(
        \Glowpointzero\Pischi\Console\Style $outputInterface,
        string $relativeFilePath,
        array $fusingStrategy,
        array $nonMatchingPathHashses,
        bool $sourceFileIsADirectory,
        bool $targetIsADirectory
    ) {
        // Let the user confirm mismatching hashes
        if (count($nonMatchingPathHashses) && $fusingStrategy['hashMismatch'] !== 'ignore') {
            $outputInterface->say('Hash mismatch detected!');
            foreach ($nonMatchingPathHashses as $nonMatchingPath => $reason) {
                $outputInterface->say(sprintf('%s: %s', $nonMatchingPath, $reason));
            }
            $nextPossibleSteps = [
                'Fuse this path anyway.',
                'Do *not* fuse this path.'
            ];
            if ($sourceFileIsADirectory || $targetIsADirectory) {
                $nextPossibleSteps[] = 'List paths whose hashes don\'t match.';
            }
            $nextStepIndex = -1;

            while ($nextStepIndex < 0 || $nextStepIndex > 1) {
                $nextStep = $outputInterface->choice(
                    'What do you want to do next?',
                    $nextPossibleSteps
                );
                $nextStepIndex = array_search($nextStep, $nextPossibleSteps);

                if ($nextStepIndex === 2) {
                    foreach ($nonMatchingPathHashses as $path => $hash) {
                        $outputInterface->say($path);
                    }
                }
            }

            if ($nextStepIndex === 1) {
                return false;
            }
        }

        // Let the user confirm mismatching file/directory paths
        if ($sourceFileIsADirectory && !$targetIsADirectory) {
            $continue = $outputInterface->confirm(
                sprintf(
                    'The source path of "%s" points to a directory, but there is a '
                    . 'file by the same name in the target path. Continue (n = skip)?',
                    $relativeFilePath
                ),
                false
            );
            if (!$continue) {
                return false;
            }
        }

        if (!$sourceFileIsADirectory && $targetIsADirectory) {
            $continue = $outputInterface->confirm(
                sprintf(
                    'The source path of "%s" points to a regular file, but there is a '
                    . 'directory by the same name in the target path. Continue (n = skip)?',
                    $relativeFilePath
                ),
                false
            );
            if (!$continue) {
                return false;
            }
        }

        return true;
    }
}
