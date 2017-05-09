<?php

namespace UR\Worker\Workers;

use Leezy\PheanstalkBundle\Proxy\PheanstalkProxyInterface;
use Monolog\Logger;
use Pheanstalk_Job;
use stdClass;
use Symfony\Component\Process\Process;
use UR\DomainManager\DataSetImportJobManagerInterface;
use UR\Service\Command\CommandService;
use UR\Worker\Manager;

class AlterImportDataTable
{
    const TEMP_FILE_NAME = 'alter_table_config';
    const LOG_FILE_NAME = 'alter_table_log';
    const RUN_COMMAND = 'ur:internal:data-import-table:alter';

    /**
     * @var DataSetImportJobManagerInterface
     */
    private $dataSetImportJobManager;

    /**
     * @var PheanstalkProxyInterface
     */
    private $queue;

    private $logger;

    private $commandService;

    private $timeout;

    private $jobDelay;

    /**
     * AlterImportDataTable constructor.
     * @param CommandService $commandService
     * @param $queue
     * @param Logger $logger
     * @param DataSetImportJobManagerInterface $dataSetImportJobManager
     * @param $timeout
     */
    public function __construct(CommandService $commandService, $queue, Logger $logger, DataSetImportJobManagerInterface $dataSetImportJobManager, $timeout, $jobDelay)
    {
        $this->commandService = $commandService;
        $this->queue = $queue;
        $this->logger = $logger;
        $this->dataSetImportJobManager = $dataSetImportJobManager;
        $this->timeout = $timeout;
        $this->jobDelay = $jobDelay;
    }

    public function alterDataSetTable(stdClass $params, Pheanstalk_Job $job, $tube)
    {
        $importJobId = $params->importJobId;
        $dataSetId = $params->dataSetId;

        try {
            $exeCuteJob = $this->dataSetImportJobManager->getExecuteImportJobByDataSetId($dataSetId);
        } catch (\Exception $exception) {
            /*job not found*/
            return;
        }

        /*
         * check if data set has another job before this job, put job back to queue
         * this make sure jobs are executes in order
         * very important: must set TTR (time to run) when putting back to tube
         */
        if ($exeCuteJob->getJobId() !== $importJobId) {
            $this->logger->notice(sprintf('DataSet with id %d is busy, putted job back into the queue', $dataSetId));
            $this->queue->putInTube($tube, $job->getData(), PheanstalkProxyInterface::DEFAULT_PRIORITY, $this->jobDelay, Manager::EXECUTION_TIME_THRESHOLD);
            return;
        }

        $executionRunId = microtime(true);

        $logFile = $this->commandService->createLogFile(sprintf('%s_%d', self::LOG_FILE_NAME, $dataSetId), $executionRunId);
        $fp = fopen($logFile, 'a');

        $alterTableConfigFile = $this->commandService->createTempFile(self::TEMP_FILE_NAME, $executionRunId);

        $fp1 = fopen($alterTableConfigFile, 'a');
        fwrite($fp1, json_encode($params));

        $alterTableCommand = sprintf('%s %s',
            $this->commandService->getAppConsoleCommand(self::RUN_COMMAND),
            $alterTableConfigFile
        );

        $process = new Process($alterTableCommand);
        $process->setTimeout($this->timeout);

        try {
            $process->mustRun(
                function ($type, $buffer) use (&$fp) {
                    fwrite($fp, $buffer);
                }
            );
        } catch (\Exception $e) {
            // top level log is very clean. This is the supervisor log but it provides the name of the specific file for more debugging
            // if the admin wants to know more about the failure, they have the exact log file
            $this->logger->error($e->getMessage());
            $this->logger->warning(sprintf('Execution run failed, please see %s for more details', $logFile));
        } finally {
            fclose($fp);
        }

        $this->dataSetImportJobManager->delete($exeCuteJob);

        if (is_file($alterTableConfigFile)) {
            unlink($alterTableConfigFile);
        }
    }
}