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
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

class UpdateDataSetTotalRowService
{
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

    /**
     * UpdateDataSetTotalRowTrait constructor.
     * @param Logger $logger
     * @param EntityManagerInterface $em
     * @param DataSetManagerInterface $dataSetManager
     * @param ConnectedDataSourceManagerInterface $connectedDataSourceManager
     */
    public function __construct(Logger $logger, EntityManagerInterface $em, DataSetManagerInterface $dataSetManager, ConnectedDataSourceManagerInterface $connectedDataSourceManager)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->dataSetManager = $dataSetManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
    }

    public function updateDataSetTotalRow($dataSetId)
    {
        try {
            /** @var DataSetInterface $dataSet */
            $dataSet = $this->dataSetManager->find($dataSetId);
            if (!$dataSet instanceof DataSetInterface) {
                throw new Exception(sprintf('Could not find data data set with ID: %s', $dataSetId));
            }

            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSetId);
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

            // update
            $this->logger->info(sprintf('Total rows in data set: %d', $totalRow));
            $dataSet->setTotalRow($totalRow);
            $this->dataSetManager->save($dataSet);
        } catch (Exception $exception) {
            $this->logger->notice(sprintf('cannot update total row for data set  (ID: %s), error occur: %s', $dataSetId, $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }

    public function updateConnectedDataSourceTotalRow(ConnectedDataSourceInterface $connectedDataSource)
    {
        $dataSet = $connectedDataSource->getDataSet();
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        $dataSetId = $dataSet->getId();
        try {
            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSetId);
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

            // update
            $connectedDataSource->setTotalRow($totalRow);
            $this->connectedDataSourceManager->save($connectedDataSource);
        } catch (\Exception $exception) {
            $this->logger->notice(sprintf('cannot update total row for connected data source (ID: %s), error occur: %s', $connectedDataSource->getId(), $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }

    public function updateAllConnectedDataSourcesTotalRowInOneDataSet(int $dataSetId)
    {
        try {
            $dataSet = $this->dataSetManager->find($dataSetId);
            if (!$dataSet instanceof DataSetInterface) {
                throw new Exception(sprintf('Could not find data data set with ID: %s', $dataSetId));
            }

            $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSetId);
            if (!$dataTable) {
                throw new Exception(sprintf('Could not find data import table with data set ID: %s', $dataSetId));
            }

            $tableName = $dataTable->getName();

            $connectedDataSources = $dataSet->getConnectedDataSources();
            if ($connectedDataSources instanceof Collection) {
                $connectedDataSources = $connectedDataSources->toArray();
            }

            foreach ($connectedDataSources as $connectedDataSource) {
                $qb = new QueryBuilder($this->conn);
                $totalRow = $qb->select("count(__id)")
                    ->from($tableName)
                    ->where(sprintf('%s IS NULL', DataSetInterface::OVERWRITE_DATE))
                    ->andWhere(sprintf('%s=:%s', '__connected_data_source_id', 'connectedDataSourceId'))
                    ->setParameter('connectedDataSourceId', $connectedDataSource->getId(), Type::INTEGER)
                    ->execute()
                    ->fetchColumn(0);

                // update
                $connectedDataSource->setTotalRow($totalRow);
                $this->connectedDataSourceManager->save($connectedDataSource);
            }
        } catch (\Exception $exception) {
            $this->logger->notice(sprintf('cannot update total row for all connected data source of data set  (ID: %s), error occur: %s', $dataSetId, $exception->getMessage()));
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }
}