<?php


namespace UR\Service\DataSet;


use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Monolog\Logger;
use Redis;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

class UpdateDataSetTotalRowService
{
    const DATA_SET_LOCK_TEMPLATE = "data-set-%s-update-total-row";
    const CONNECTED_DATA_SOURCE_LOCK_TEMPLATE = "connected-data-source-%s-update-total-row";
    const REDIS_KEY_TIME_OUT = 6;

    private $logger;

    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var DataSetManagerInterface
     */
    private $dataSetManager;

    /**
     * @var ConnectedDataSourceManagerInterface
     */
    private $connectedDataSourceManager;

    /** @var Redis */
    private $redis;

    /**
     * UpdateDataSetTotalRowTrait constructor.
     * @param Logger $logger
     * @param EntityManagerInterface $em
     * @param DataSetManagerInterface $dataSetManager
     * @param ConnectedDataSourceManagerInterface $connectedDataSourceManager
     * @param Redis $redis
     */
    public function __construct(Logger $logger, EntityManagerInterface $em, DataSetManagerInterface $dataSetManager, ConnectedDataSourceManagerInterface $connectedDataSourceManager, Redis $redis)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->dataSetManager = $dataSetManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->redis = $redis;
    }

    public function updateDataSetTotalRow($dataSetId)
    {
        $dataSetLockKey = sprintf(self::DATA_SET_LOCK_TEMPLATE, $dataSetId);
        if ($this->redis->exists($dataSetLockKey)) {
            //Other process is updating total row for this data set. Quit job
            return;
        }

        //Lock for 6 seconds
        $this->redis->set($dataSetLockKey, 1, self::REDIS_KEY_TIME_OUT);

        try {
            /** @var DataSetInterface $dataSet */
            $dataSet = $this->dataSetManager->find($dataSetId);
            if (!$dataSet instanceof DataSetInterface) {
                throw new Exception(sprintf('Could not find data data set with ID: %s', $dataSetId));
            }

            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());
            $dataTable = $dataSetSynchronizer->createEmptyDataSetTable($dataSet);
            if (!$dataTable) {
                throw new Exception(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            }

            $tableName = $dataTable->getName();
            $qb = new QueryBuilder($this->conn);
            $totalRow = $qb->select("count(__id)")
                ->from($tableName)
                ->where(sprintf('%s IS NULL', DataSetInterface::OVERWRITE_DATE))
                ->execute()
                ->fetchColumn(0);

            // update by raw sql, ignore Doctrine events
            $updateTotalRowSQL = sprintf("UPDATE %s SET total_row = %s WHERE id = %s", 'core_data_set', $totalRow, $dataSetId);
            $this->conn->executeQuery($updateTotalRowSQL);
        } catch (Exception $exception) {
            $this->logger->notice(sprintf('cannot update total row for data set  (ID: %s), error occur: %s', $dataSetId, $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }

        //Release lock
        $this->redis->del($dataSetLockKey);
    }

    public function updateConnectedDataSourceTotalRow(ConnectedDataSourceInterface $connectedDataSource)
    {
        $dataSetId = $connectedDataSource->getDataSet()->getId();
        try {
            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());
            $dataTable = $dataSetSynchronizer->createEmptyDataSetTable($connectedDataSource->getDataSet());
            if (!$dataTable) {
                throw new Exception(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            }

            $tableName = $dataTable->getName();

            $qb = new QueryBuilder($this->conn);
            $totalRow = $qb->select("count(__id)")
                ->from($tableName)
                ->where(sprintf('%s IS NULL', DataSetInterface::OVERWRITE_DATE))
                ->andWhere(sprintf('%s=:%s', '__connected_data_source_id', 'connectedDataSourceId'))
                ->setParameter('connectedDataSourceId', $connectedDataSource->getId(), Type::INTEGER)
                ->execute()
                ->fetchColumn(0);

            // update by raw sql, ignore Doctrine events
            $updateTotalRowSQL = sprintf("UPDATE %s SET total_row = %s WHERE id = %s", 'core_connected_data_source', $totalRow, $connectedDataSource->getId());
            $this->conn->executeQuery($updateTotalRowSQL);
        } catch (\Exception $exception) {
            $this->logger->notice(sprintf('cannot update total row for connected data source (ID: %s), error occur: %s', $connectedDataSource->getId(), $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }

    public function updateAllConnectedDataSourcesTotalRowInOneDataSet(int $dataSetId)
    {
        $dataSetLockKey = sprintf(self::CONNECTED_DATA_SOURCE_LOCK_TEMPLATE, $dataSetId);
        if ($this->redis->exists($dataSetLockKey)) {
            //Other process is updating total row for connected data sources on this data set. Quit job
            return;
        }

        //Lock for 6 seconds
        $this->redis->set($dataSetLockKey, 1, self::REDIS_KEY_TIME_OUT);

        try {

            $dataSet = $this->dataSetManager->find($dataSetId);
            if (!$dataSet instanceof DataSetInterface) {
                throw new Exception(sprintf('Could not find data data set with ID: %s', $dataSetId));
            }

            $connectedDataSources = $dataSet->getConnectedDataSources();
            $connectedDataSources = $connectedDataSources instanceof Collection ? $connectedDataSources->toArray() : $connectedDataSources;

            foreach ($connectedDataSources as $connectedDataSource) {
                if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                    continue;
                }

                $this->updateConnectedDataSourceTotalRow($connectedDataSource);
            }
        } catch (\Exception $exception) {
            $this->logger->notice(sprintf('cannot update total row for all connected data source of data set  (ID: %s), error occur: %s', $dataSetId, $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }

        //Release lock
        $this->redis->del($dataSetLockKey);
    }
}