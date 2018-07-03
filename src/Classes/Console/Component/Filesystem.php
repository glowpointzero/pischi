<?php
namespace Glowpointzero\Pischi\Console\Component;

class Filesystem extends \Symfony\Component\Filesystem\Filesystem
{
    /**
     * Holds paths to all temporary files created at runtime
     *
     * @var array
     */
    protected $temporaryFiles = [];

    public function __destruct()
    {
        $this->remove($this->temporaryFiles);
    }

    /**
     * Gets the file (list) out of any given directory.
     *
     * @param string $directory The starting directory
     * @param string $filterPattern A regex filter pattern
     * @param array $typesFilter 'files', 'directories', or both (default)
     * @return bool|array
     */
    public function getFilesInDirectory($directory, $filterPattern = '', $typesFilter = ['files', 'directories'])
    {
        // Get all files
        $files = scandir($directory);
        if ($files === false) {
            return false;
        }
        $currentDirPos = array_search('.', $files);
        if ($currentDirPos !== false) {
            unset($files[$currentDirPos]);
        }
        $upperDirPos = array_search('..', $files);
        if ($upperDirPos !== false) {
            unset($files[$upperDirPos]);
        }

        // Filter files by pattern and type
        foreach ($files as $fileNumber => $fileName) {
            if ($filterPattern) {
                if (!preg_match($filterPattern, $fileName)) {
                    unset($files[$fileNumber]);
                    continue;
                }
            }
            if (
                !in_array('files', $typesFilter)
                && is_file($directory . DIRECTORY_SEPARATOR . $fileName)) {
                unset($files[$fileNumber]);
                continue;
            }
            if (
                !in_array('directories', $typesFilter)
                && is_dir($directory . DIRECTORY_SEPARATOR . $fileName)) {
                unset($files[$fileNumber]);
                continue;
            }
        }

        // reset indexes, in case any files have been skipped
        $files = array_values($files);

        return $files;
    }

    /**
     * @return string
     */
    public function getUserHome()
    {
        return rtrim(getenv('HOME'), '/\\');
    }

    /**
     * Creates a new, temporary file
     *
     * @return type
     */
    public function createTemporaryFile()
    {
        $tempFilePath = $this->tempnam(sys_get_temp_dir(), 'pischi-');
        $this->temporaryFiles[] = $tempFilePath;
        return $tempFilePath;
    }

    /**
     * Retrieves file structure of a given directory recursively
     * and returns all files & folders as a single-level array.
     *
     * @param type $directory
     * @return string|array
     */
    public function getFilestructureRecursively($directory)
    {
        $filesAndFolders = [];

        if (!is_dir($directory)) {
            return $filesAndFolders;
        }

        foreach ($this->getFilesInDirectory($directory) as $fileName) {
            $filesAndFolders[] = $fileName;
            $absolutePath = realpath($directory . '/' . $fileName);
            if (is_dir($absolutePath)) {
                foreach ($this->getFilestructureRecursively($absolutePath) as $fileInSubdirectory) {
                    $filesAndFolders[] = $fileName . '/' . $fileInSubdirectory;
                }
            }
        }

        return $filesAndFolders;
    }
}
