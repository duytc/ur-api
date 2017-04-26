<?php

namespace UR\Worker\Workers;

use Leezy\PheanstalkBundle\Proxy\PheanstalkProxyInterface;
use Monolog\Logger;
use Pheanstalk_Job;
use stdClass;
use Symfony\Component\Process\Process;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSetImportJobManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Exception\SqlLockTableException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Command\CommandService;
use UR\Worker\Manager;

class LoadingDataFromFileToDataImportTable
{
    const PHP_BIN = 'php app/console';
// in prod would probably be a symfony console command with args
    const RUN_COMMAND = 'ur:data-set:load-file';

    /**
     * @var ImportHistoryManagerInterface
     */
    private $importHistoryManager;

    /**
     * @var DataSetImportJobManagerInterface
     */
    private $dataSetImportJobManager;

    /**
     * @var Logger $logger
     */
    private $logger;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;

    /**
     * @var ConnectedDataSourceManagerInterface
     */
    private $connectedDataSourceManager;

    /**
     * @var PheanstalkProxyInterface
     */
    private $queue;

    private $timeout;

    private $commandService;

    private $jobDelay;

    function __construct(CommandService $commandService, Logger $logger, DataSourceEntryManagerInterface $dataSourceEntryManager, ConnectedDataSourceManagerInterface $connectedDataSourceManager, PheanstalkProxyInterface $queue, ImportHistoryManagerInterface $importHistoryManager, DataSetImportJobManagerInterface $dataSetImportJobManager, $timeout, $jobDelay)
    {
        $this->commandService = $commandService;
        $this->logger = $logger;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->queue = $queue;
        $this->importHistoryManager = $importHistoryManager;
        $this->dataSetImportJobManager = $dataSetImportJobManager;
        $this->timeout = $timeout;
        $this->jobDelay = $jobDelay;
    }

    /**
     * @param stdClass $params
     * @param Pheanstalk_Job $job
     * @param $tube
     */
    public function loadingDataFromFileToDataImportTable(stdClass $params, Pheanstalk_Job $job, $tube)
    {
        $importJobId = $params->importJobId;
        $dataSetId = $params->dataSetId;

        try {
            /**@var DataSetImportJobInterface $exeCuteJob */
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

        $dataSourceEntryId = $params->entryId;
        $connectedDataSourceId = $params->connectedDataSourceId;


        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->logger->warning(sprintf('Data Source Entry %d not found (may be deleted before)', $dataSourceEntryId));
            return;
        }

        /**@var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            $this->logger->warning(sprintf('Connected Data Source %d not found (may be deleted before)', $connectedDataSourceId));
            return;
        }

        /* creating import history */
        $importHistoryEntity = $this->importHistoryManager->createImportHistoryByDataSourceEntryAndConnectedDataSource($dataSourceEntry, $connectedDataSource);

        $logFile = $this->commandService->createLogFile('import_log', $dataSetId);

        $fp = fopen($logFile, 'a');

        $loadDataCommand = sprintf('%s %d %d %d',
            $this->commandService->getAppConsoleCommand(self::RUN_COMMAND),
            $connectedDataSourceId,
            $dataSourceEntryId,
            $importHistoryEntity->getId()
        );

        $process = new Process($loadDataCommand);

        $process->setTimeout($this->timeout);

        try {
            $process->mustRun(
                function ($type, $buffer) use (&$fp) {
                    fwrite($fp, $buffer);
                }
            );
        } catch (SqlLockTableException $exception) {
            $this->logger->warning('Table is locked. Putting job back into the queue');
            $this->queue->putInTube($tube, $job->getData(), PheanstalkProxyInterface::DEFAULT_PRIORITY, $this->jobDelay, Manager::EXECUTION_TIME_THRESHOLD);
        } catch (\Exception $e) {
            // top level log is very clean. This is the supervisor log but it provides the name of the specific file for more debugging
            // if the admin wants to know more about the failure, they have the exact log file
            $this->logger->error($e->getMessage());
            $this->logger->warning(sprintf('Execution run failed (exit code %d), please see %s for more details', $process->getExitCode(), $logFile));
            if ($importHistoryEntity->getId() !== null) {
                $this->importHistoryManager->delete($importHistoryEntity);
            }
        } finally {
            fclose($fp);
        }

        $this->dataSetImportJobManager->delete($exeCuteJob);
    }
}