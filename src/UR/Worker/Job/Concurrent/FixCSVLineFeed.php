<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use SplFileObject;

/*
 * TODO DELETE WHEN STABLE
 */

class FixCSVLineFeed implements JobInterface
{
    const JOB_NAME = 'fixCSVLineFeed';

    const PARAM_KEY_FILEPATH = 'filePath';

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $this->logger->info('Starting fix Window Line Feed');

        $filePath = $params->getRequiredParam(self::PARAM_KEY_FILEPATH);
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