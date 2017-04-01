<?php

namespace UR\Behaviors;


trait FileUtilsTrait
{
    /**
     * convert file to Utf8 encoding.
     * If success, the original file will be renamed to ${file}.orig,
     * and new file will be overwrited to orginal file name as ${file}
     *
     * @param $filePath
     * @param $kernelRootDir
     * @return bool return true if success, else return false
     */
    public function convertToUtf8($filePath, $kernelRootDir)
    {
        if (null == $filePath) {
            return false;
        }

        if (!file_exists($filePath)) {
            return false;
        }

        $result = shell_exec(sprintf('%s "%s"', ($kernelRootDir . '/convert_to_utf8.sh'), $filePath)); // important: put filePath into "" allows special character in filePath

        if (null == $result) {
            return false;
        }

        if (strpos($result, "[error]") > -1) {
            return false;
        }

        if (strpos($result, "[success]") > -1) {
            return true;
        }

        return true;
    }

    /**
     * escapeFileNameContainsSpecialCharacters
     * e.g:
     * - 'Matchflow_Report_#2 (1).xlsx' => 'Matchflow_Report__2__1_.xlsx'
     *
     * @param string $filename
     * @return mixed|string
     */
    public function escapeFileNameContainsSpecialCharacters($filename)
    {
        $escapedFilename = preg_replace('/[^-_.\(\)a-z0-9]/i', '_', $filename);

        return (null === $escapedFilename) ? $filename : $escapedFilename;
    }
}