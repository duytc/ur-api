<?php

namespace UR\Worker\Workers;

use Leezy\PheanstalkBundle\Proxy\PheanstalkProxyInterface;
use Monolog\Logger;
use Pheanstalk_Job;
use stdClass;
use Symfony\Component\Process\Process;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Exception\SqlLockTableException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Import\DataSetImportJobQueueService;

class LoadingDataFromFileToDataImportTable
{
    const PHP_BIN = 'php app/console';
// in prod would probably be a symfony console command with args
    const RUN_COMMAND = 'ur:data-set:load-file';

    /**
     * @var String
     * i.e prod or dev
     */
    private $env;

    /**
     * @var ImportHistoryManagerInterface
     */
    private $importHistoryManager;

    /**
     * @var DataSetImportJobQueueService
     */
    private $dataSetImportJobQueueService;

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

    private $logDir;

    private $rootDir;

    private $isDebug;

    private $timeout;

    function __construct($rootDir, $env, $isDebug, Logger $logger, DataSourceEntryManagerInterface $dataSourceEntryManager, ConnectedDataSourceManagerInterface $connectedDataSourceManager, PheanstalkProxyInterface $queue, $logDir, ImportHistoryManagerInterface $importHistoryManager, DataSetImportJobQueueService $dataSetImportJobQueueService, $timeout)
    {
        $this->rootDir = $rootDir;
        $this->env = $env;
        $this->isDebug = $isDebug;
        $this->logger = $logger;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->queue = $queue;
        $this->logDir = $logDir;
        $this->importHistoryManager = $importHistoryManager;
        $this->dataSetImportJobQueueService = $dataSetImportJobQueueService;
        $this->timeout = $timeout;
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

        $exeCuteJob = $this->dataSetImportJobQueueService->isExecuteJob($dataSetId, $importJobId, $this->logger);

        if (!$exeCuteJob instanceof DataSetImportJobInterface) {
            $this->queue->putInTube($tube, $job->getData(), 1024, 15);
            return;
        }

        $dataSourceEntryIds = $params->entryIds;
        $connectedDataSourceId = $params->connectedDataSourceId;

        foreach ($dataSourceEntryIds as $dataSourceEntryId) {

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

            if (!is_dir($this->logDir)) {
                mkdir($this->logDir, 0777, true);
            }

            $logFile = sprintf('%s/import_log_%s.log', $this->logDir, $importHistoryEntity->getId());

            $fp = fopen($logFile, 'a');

            // make sure command runs as same environment and allow NOTICE messages
            // INFO messages will be printed. Make sure all important logs are NOTICE and above
            $envFlags = sprintf('--env=%s -v', $this->env);
            if (!$this->isDebug) {
                $envFlags .= ' --no-debug';
            }

            $loadDataCommand = sprintf('%s %s %s %d %d %d',
                $this->getAppConsoleCommand(),
                self::RUN_COMMAND, $envFlags,
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
                $this->queue->putInTube($tube, $job->getData(), 0, 15);
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
        }

        $this->dataSetImportJobQueueService->deleteJob($exeCuteJob);
    }

    private function getAppConsoleCommand()
    {
        $pathToSymfonyConsole = $this->rootDir;

        $command = sprintf('php %s/console', $pathToSymfonyConsole);

        return $command;
    }
}