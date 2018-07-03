<?php
namespace Glowpointzero\Pischi;

use Glowpointzero\Pischi\Console\Component\Filesystem;

class Hasher
{
    /**
     * @param string $rootDirectory
     * @param array $hashingConfiguration Hashing configuration options
     * @return array|bool                 A single-level array, where the key is the relative file path
     *                                    and the value their respective hash.
     */
    public function generateFileHashesRecursively(string $rootDirectory, $hashingConfiguration = null)
    {
        if (!is_array($hashingConfiguration)) {
            $hashingConfiguration = [
                'sourceDirectory' => $rootDirectory,
                'excludedPaths' => [],
                'excludedPatterns' => []
            ];
        }
        
        $rootDirectory = realpath($rootDirectory);
        $absSourceDirectory = realpath($hashingConfiguration['sourceDirectory']);
        
        if ($rootDirectory === false || !is_dir($rootDirectory)) {
            return false;
        }
        $filesystem = new Filesystem();
        $fileHashes = [];
        $excludedPaths = $hashingConfiguration['excludedPaths'];
        $excludedPatterns = $hashingConfiguration['excludedPatterns'];
        foreach ($filesystem->getFilesInDirectory($rootDirectory) as $fileName) {
            $absFilePath = realpath($rootDirectory . '/' . $fileName);
            $targetIsDirectory = is_dir($absFilePath);
            
            $comparableFilePath = str_replace('\\', '/', str_replace($absSourceDirectory, '', $absFilePath));
            $comparableFilePath = ltrim($comparableFilePath, ' \\/');
            
            if (in_array($comparableFilePath, $excludedPaths)) {
                continue;
            }
            
            if (!$targetIsDirectory) {
                foreach ($excludedPatterns as $pattern) {
                    if (preg_match($pattern, $comparableFilePath)) {
                        continue 2;
                    }
                }
            }

            if ($targetIsDirectory) {
                $directoryContentHashes = $this->generateFileHashesRecursively($absFilePath, $hashingConfiguration);
                foreach ($directoryContentHashes as $fileOrFolderName => $fileOrFolderHash) {
                    $fileHashes[$fileName . '/' . $fileOrFolderName] = $fileOrFolderHash;
                }
                continue;
            }
            $fileHashes[$fileName] = md5_file($absFilePath);
        }

        return $fileHashes;
    }

    /**
     * Checks any given path against a set of hashes. Multiple hashes may be taken
     * into consideration as a path for a directory may contain multiple sub-
     * directories (and files) whose hashes will be compared.
     *
     * @param string $path
     * @param array $expectedHashes
     * @param array $comparingHashes
     * @param array|null $nonMatchingPaths
     * @return bool
     */
    public static function hashesForPathMatch(
        string $path,
        array $expectedHashes,
        array $comparingHashes,
        array &$nonMatchingPaths = null
    ) {
        $nonMatchingPaths = [];
        $verifiedPaths = [];

        foreach ($expectedHashes as $hashPath => $hash) {
            if (substr($hashPath, 0, strlen($path)) === $path) {
                if (!isset($comparingHashes[$hashPath])) {
                    $nonMatchingPaths[$hashPath] = 'No hash for comparison found.';
                    continue;
                }

                $verifiedPaths[$hashPath] = $hash;
                
                if ($hash !== $comparingHashes[$hashPath]) {
                    $nonMatchingPaths[$hashPath] = 'Hash mismatch.';
                    continue;
                }
            }
        }

        if (count($verifiedPaths) === 0) {
            $nonMatchingPaths[$path] = 'No hash found to verify.';
        }

        return count($nonMatchingPaths) === 0;
    }
}
