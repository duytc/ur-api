<?php

namespace UR\Worker\Workers;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use stdClass;
use UR\DomainManager\DataSetManagerInterface;
use UR\Exception\SqlLockTableException;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\LockingDatabaseTable;
use UR\Service\DataSet\Synchronizer;

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

    private $lockingDatabaseTable;

    private $queue;

    private $logger;

    /**
     * AlterImportDataTable constructor.
     * @param DataSetManagerInterface $dataSetManager
     * @param EntityManagerInterface $entityManager
     * @param $queue
     * @param Logger $logger
     */
    public function __construct(DataSetManagerInterface $dataSetManager, EntityManagerInterface $entityManager, $queue, Logger $logger)
    {
        $this->dataSetManager = $dataSetManager;
        $this->entityManager = $entityManager;
        $this->conn = $entityManager->getConnection();
        $this->lockingDatabaseTable = new LockingDatabaseTable($this->conn);
        $this->queue = $queue;
        $this->logger = $logger;
    }

    public function truncateDataSetTable(StdClass $params, $job = null, $tube = null)
    {
        $dataSetId = $params->dataSetId;
        /**
         * @var DataSetInterface $dataSet
         */
        $dataSet = $this->dataSetManager->find($dataSetId);

        if ($dataSet === null) {
            throw new \Exception(sprintf('Cannot find Data Set with id: %s', $dataSetId));
        }

        $schema = new Schema();
        $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());;
        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

        // check if table not existed
        if (!$dataTable) {
            return;
        }

        try {
            $this->lockingDatabaseTable->lockTable($dataTable->getName());
        } catch (SqlLockTableException $exception) {
            $this->queue->putInTube($tube, $job->getData(), 0, 15);
            return;
        }

        try {
            $truncateSQL = sprintf("TRUNCATE %s", $dataTable->getName());
            $this->conn->exec($truncateSQL);
        } catch (\Exception $e) {
            $this->lockingDatabaseTable->unLockTable();
            $this->logger->error($e->getMessage());
            throw new \mysqli_sql_exception("Cannot Sync Schema " . $schema->getName());
        }

        $this->logger->debug(sprintf('Truncate data set %s with table name %s', $dataSetId, $dataTable->getName()));
        $this->lockingDatabaseTable->unLockTable();
    }
}