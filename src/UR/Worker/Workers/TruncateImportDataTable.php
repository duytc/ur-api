<?php

namespace UR\Worker\Workers;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxyInterface;
use Monolog\Logger;
use Pheanstalk_Job;
use stdClass;
use UR\Bundle\ApiBundle\Behaviors\UpdateDataSetTotalRowTrait;
use UR\DomainManager\DataSetImportJobManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\Import\LoadingDataService;
use UR\Worker\Manager;

class TruncateImportDataTable
{
    use UpdateDataSetTotalRowTrait;
    /**
     * @var DataSetManagerInterface $dataSetManager
     */
    private $dataSetManager;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    private $conn;

    private $queue;

    private $logger;

    private $jobDelay;

    private $dataSetImportJobManager;

    /**
     * @var ImportHistoryManagerInterface
     */
    protected $importHistoryManager;

    /**
     * @var LoadingDataService
     */
    protected $loadingDataService;

    /**
     * AlterImportDataTable constructor.
     * @param DataSetManagerInterface $dataSetManager
     * @param PheanstalkProxyInterface $queue
     * @param EntityManagerInterface $entityManager
     * @param Logger $logger
     * @param $jobDelay
     * @param DataSetImportJobManagerInterface $dataSetImportJobManager
     * @param ImportHistoryManagerInterface $importHistoryManager
     * @param LoadingDataService $loadingDataService
     */
    public function __construct(DataSetManagerInterface $dataSetManager, EntityManagerInterface $entityManager, PheanstalkProxyInterface $queue, Logger $logger, $jobDelay, DataSetImportJobManagerInterface $dataSetImportJobManager, ImportHistoryManagerInterface $importHistoryManager, LoadingDataService $loadingDataService)
    {
        $this->dataSetManager = $dataSetManager;
        $this->entityManager = $entityManager;
        $this->conn = $entityManager->getConnection();
        $this->queue = $queue;
        $this->logger = $logger;
        $this->jobDelay = $jobDelay;
        $this->dataSetImportJobManager = $dataSetImportJobManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->loadingDataService = $loadingDataService;
    }

    public function truncateDataSetTable(stdClass $params, Pheanstalk_Job $job, $tube)
    {
        $importJobId = $params->importJobId;
        $dataSetId = $params->dataSetId;

        try {
            $exeCuteJob = $this->dataSetImportJobManager->getExecuteImportJobByDataSetId($dataSetId);
            $expireDate = $exeCuteJob->getDataSet()->getJobExpirationDate();

            if ($exeCuteJob->getCreatedDate() < $expireDate) {
                $this->logger->notice(sprintf('Ignore job (ID: %s) because of expiration', $job->getId()));
                $this->dataSetImportJobManager->delete($exeCuteJob);
                return;
            }
        } catch (\Exception $exception) {
            /*job not found*/
            return;
        }

        /*
         * check if data set has another job before this job, put job back to queue
         * this make sure jobs are executes in order
         */
        if ($exeCuteJob->getJobId() !== $importJobId) {
            $this->logger->notice(sprintf('DataSet with id %d is busy, putted job back into the queue', $dataSetId));
            $this->queue->putInTube($tube, $job->getData(), PheanstalkProxyInterface::DEFAULT_PRIORITY, $this->jobDelay, Manager::EXECUTION_TIME_THRESHOLD);
            return;
        }

        try {
            /**
             * @var DataSetInterface $dataSet
             */
            $dataSet = $this->dataSetManager->find($dataSetId);

            if ($dataSet === null) {
                throw new \Exception(sprintf('Cannot find Data Set with id: %s', $dataSetId));
            }

            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());;
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

            // check if table not existed
            if (!$dataTable) {
                return;
            }

            $truncateSQL = sprintf("TRUNCATE %s", $dataTable->getName());
            $this->conn->exec($truncateSQL);
            $this->updateDataSetTotalRow($dataSet->getId());
            $this->updateConnectedDataSourceTotalRow($dataSet);

            $importHistories = $this->importHistoryManager->getImportedHistoryByDataSet($dataSet);
//            $reloadImportHistories = [];
            /**
             * @var ImportHistoryInterface $importHistory
             */
            foreach ($importHistories as $importHistory) {
                $this->importHistoryManager->delete($importHistory);
//                $reloadImportHistories[$importHistory->getDataSourceEntry()->getId()] = $importHistory;
            }

//            $this->loadingDataService->reloadDataAugmentationWhenUndo($reloadImportHistories);
            $this->logger->notice(sprintf('Truncate data set %s with table name %s', $dataSetId, $dataTable->getName()));
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->notice('error while truncate table');
        }

        $this->dataSetImportJobManager->delete($exeCuteJob);
    }

    /**
     * @return EntityManagerInterface
     */
    protected function getEntityManager()
    {
        return $this->entityManager;
    }
}