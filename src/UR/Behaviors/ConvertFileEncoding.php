<?php

namespace UR\Behaviors;


trait ConvertFileEncoding
{
    /**
     * convert file to Utf8 encoding.
     * If success, the original file will be renamed to ${file}.orig,
     * and new file will be overwrited to orginal file name as ${file}
     *
     * @param $filePath
     * @return bool return true if success, else return false
     */
    public function convertToUtf8($filePath)
    {
        if (null == $filePath) {
            return false;
        }

        $result = shell_exec(__DIR__ . '/../../../app/convert_to_utf8.sh' . ' ' . $filePath);

        if (null == $result) {
            return false;
        }

        echo "finish, result: " . $result . "\n";

        if (strpos($result, "[error]") > -1) {
            return false;
        }

        if (strpos($result, "[success]") > -1) {
            return true;
        }

        return true;
    }
}