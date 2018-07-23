<?php

namespace UR\Worker\Job\Linear;

use DateTime;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\Bundle\ApiBundle\Behaviors\GetChangedDateRangeTrait;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class UpdateChangedDateRangeForMapDataSetSubJob implements SplittableJobInterface
{
    use GetChangedDateRangeTrait;

    const JOB_NAME = 'UpdateChangedDateRangeForMapDataSetSubJob';

    const DATA_SET_ID = 'data_set_id';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';
    const ENTRY_IDS = 'entry_ids';
    const ACTION = 'action';
    const IMPORT_HISTORY_ID = 'import_history_id';

    // for reload data only
    const RELOAD_DATE_RANGE = 'reload_date_range';

    // undo import history, remove entries, remove data set, remove connected data source only
    const DELETED_DATE_RANGE = 'deleted_date_range';

    const ACTION_NEW_ENTRY = 'new-entry';
    const ACTION_REPLAY_ENTRY = 'replay-entry';
    const ACTION_RELOAD_CONNECTED_DATA_SOURCE = 'reload-connected-data-source';
    const ACTION_RELOAD_DATA_SET = 'reload-data-set';
    const ACTION_REMOVE_CONNECTED_DATA_SOURCE = 'remove-connected-data-source';
    const ACTION_REMOVE_DATA_SET = 'remove-data-set';
    const ACTION_REMOVE_ALL_DATA_CONNECTED_DATA_SOURCE = 'remove-all-data-connected-data-source';
    const ACTION_REMOVE_ALL_DATA_DATA_SET = 'remove-all-data-data-set';
    const ACTION_REMOVE_ENTRY = 'remove-entry';
    const ACTION_UNDO_IMPORT = 'undo-import';

    const SUPPORTED_ACTIONS = [
        self::ACTION_NEW_ENTRY,
        self::ACTION_REPLAY_ENTRY,
        self::ACTION_RELOAD_CONNECTED_DATA_SOURCE,
        self::ACTION_RELOAD_DATA_SET,
        self::ACTION_REMOVE_CONNECTED_DATA_SOURCE,
        self::ACTION_REMOVE_DATA_SET,
        self::ACTION_REMOVE_ALL_DATA_DATA_SET,
        self::ACTION_REMOVE_ALL_DATA_CONNECTED_DATA_SOURCE,
        self::ACTION_REMOVE_ENTRY,
        self::ACTION_UNDO_IMPORT
    ];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var DataSetManagerInterface */
    private $dataSetManager;

    /** @var ConnectedDataSourceManagerInterface */
    private $connectedDataSourceManager;

    /** @var DataSourceEntryManagerInterface */
    private $dataSourceEntryManager;

    /** @var ImportHistoryManagerInterface */
    private $importHistoryManager;

    public function __construct(LoggerInterface $logger,
                                DataSetManagerInterface $dataSetManager,
                                ConnectedDataSourceManagerInterface $connectedDataSourceManager,
                                DataSourceEntryManagerInterface $dataSourceEntryManager,
                                ImportHistoryManagerInterface $importHistoryManager)
    {
        $this->logger = $logger;
        $this->dataSetManager = $dataSetManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->importHistoryManager = $importHistoryManager;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->dataSetManager->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            return;
        }

        /* make sure is map data set */
        if (empty($dataSet->getLinkedMapDataSets())) {
            return;
        }

        $action = $params->getParam(self::ACTION);
        if (empty($action)) {
            return;
        }

        /* get date range from params due to action */
        $dateRangeChanged = $this->getDateRangeChangedForDataSetDueToAction($params);
        if (!is_array($dateRangeChanged) || empty($dateRangeChanged)) {
            $this->logger->warning(sprintf('Could not get changed date range for data set #%s, because action not supported or data set\'s data is not change', $dataSetId));
            return;
        }

        /* apply new date range to map data set */
        $dateRangeChanged = $this->calculateChangedDateRangeForMapDataSet($dataSet, $dateRangeChanged[0], $dateRangeChanged[1]);

        $dataSet
            ->setIsChangedDateRange(true)
            ->setChangedStartDate($dateRangeChanged[0])
            ->setChangedEndDate($dateRangeChanged[1]);

        $this->logger->info(sprintf('Got changed date range for data set #%s: %s - %s', $dataSetId,
            ($dataSet->getChangedStartDate() instanceof DateTime) ? $dataSet->getChangedStartDate()->format('Y-m-d') : 'null',
            ($dataSet->getChangedEndDate() instanceof DateTime) ? $dataSet->getChangedEndDate()->format('Y-m-d') : 'null'));

        try {
            $this->dataSetManager->save($dataSet);
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('Could not save changed date range for data set #%s, because data set may be deleted before', $dataSetId));
        }
    }

    /**
     * @param JobParams $params
     * @return array|bool
     */
    private function getDateRangeChangedForDataSetDueToAction(JobParams $params)
    {
        $action = $params->getParam(self::ACTION);

        if (!in_array($action, self::SUPPORTED_ACTIONS)) {
            return false;
        }

        switch ($action) {
            case self::ACTION_NEW_ENTRY:
            case self::ACTION_REPLAY_ENTRY:
                $entryIds = $params->getParam(self::ENTRY_IDS);
                if (!is_array($entryIds)) {
                    break;
                }

                return $this->getDateRangeFromEntries($this->dataSourceEntryManager, $entryIds);

            case self::ACTION_RELOAD_CONNECTED_DATA_SOURCE:
            case self::ACTION_REMOVE_ALL_DATA_CONNECTED_DATA_SOURCE:
                $reloadDateRange = $params->getParam(self::RELOAD_DATE_RANGE);
                if (!is_array($reloadDateRange) || count($reloadDateRange) < 2
                ) {
                    $connectedDataSourceId = $params->getParam(self::CONNECTED_DATA_SOURCE_ID);
                    if (is_null($connectedDataSourceId) || !is_integer($connectedDataSourceId)) {
                        break;
                    }

                    $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
                    if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                        break;
                    }

                    return $this->getChangedDateRangeForConnectedDataSource($connectedDataSource);
                }

                // reload by date range
                try {
                    return [
                        date_create_from_format(DateFormat::DEFAULT_DATE_FORMAT, $reloadDateRange[0]),
                        date_create_from_format(DateFormat::DEFAULT_DATE_FORMAT, $reloadDateRange[1])
                    ];
                } catch (\Exception $e) {
                    return [];
                }

            case self::ACTION_RELOAD_DATA_SET:
                $reloadDateRange = $params->getParam(self::RELOAD_DATE_RANGE);
                if (!is_array($reloadDateRange) || count($reloadDateRange) < 2
                ) {
                    $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
                    $dataSet = $this->dataSetManager->find($dataSetId);
                    if (!$dataSet instanceof DataSetInterface) {
                        return [];
                    }

                    return $this->getDateRangeFromDataSet($dataSet);
                }

                // reload by date range
                try {
                    return [
                        date_create_from_format(DateFormat::DEFAULT_DATE_FORMAT, $reloadDateRange[0]),
                        date_create_from_format(DateFormat::DEFAULT_DATE_FORMAT, $reloadDateRange[1])
                    ];
                } catch (\Exception $e) {
                    return [];
                }

            case self::ACTION_REMOVE_DATA_SET:
                // not supported this case. Could not remove map data set  if still have connected ds use this map data set
                // Then, if deleted map data set, nothing happens
                break;

            case self::ACTION_REMOVE_ALL_DATA_DATA_SET:
                $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
                $dataSet = $this->dataSetManager->find($dataSetId);
                if (!$dataSet instanceof DataSetInterface) {
                    return [];
                }

                return $this->getDateRangeFromDataSet($dataSet);

            case self::ACTION_REMOVE_CONNECTED_DATA_SOURCE:
            case self::ACTION_REMOVE_ENTRY:
            case self::ACTION_UNDO_IMPORT:
                $deletedDateRange = $params->getParam(self::DELETED_DATE_RANGE);
                if (!is_array($deletedDateRange) || count($deletedDateRange) < 2) {
                    break;
                }

                try {
                    $startDate = new DateTime($deletedDateRange[0]);
                } catch (\Exception $e) {
                    $startDate = null;
                }

                try {
                    $endDate = new DateTime($deletedDateRange[1]);
                } catch (\Exception $e) {
                    $endDate = null;
                }

                return [
                    $startDate,
                    $endDate
                ];
        }

        return [];
    }

    /**
     * calculate Changed Date Range For Map Data Set
     *
     * @param DataSetInterface $dataSet
     * @param null|DateTime $newChangedStartDate
     * @param null|DateTime $newChangedEndDate
     * @return array
     */
    private function calculateChangedDateRangeForMapDataSet(DataSetInterface $dataSet, $newChangedStartDate, $newChangedEndDate)
    {
        $newChangedStartDate = ($newChangedStartDate instanceof DateTime) ? $newChangedStartDate : null;
        $newChangedEndDate = ($newChangedEndDate instanceof DateTime) ? $newChangedEndDate : null;

        $oldStartDate = $dataSet->getChangedStartDate();
        $oldEndDate = $dataSet->getChangedEndDate();
        $isChangedDateRange = $dataSet->getIsChangedDateRange();

        if (!$isChangedDateRange) {
            return [$newChangedStartDate, $newChangedEndDate];
        }

        if (is_null($newChangedStartDate) || is_null($newChangedEndDate)) {
            // force both null
            return [null, null];
        }

        $newChangedStartDate = (!is_null($oldStartDate) && $newChangedStartDate < $oldStartDate)
            ? $newChangedStartDate
            : $oldStartDate;

        $newChangedEndDate = (!is_null($oldEndDate) && $newChangedEndDate > $oldEndDate)
            ? $newChangedEndDate
            : $oldEndDate;

        return [$newChangedStartDate, $newChangedEndDate];
    }
}