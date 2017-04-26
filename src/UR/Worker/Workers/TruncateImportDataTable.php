<?php

namespace UR\Worker\Workers;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxyInterface;
use Monolog\Logger;
use Pheanstalk_Job;
use stdClass;
use UR\DomainManager\DataSetImportJobManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Worker\Manager;

class TruncateImportDataTable
{
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
     * AlterImportDataTable constructor.
     * @param DataSetManagerInterface $dataSetManager
     * @param EntityManagerInterface $entityManager
     * @param $queue
     * @param Logger $logger
     * @param $jobDelay
     */
    public function __construct(DataSetManagerInterface $dataSetManager, EntityManagerInterface $entityManager, PheanstalkProxyInterface $queue, Logger $logger, $jobDelay, DataSetImportJobManagerInterface $dataSetImportJobManager)
    {
        $this->dataSetManager = $dataSetManager;
        $this->entityManager = $entityManager;
        $this->conn = $entityManager->getConnection();
        $this->queue = $queue;
        $this->logger = $logger;
        $this->jobDelay = $jobDelay;
        $this->dataSetImportJobManager = $dataSetImportJobManager;
    }

    public function truncateDataSetTable(stdClass $params, Pheanstalk_Job $job, $tube)
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

            $this->logger->notice(sprintf('Truncate data set %s with table name %s', $dataSetId, $dataTable->getName()));
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->notice('error while truncate table');
        }

        $this->dataSetImportJobManager->delete($exeCuteJob);
    }
}