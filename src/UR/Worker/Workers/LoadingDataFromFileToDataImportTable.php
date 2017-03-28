<?php

namespace UR\Worker\Workers;

use Monolog\Logger;
use StdClass;
use Symfony\Component\Process\Process;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Exception\SqlLockTableException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;

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

    private $queue;

    private $logDir;


    function __construct(Logger $logger, DataSourceEntryManagerInterface $dataSourceEntryManager, ConnectedDataSourceManagerInterface $connectedDataSourceManager, $queue, $logDir, ImportHistoryManagerInterface $importHistoryManager)
    {
        $this->logger = $logger;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->queue = $queue;
        $this->logDir = $logDir;
        $this->importHistoryManager = $importHistoryManager;
    }

    public function loadingDataFromFileToDataImportTable(StdClass $params, $job, $tube)
    {
        $connectedDataSourceId = $params->connectedDataSourceId;
        $entryId = $params->entryId;
        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($entryId);
        /**@var ConnectedDataSourceInterface $connectedDataSource */
        try {
            $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        } catch (\Exception $exception) {
            $this->logger->warning($exception->getMessage());
        }

        /* creating import history */
        $importHistoryEntity = $this->importHistoryManager->createImportHistoryByDataSourceEntryAndConnectedDataSource($dataSourceEntry, $connectedDataSource);

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }

        $logFile = sprintf('%s/import_log_%s.log', $this->logDir, $importHistoryEntity->getId());

        $fp = fopen($logFile, 'a');

        $process = new Process(sprintf('%s %s %d %d %d', self::PHP_BIN, self::RUN_COMMAND, $connectedDataSourceId, $entryId, $importHistoryEntity->getId()));

        try {
            $process->mustRun(
                function ($type, $buffer) use (&$fp) {
                    fwrite($fp, $buffer);
                }
            );
        } catch (SqlLockTableException $exception) {
            $this->logger->warning('put job back to tube');
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
}