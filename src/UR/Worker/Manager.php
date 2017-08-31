<?php

namespace UR\Worker;

use Leezy\PheanstalkBundle\Proxy\PheanstalkProxy;
use Pubvantage\Worker\Scheduler\ConcurrentJobScheduler;
use Pubvantage\Worker\Scheduler\ConcurrentJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\DataSetJobScheduler;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use Pubvantage\Worker\Scheduler\LinearJobScheduler;
use Pubvantage\Worker\Scheduler\LinearJobSchedulerInterface;
use Redis;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DateUtilInterface;
use UR\Worker\Job\Concurrent\CleanUpTimeSeriesForDataSource;
use UR\Worker\Job\Concurrent\ProcessAlert;
use UR\Worker\Job\Concurrent\UpdateDetectedFieldsWhenEntryInserted;
use UR\Worker\Job\Concurrent\UpdateDetectedFieldsWhenEntryDeleted;
use UR\Worker\Job\Concurrent\UpdateTotalRowWhenEntryInserted;
use UR\Worker\Job\Concurrent\DetectDateRangeForDataSource;
use UR\Worker\Job\Concurrent\DetectDateRangeForDataSourceEntry;
use UR\Worker\Job\Linear\AlterDataSetTableJob;
use UR\Worker\Job\Linear\AlterDataSetTableSubJob;
use UR\Worker\Job\Linear\DeleteConnectedDataSource;
use UR\Worker\Job\Linear\LoadFilesIntoDataSet;
use UR\Worker\Job\Linear\LoadFilesIntoDataSetMapBuilder;
use UR\Worker\Job\Linear\ReloadConnectedDataSource;
use UR\Worker\Job\Linear\ReloadDataSet;
use UR\Worker\Job\Linear\RemoveAllDataFromConnectedDataSource;
use UR\Worker\Job\Linear\RemoveAllDataFromDataSet;
use UR\Worker\Job\Linear\TruncateDataSetSubJob;
use UR\Worker\Job\Linear\UndoImportHistories;
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

    public function __construct(DateUtilInterface $dateUtil,
                                Redis $redis,
                                PheanstalkProxy $beanstalk,
                                ConcurrentJobScheduler $concurrentJobScheduler,
                                LinearJobScheduler $linearJobScheduler,
                                DataSetJobScheduler $dataSetJobScheduler)
    {
        $this->dateUtil = $dateUtil;
        $this->redis = $redis;
        $this->beanstalk = $beanstalk;
        $this->concurrentJobScheduler = $concurrentJobScheduler;
        $this->linearJobScheduler = $linearJobScheduler;
        $this->dataSetJobScheduler = $dataSetJobScheduler;
    }

    /**
     * @param int $connectedDataSourceId
     * @param array $entryIds
     * @param int $dataSetId
     */
    public function loadingDataSourceEntriesToDataSetTable($connectedDataSourceId, $entryIds, $dataSetId)
    {
        $loadFilesToDataSet = [
            'task' => LoadFilesIntoDataSet::JOB_NAME,
            LoadFilesIntoDataSet::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId,
            LoadFilesIntoDataSet::ENTRY_IDS => $entryIds
        ];

        $this->dataSetJobScheduler->addJob($loadFilesToDataSet, $dataSetId);
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

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     */
    public function reloadAllForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource)
    {
        $reloadAllForConnectedDataSource = [
            'task' => ReloadConnectedDataSource::JOB_NAME,
            ReloadConnectedDataSource::CONNECTED_DATA_SOURCE_ID => $connectedDataSource->getId()
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
        $undoImportHistories = [
            'task' => UndoImportHistories::JOB_NAME,
            UndoImportHistories::IMPORT_HISTORY_IDS => $importHistoryIds
        ];

        $this->dataSetJobScheduler->addJob($undoImportHistories, $dataSetId);
    }

    /**
     * @param DataSetInterface $dataSet
     */
    public function updateOverwriteDateForDataSet(DataSetInterface $dataSet)
    {
        $updateOverwriteDate = [
            'task' => UpdateOverwriteDateInDataSetSubJob::JOB_NAME
        ];

        $this->dataSetJobScheduler->addJob($updateOverwriteDate, $dataSet->getId());
    }

    /**
     * @param DataSetInterface $dataSet
     */
    public function updateTotalRowsForDataSet(DataSetInterface $dataSet)
    {
        $updateTotalRow = [
            'task' => UpdateDataSetTotalRowSubJob::JOB_NAME
        ];

        $this->dataSetJobScheduler->addJob($updateTotalRow, $dataSet->getId());
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
     * @param int $dataSourceId
     */
    public function updateDetectedFieldsWhenEntryDeleted($format, $path, $dataSourceId)
    {
        $jobData = [
            'task' => UpdateDetectedFieldsWhenEntryDeleted::JOB_NAME,
            UpdateDetectedFieldsWhenEntryDeleted::PARAM_KEY_FORMAT => $format,
            UpdateDetectedFieldsWhenEntryDeleted::PARAM_KEY_PATH => $path,
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

    public function cleanUpDataSourceTimeSeries($dataSourceId)
    {
        $jobData = [
            'task' => CleanUpTimeSeriesForDataSource::JOB_NAME,
            CleanUpTimeSeriesForDataSource::DATA_SOURCE_ID => $dataSourceId,
        ];

        // concurrent job, we do not care what order it is processed in
        $this->concurrentJobScheduler->addJob($jobData);
    }
}