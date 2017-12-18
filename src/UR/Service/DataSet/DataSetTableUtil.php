<?php


namespace UR\Service\DataSet;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
    public function getEntriesByReloadParameter(ConnectedDataSourceInterface $connectedDataSource, ReloadParams $reloadParameter)
    {
        $reloadType = $reloadParameter->getType();
        $reloadStartDate = $reloadParameter->getStartDate();
        $reloadEndDate = $reloadParameter->getEndDate();

        switch ($reloadType) {
            case ReloadParamsInterface::ALL_DATA_TYPE: {
                $dataSourceEntries = $this->getAllDataSourceEntries($connectedDataSource);
                break;
            }
            case ReloadParamsInterface::DETECTED_DATE_RANGE_TYPE: {
                $dataSourceEntries = $this->getDataSourceEntriesByDetectedDateRange($connectedDataSource, $reloadStartDate, $reloadEndDate);
                break;
            }
            case ReloadParamsInterface::IMPORTED_ON_TYPE: {
                $dataSourceEntries = $this->getDataSourceEntriesByImportedDate($connectedDataSource, $reloadStartDate, $reloadEndDate);
                break;
            }
            default:
                throw new Exception('Not support this type of reloading, type =%s', $reloadType);
        }

        if (empty($dataSourceEntries)) {
            return [];
        }

        $allEntriesId = array_map(function ($entry) {
            if ($entry instanceof DataSourceEntryInterface) {
                return $entry->getId();
            }
        }, $dataSourceEntries);

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
    private function getAllDataSourceEntries(ConnectedDataSourceInterface $connectedDataSource)
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
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param $startDate
     * @param $endDate
     * @return mixed
     */
    private function getDataSourceEntriesByDetectedDateRange(ConnectedDataSourceInterface $connectedDataSource, $startDate, $endDate)
    {
        $allEntries = $this->getAllDataSourceEntries($connectedDataSource);
        $dataSourceEntries = $this->filterDataSourceEntriesByDetectedDateRange($allEntries, $startDate, $endDate);

        return $dataSourceEntries;
    }

    /**
     * @param $dataSourceEntries
     * @param $startDate
     * @param $endDate
     * @return mixed
     */
    private function filterDataSourceEntriesByDetectedDateRange($dataSourceEntries, $startDate, $endDate)
    {
        if (!is_array($dataSourceEntries) || empty($dataSourceEntries)) {
            return [];
        }

        if (!$startDate instanceof DateTime || !$endDate instanceof DateTime) {
            return $dataSourceEntries;
        }

        $entries = [];
        foreach ($dataSourceEntries as $dataSourceEntry) {
            if ($this->isIgnoreDataSourceEntry($dataSourceEntry)) {
                continue;
            }

            $entryStartDate = $dataSourceEntry->getStartDate();
            $entryEndDate = $dataSourceEntry->getEndDate();

            if (!$entryStartDate instanceof DateTime && !$entryEndDate instanceof DateTime) {
                continue;
            }

            $entryStartDate->setTime(0, 0, 0);
            $entryEndDate->setTime(0, 0, 0);

            if (($entryStartDate >= $startDate && $entryStartDate <= $endDate) ||
                ($entryEndDate >= $startDate && $entryEndDate <= $endDate) ||
                ($startDate >= $entryStartDate && $endDate <= $entryEndDate)
            ) {

                $entries[] = $dataSourceEntry;
            }
        }

        return $entries;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param $startDate
     * @param $endDate
     * @return array|mixed
     */
    private function getDataSourceEntriesByImportedDate(ConnectedDataSourceInterface $connectedDataSource, $startDate, $endDate)
    {
        $allDataSourceEntries = $this->getAllDataSourceEntries($connectedDataSource);

        if (!$startDate instanceof DateTime || !$endDate instanceof DateTime) {
            return $allDataSourceEntries;
        }

        $dataSourceEntries = $this->filterDataSourceEntriesByImportedDate($allDataSourceEntries, $startDate, $endDate);

        return $dataSourceEntries;
    }

    /**
     * @param $dataSourceEntries
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @return array
     */
    private function filterDataSourceEntriesByImportedDate($dataSourceEntries, DateTime $startDate, DateTime $endDate)
    {
        if (!is_array($dataSourceEntries) || empty($dataSourceEntries)) {
            return [];
        }

        $entries = [];
        foreach ($dataSourceEntries as $dataSourceEntry) {
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                continue;
            }
            $importedDate = $dataSourceEntry->getReceivedDate()->setTime(0, 0, 0);
            if ($importedDate >= $startDate && $importedDate <= $endDate) {
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
        } catch (Exception $e) {

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