<?php

namespace UR\Worker;

use DateTime;
use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use Pubvantage\Worker\Scheduler\ConcurrentJobScheduler;
use Pubvantage\Worker\Scheduler\ConcurrentJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\DataSetJobScheduler;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\DataSourceEntryJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\LinearJobScheduler;
use Pubvantage\Worker\Scheduler\LinearJobSchedulerInterface;
use Redis;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\ReloadParamsInterface;
use UR\Service\DateUtilInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Worker\Job\Concurrent\CountChunkRow;
use UR\Worker\Job\Concurrent\DetectDateRangeForDataSource;
use UR\Worker\Job\Concurrent\DetectDateRangeForDataSourceEntry;
use UR\Worker\Job\Concurrent\MaintainPreCalculateTableForLargeReportView;
use UR\Worker\Job\Concurrent\ParseChunkFile;
use UR\Worker\Job\Concurrent\ProcessAlert;
use UR\Worker\Job\Concurrent\RemoveDuplicatedDateEntriesForDataSource;
use UR\Worker\Job\Concurrent\SplitHugeFile;
use UR\Worker\Job\Concurrent\UpdateDetectedFieldsWhenEntryDeleted;
use UR\Worker\Job\Concurrent\UpdateDetectedFieldsWhenEntryInserted;
use UR\Worker\Job\Concurrent\UpdateTotalRowWhenEntryInserted;
use UR\Worker\Job\Linear\AlterDataSetTableJob;
use UR\Worker\Job\Linear\AlterDataSetTableSubJob;
use UR\Worker\Job\Linear\DeleteConnectedDataSource;
use UR\Worker\Job\Concurrent\LoadFileIntoDataSetMapBuilderSubJob;
use UR\Worker\Job\Linear\LoadFilesIntoDataSetLinearWithConcurrentSubJob;
use UR\Worker\Job\Linear\LoadFilesIntoDataSetMapBuilder;
use UR\Worker\Job\Linear\ReloadConnectedDataSource;
use UR\Worker\Job\Linear\ReloadDataSet;
use UR\Worker\Job\Linear\RemoveAllDataFromConnectedDataSource;
use UR\Worker\Job\Linear\RemoveAllDataFromDataSet;
use UR\Worker\Job\Linear\TruncateDataSetSubJob;
use UR\Worker\Job\Linear\UndoImportHistorySubJob;
use UR\Worker\Job\Linear\UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob;
use UR\Worker\Job\Linear\UpdateAugmentedDataSetStatusSubJob;
use UR\Worker\Job\Linear\UpdateDataSetTotalRowSubJob;
use UR\Worker\Job\Linear\UpdateOverwriteDateInDataSetSubJob;

// responsible for creating background tasks

class Manager
{
    /** @var DateUtilInterface */
    protected $dateUtil;

    /** @var Redis */
    protected $redis;

    /** @var PheanstalkProxy */
    protected $beanstalk;

    /** @var LinearJobSchedulerInterface */
    protected $linearJobScheduler;

    /** @var DataSetJobSchedulerInterface */
    protected $dataSetJobScheduler;

    /** @var ConcurrentJobSchedulerInterface */
    protected $concurrentJobScheduler;

    /** @var  DataSourceEntryJobSchedulerInterface */
    protected $dataSourceEntryScheduler;

    public function __construct(DateUtilInterface $dateUtil, Redis $redis, PheanstalkProxy $beanstalk, ConcurrentJobScheduler $concurrentJobScheduler,
                                LinearJobScheduler $linearJobScheduler, DataSetJobScheduler $dataSetJobScheduler, DataSourceEntryJobSchedulerInterface $dataSourceEntryScheduler)
    {
        $this->dateUtil = $dateUtil;
        $this->redis = $redis;
        $this->beanstalk = $beanstalk;
        $this->concurrentJobScheduler = $concurrentJobScheduler;
        $this->linearJobScheduler = $linearJobScheduler;
        $this->dataSetJobScheduler = $dataSetJobScheduler;
        $this->dataSourceEntryScheduler = $dataSourceEntryScheduler;
    }

    /**
     * @param int|array $connectedDataSourceIds
     * @param array $entryIds
     * @param int $dataSetId
     */
    public function loadingDataSourceEntriesToDataSetTable($connectedDataSourceIds, $entryIds, $dataSetId)
    {
        // support $connectedDataSourceIds as both int and array
        if (!is_array($connectedDataSourceIds)) {
            $connectedDataSourceIds = [$connectedDataSourceIds];
        }

        if (empty($connectedDataSourceIds)) {
            return;
        }

        // Notice: use arrays of jobs to save process liner jobs when use dataSetJobScheduler

        $jobs = [];
        foreach ($connectedDataSourceIds as $connectedDataSourceId) {
            $jobs[] = [
                'task' => LoadFilesIntoDataSetLinearWithConcurrentSubJob::JOB_NAME,
                LoadFilesIntoDataSetLinearWithConcurrentSubJob::DATA_SET_ID => $dataSetId,
                LoadFilesIntoDataSetLinearWithConcurrentSubJob::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId,
                LoadFilesIntoDataSetLinearWithConcurrentSubJob::ENTRY_IDS => $entryIds
            ];
        }

        $this->dataSetJobScheduler->addJob($jobs, $dataSetId);
    }

    /**
     * @param DataSetInterface $dataSet
     */
    public function reloadAllForDataSet(DataSetInterface $dataSet)
    {
        $reloadAllForDataSet = [
            'task' => ReloadDataSet::JOB_NAME
        ];

        $this->dataSetJobScheduler->addJob($reloadAllForDataSet, $dataSet->getId());
    }

    public function reloadDataSetByDateRange(DataSetInterface $dataSet, ReloadParamsInterface $reloadParameter)
    {
        $reloadType = $reloadParameter->getType();
        $reloadStartDate = $reloadParameter->getStartDate() instanceof DateTime
            ? $reloadParameter->getStartDate()->format(DateFormat::DEFAULT_DATE_FORMAT)
            : $reloadParameter->getStartDate();
        $reloadEndDate = $reloadParameter->getEndDate() instanceof DateTime
            ? $reloadParameter->getEndDate()->format(DateFormat::DEFAULT_DATE_FORMAT)
            : $reloadParameter->getEndDate();

        $reloadAllForDataSet = [
            'task' => ReloadDataSet::JOB_NAME,
            ReloadParamsInterface::RELOAD_TYPE => $reloadType,
            ReloadParamsInterface::RELOAD_START_DATE => $reloadStartDate,
            ReloadParamsInterface::RELOAD_END_DATE => $reloadEndDate
        ];

        $this->dataSetJobScheduler->addJob($reloadAllForDataSet, $dataSet->getId());
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ReloadParamsInterface $reloadParameter
     */
    public function reloadAllForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ReloadParamsInterface $reloadParameter = null)
    {
        $reloadType = $reloadParameter instanceof ReloadParamsInterface ? $reloadParameter->getType() : ReloadParamsInterface::ALL_DATA_TYPE;
        $reloadStartDate = null;
        $reloadEndDate = null;

        $reloadAllForConnectedDataSource = [
            'task' => ReloadConnectedDataSource::JOB_NAME,
            ReloadConnectedDataSource::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId(),
            ReloadParamsInterface::RELOAD_TYPE => $reloadType,
            ReloadParamsInterface::RELOAD_START_DATE => $reloadStartDate,
            ReloadParamsInterface::RELOAD_END_DATE => $reloadEndDate
        ];

        $this->dataSetJobScheduler->addJob($reloadAllForConnectedDataSource, $connectedDataSource->getDataSet()->getId());
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ReloadParamsInterface $reloadParameter
     */
    public function reloadConnectedDataSourceByDateRange(ConnectedDataSourceInterface $connectedDataSource, ReloadParamsInterface $reloadParameter)
    {
        $reloadType = $reloadParameter->getType();
        $reloadStartDate = $reloadParameter->getStartDate() instanceof DateTime
            ? $reloadParameter->getStartDate()->format(DateFormat::DEFAULT_DATE_FORMAT)
            : $reloadParameter->getStartDate();
        $reloadEndDate = $reloadParameter->getEndDate() instanceof DateTime
            ? $reloadParameter->getEndDate()->format(DateFormat::DEFAULT_DATE_FORMAT)
            : $reloadParameter->getEndDate();

        $reloadAllForConnectedDataSource = [
            'task' => ReloadConnectedDataSource::JOB_NAME,
            ReloadConnectedDataSource::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId(),
            ReloadParamsInterface::RELOAD_TYPE => $reloadType,
            ReloadParamsInterface::RELOAD_START_DATE => $reloadStartDate,
            ReloadParamsInterface::RELOAD_END_DATE => $reloadEndDate
        ];

        $this->dataSetJobScheduler->addJob($reloadAllForConnectedDataSource, $connectedDataSource->getDataSet()->getId());
    }

    /**
     * @param $connectedDataSourceId
     * @param $dataSetId
     */
    public function removeAllDataFromConnectedDataSource($connectedDataSourceId, $dataSetId)
    {
        $removeDataFromConnectedDataSource = [
            'task' => RemoveAllDataFromConnectedDataSource::JOB_NAME,
            RemoveAllDataFromConnectedDataSource::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
        ];

        $this->dataSetJobScheduler->addJob($removeDataFromConnectedDataSource, $dataSetId);
    }

    /**
     * @param $connectedDataSourceId
     * @param $dataSetId
     */
    public function deleteConnectedDataSource($connectedDataSourceId, $dataSetId)
    {
        $deleteConnectedDataSource = [
            'task' => DeleteConnectedDataSource::JOB_NAME,
            DeleteConnectedDataSource::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
        ];

        $this->dataSetJobScheduler->addJob($deleteConnectedDataSource, $dataSetId);
    }

    /**
     * @param array $importHistoryIds
     * @param int $dataSetId
     */
    public function undoImportHistories($importHistoryIds, $dataSetId)
    {
        // do create all linear jobs for each files
        if (!is_array($importHistoryIds)) {
            return;
        }

        $jobs = [];
        $jobs[] = [
            'task' => UndoImportHistorySubJob::JOB_NAME,
            UndoImportHistorySubJob::IMPORT_HISTORY_IDS => $importHistoryIds
        ];

        $jobs = array_merge($jobs, [
            ['task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME],
            ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],
            ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME],
            ['task' => UpdateAugmentedDataSetStatusSubJob::JOB_NAME],
        ]);

        $this->dataSetJobScheduler->addJob($jobs, $dataSetId);
    }

    /**
     * @param $dataSetId
     */
    public function updateOverwriteDateForDataSet($dataSetId)
    {
        $updateOverwriteDate = [
            'task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME
        ];

        $this->dataSetJobScheduler->addJob($updateOverwriteDate, $dataSetId);
    }

    /**
     * @param $dataSetId
     */
    public function updateTotalRowsForDataSet($dataSetId)
    {
        $updateTotalRow = [
            'task' => UpdateDataSetTotalRowSubJob::JOB_NAME
        ];

        $this->dataSetJobScheduler->addJob($updateTotalRow, $dataSetId);
    }

    /**
     * @param int $code
     * @param int $publisherId
     * @param array $details
     * @param null|int $dataSourceId optional
     */
    public function processAlert($code, $publisherId, $details, $dataSourceId = null)
    {
        $jobData = [
            'task' => ProcessAlert::JOB_NAME,
            ProcessAlert::PARAM_KEY_CODE => $code,
            ProcessAlert::PARAM_KEY_PUBLISHER_ID => $publisherId,
            ProcessAlert::PARAM_KEY_DETAILS => $details,
            ProcessAlert::PARAM_KEY_DATA_SOURCE_ID => $dataSourceId
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    /**
     * @param int $dataSetId
     * @param array $newFields
     * @param array $updateFields
     * @param array $deletedFields
     */
    public function alterDataSetTable($dataSetId, $newFields, $updateFields, $deletedFields)
    {
        $jobData = [
            'task' => AlterDataSetTableJob::JOB_NAME,
            AlterDataSetTableSubJob::NEW_FIELDS => $newFields,
            AlterDataSetTableSubJob::UPDATE_FIELDS => $updateFields,
            AlterDataSetTableSubJob::DELETED_FIELDS => $deletedFields
        ];

        // linear job for data set
        $this->dataSetJobScheduler->addJob($jobData, $dataSetId);
    }

    /**
     * @param int $dataSetId
     */
    public function removeAllDataFromDataSet($dataSetId)
    {
        $jobData = [
            'task' => RemoveAllDataFromDataSet::JOB_NAME
        ];

        // linear job for data set
        $this->dataSetJobScheduler->addJob($jobData, $dataSetId);
    }

    /**
     * @param int $entryId
     * @param int $dataSourceId
     */
    public function updateDetectedFieldsWhenEntryInserted($entryId, $dataSourceId)
    {
        $jobData = [
            'task' => UpdateDetectedFieldsWhenEntryInserted::JOB_NAME,
            UpdateDetectedFieldsWhenEntryInserted::PARAM_KEY_ENTRY_ID => $entryId,
            UpdateDetectedFieldsWhenEntryInserted::PARAM_KEY_DATA_SOURCE_ID => $dataSourceId
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    /**
     * @param int $entryId
     */
    public function updateTotalRowWhenEntryInserted($entryId)
    {
        $jobData = [
            'task' => UpdateTotalRowWhenEntryInserted::JOB_NAME,
            UpdateTotalRowWhenEntryInserted::PARAM_KEY_ENTRY_ID => $entryId
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    /**
     * @param string $format
     * @param string $path
     * @param $chunkPaths
     * @param int $dataSourceId
     */
    public function updateDetectedFieldsWhenEntryDeleted($format, $path, $chunkPaths, $dataSourceId)
    {
        $jobData = [
            'task' => UpdateDetectedFieldsWhenEntryDeleted::JOB_NAME,
            UpdateDetectedFieldsWhenEntryDeleted::PARAM_KEY_FORMAT => $format,
            UpdateDetectedFieldsWhenEntryDeleted::PARAM_KEY_PATH => $path,
            UpdateDetectedFieldsWhenEntryDeleted::PARAM_KEY_CHUNK_PATHS => $chunkPaths,
            UpdateDetectedFieldsWhenEntryDeleted::PARAM_KEY_DATA_SOURCE_ID => $dataSourceId
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    /**
     * @param $dataSourceId
     * @param $entryId
     */
    public function updateDateRangeForDataSourceEntry($dataSourceId, $entryId)
    {
        $jobData = [
            'task' => DetectDateRangeForDataSourceEntry::JOB_NAME,
            DetectDateRangeForDataSourceEntry::DATA_SOURCE_ID => $dataSourceId,
            DetectDateRangeForDataSourceEntry::ENTRY_ID => $entryId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    /**
     * @param int $dataSourceId
     */
    public function updateDateRangeForDataSource($dataSourceId)
    {
        $jobData = [
            'task' => DetectDateRangeForDataSource::JOB_NAME,
            DetectDateRangeForDataSource::DATA_SOURCE_ID => $dataSourceId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    /**
     * @param int $dataSetId
     */
    public function loadFilesIntoDataSetMapBuilder($dataSetId)
    {
        // remove data first
        $this->dataSetJobScheduler->addJob([
            'task' => TruncateDataSetSubJob::JOB_NAME
        ], $dataSetId);

        $jobData = [
            'task' => LoadFilesIntoDataSetMapBuilder::JOB_NAME,
            LoadFilesIntoDataSetMapBuilder::DATA_SET_ID => $dataSetId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->dataSetJobScheduler->addJob($jobData, $dataSetId);
    }

    /**
     * @param int $dataSetId
     * @param $mapBuilderConfigId
     */
    public function loadDataSetMapBuilder($dataSetId, $mapBuilderConfigId)
    {
        $jobData = [
            'task' => LoadFileIntoDataSetMapBuilderSubJob::JOB_NAME,
            LoadFileIntoDataSetMapBuilderSubJob::DATA_SET_ID => $dataSetId,
            LoadFileIntoDataSetMapBuilderSubJob::MAP_BUILDER_CONFIG_ID => $mapBuilderConfigId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    public function parseChunkFile($jobData)
    {
        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    public function updateAllConnectedDataSourceTotalRowForDataSet($dataSetId)
    {
        $jobs = [
            ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME]
        ];

        // since we can guarantee order. We can batch load many files and then run 1 job to update overwrite date once
        // this will save a lot of execution time
        $this->dataSetJobScheduler->addJob($jobs, $dataSetId);
    }

    public function splitHugeFile($dataSourceEntryId)
    {
        $jobData = [
            'task' => SplitHugeFile::JOB_NAME,
            ParseChunkFile::DATA_SOURCE_ENTRY_ID => $dataSourceEntryId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    public function removeDuplicatedDateEntries($dataSourceId)
    {
        $jobData = [
            'task' => RemoveDuplicatedDateEntriesForDataSource::JOB_NAME,
            RemoveDuplicatedDateEntriesForDataSource::DATA_SOURCE_ID => $dataSourceId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    public function createJobCountChunkRow($chunkFilePath, $dataSourceEntryId)
    {
        $jobData = [
            'task' => CountChunkRow::JOB_NAME,
            CountChunkRow::CHUNK => $chunkFilePath,
            CountChunkRow::ENTRY_ID => $dataSourceEntryId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }

    public function maintainPreCalculateTableForLargeReportView($reportViewId)
    {
        $jobData = [
            'task' => MaintainPreCalculateTableForLargeReportView::JOB_NAME,
            MaintainPreCalculateTableForLargeReportView::REPORT_VIEW_ID => $reportViewId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }
}