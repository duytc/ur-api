<?php

namespace UR\Worker\Workers;

use Monolog\Logger;
use SplFileObject;
use stdClass;

class CSVFixWindowLineFeed
{
    /**
     * @var Logger $logger
     */
    private $logger;

    function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param stdClass $params
     */
    public function fixWindowLineFeed(stdClass $params)
    {
        $this->logger->info('Starting fix Window Line Feed');

        $filePath = $params->filePath;
        $copyPath = $filePath . 'copy' . time();
        rename($filePath, $copyPath);
        $this->logger->info('Copy file to ' . $copyPath);

        $file = fopen($filePath, 'w');

        foreach (new SplFileObject($copyPath) as $lineNumber => $lineContent) {
            $fixCLRFContent = str_replace("\r", "\n", $lineContent);
            fwrite($file, $fixCLRFContent);
            if ($lineNumber % 1000 == 0) {
                $this->logger->info('Process line ' . $lineNumber);
            }
        }
        fclose($file);

        if (is_file($copyPath)) {
            unlink($copyPath);
        }

        $this->logger->info('Finish fix Window Line Feed');
    }
}