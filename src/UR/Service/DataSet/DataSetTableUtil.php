<?php


namespace UR\Service\DataSet;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;

class DataSetTableUtil implements DataSetTableUtilInterface
{
    /** @var \Doctrine\DBAL\Connection */
    private $connection;

    /** @var Synchronizer */
    private $sync;

    /** @var EntityManagerInterface */
    private $em;

    /** @var ImportHistoryManagerInterface */
    private $importHistoryManager;

    /**
     * DataSetTableUtil constructor.
     * @param EntityManagerInterface $em
     * @param ImportHistoryManagerInterface $importHistoryManager
     */
    public function __construct(EntityManagerInterface $em, ImportHistoryManagerInterface $importHistoryManager)
    {
        $this->em = $em;
        $this->importHistoryManager = $importHistoryManager;
    }

    /**
     * @inheritdoc
     */
    public function updateIndexes(DataSetInterface $dataSet)
    {
        $table = $this->getDataSetTable($dataSet);

        if (!$table instanceof Table) {
            return;
        }

        $this->getSync()->updateIndexes($this->getConnection(), $table, $dataSet);
    }

    /**
     * @inheritdoc
     */
    public function getEntriesByDateRange(ConnectedDataSourceInterface $connectedDataSource, $startDate, $endDate)
    {
        $entries = $this->getEntriesByCheckingEntries($connectedDataSource, $startDate, $endDate);
        $entriesFromDataSetTable = [];
        if ($startDate instanceof DateTime && $endDate instanceof DateTime) {
            $entriesFromDataSetTable = $this->getEntriesByDataSetTable($connectedDataSource, $startDate, $endDate);
        }

        $allEntries = array_merge($entries, $entriesFromDataSetTable);
        $allEntriesId = array_map(function ($entry) {
            if ($entry instanceof DataSourceEntryInterface) {
                return $entry->getId();
            }
        }, $allEntries);

        return array_unique(array_values($allEntriesId));
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        if (!$this->connection instanceof Connection) {
            $this->connection = $this->em->getConnection();
        }

        return $this->connection;
    }

    /**
     * @return Synchronizer
     */
    public function getSync()
    {
        if (!$this->sync instanceof Synchronizer) {
            $this->sync = new Synchronizer($this->getConnection(), new Comparator());
        }

        return $this->sync;
    }

    /**
     * @param DataSetInterface $dataSet
     * @return Table|false
     */
    private function getDataSetTable(DataSetInterface $dataSet)
    {
        return $this->getSync()->getDataSetImportTable($dataSet->getId());
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return mixed
     */
    private function getEntriesByCheckingEntries(ConnectedDataSourceInterface $connectedDataSource, $startDate, $endDate)
    {
        $dataSourceEntries = $this->getDataSourceEntries($connectedDataSource);

        if (is_null($startDate) && is_null($endDate)) {
            return $dataSourceEntries;
        }

        $dataSourceEntries = $this->filterDataSourceEntriesByDateRange($dataSourceEntries, $startDate, $endDate);
        return $dataSourceEntries;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param $startDate
     * @param $endDate
     * @return array
     * @internal param DataSetInterface $dataSet
     */

    private function getEntriesByDataSetTable(ConnectedDataSourceInterface $connectedDataSource, $startDate, $endDate)
    {
        $importIds = $this->getImportHistoryIdsByDateRange($connectedDataSource, $startDate, $endDate);
        if (empty($importIds)) {
            return [];
        }

        $entries = [];
        foreach ($importIds as $importId) {
            $import = $this->importHistoryManager->find($importId);
            if (!$import instanceof ImportHistoryInterface) {
                continue;
            }

            $entries[] = $import->getDataSourceEntry();
        }

        return $entries;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return mixed
     * @internal param DataSetInterface $dataSet
     */
    private function getDataSourceEntries(ConnectedDataSourceInterface $connectedDataSource)
    {
        $dataSource = $connectedDataSource->getDataSource();

        if (!$dataSource instanceof DataSourceInterface) {
            return [];
        }

        $dataSourceEntries = $dataSource->getDataSourceEntries();
        if ($dataSourceEntries instanceof Collection) {
            $dataSourceEntries = $dataSourceEntries->toArray();
        }

        return $dataSourceEntries;
    }

    /**
     * @param $dataSourceEntries
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return mixed
     */
    private function filterDataSourceEntriesByDateRange($dataSourceEntries, DateTime $startDate, DateTime $endDate)
    {
        if (!is_array($dataSourceEntries) || empty($dataSourceEntries)) {
            return [];
        }

        $entries = [];
        foreach ($dataSourceEntries as $dataSourceEntry) {
            if ($this->isIgnoreDataSourceEntry($dataSourceEntry)) {
                continue;
            }

            if (($dataSourceEntry->getStartDate() >= $startDate && $dataSourceEntry->getStartDate() <= $endDate) ||
                ($dataSourceEntry->getEndDate() >= $startDate && $dataSourceEntry->getEndDate() <= $endDate)
            ) {
                $entries[] = $dataSourceEntry;
            }
        }

        return $entries;
    }


    /**
     * @param $dataSourceEntry
     * @return bool
     */
    private function isIgnoreDataSourceEntry($dataSourceEntry)
    {
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return true;
        }

        $dataSource = $dataSourceEntry->getDataSource();

        if (!$dataSource instanceof DataSourceInterface) {
            return true;
        }

        if (!$dataSource->isDateRangeDetectionEnabled()) {
            return true;
        }

        if (empty($dataSourceEntry->getDataSource()->getDetectedStartDate()) &&
            empty($dataSourceEntry->getDataSource()->getDetectedEndDate())
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return array
     */
    private function getImportHistoryIdsByDateRange(ConnectedDataSourceInterface $connectedDataSource, $startDate, $endDate)
    {
        $dataSet = $connectedDataSource->getDataSet();
        if (!$dataSet instanceof DataSetInterface) {
            return [];
        }

        $dateFields = array_filter($dataSet->getAllDimensionMetrics(), function ($type) {
            return in_array($type, [FieldType::DATE, FieldType::DATETIME]);
        });

        if (empty($dateFields)) {
            return [];
        }

        $dateFields = array_keys($dateFields);

        $dataSetTable = $this->getSync()->getDataSetImportTable($dataSet->getId());
        if (!$dataSetTable instanceof Table) {
            return [];
        }

        $startDate = $startDate->format("Y-m-d H:i:s");
        $endDate = $endDate->format("Y-m-d H:i:s");
        $queryBuilder = $this->getConnection()->createQueryBuilder()->from($dataSetTable->getName());
        $queryBuilder->select(sprintf("DISTINCT %s", DataSetInterface::IMPORT_ID_COLUMN));
        $queryBuilder->andWhere(sprintf('`%s` = %s', DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN, $connectedDataSource->getId()));

        foreach ($dateFields as $dateField) {
            if (!$dataSetTable->hasColumn($dateField)) {
                continue;
            }

            $queryBuilder->andWhere(sprintf('`%s` >= "%s" AND `%s` <= "%s"', $dateField, $startDate, $dateField, $endDate));
        }

        $imports = [];

        try {
            $stmt = $queryBuilder->execute();
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                if (!array_key_exists(DataSetInterface::IMPORT_ID_COLUMN, $row)) {
                    continue;
                }

                $imports[] = $row[DataSetInterface::IMPORT_ID_COLUMN];
            }
        } catch (\Exception $e) {

        }

        return $imports;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEm()
    {
        return $this->em;
    }
}