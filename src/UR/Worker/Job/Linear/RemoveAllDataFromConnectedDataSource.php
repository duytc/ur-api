<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use Pubvantage\Worker\Scheduler\DataSetJobSchedulerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;

class RemoveAllDataFromConnectedDataSource implements SplittableJobInterface
{
    const JOB_NAME = 'removeAllDataFromConnectedDataSource';

    const DATA_SET_ID = 'data_set_id';
    const CONNECTED_DATA_SOURCE_ID = 'connected_data_source_id';

    /**
     * @var DataSetJobSchedulerInterface
     */
    protected $scheduler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ImportHistoryManagerInterface
     */
    protected $importHistoryManager;

    public function __construct(DataSetJobSchedulerInterface $scheduler, LoggerInterface $logger
    )
    {
        $this->scheduler = $scheduler;

        $this->logger = $logger;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        // do create all linear jobs for each files
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $connectedDataSourceId = (int)$params->getRequiredParam(self::CONNECTED_DATA_SOURCE_ID);
        $jobs = [];

        //add jobs remove data from data import table by connected data source
        $jobs[] = [
            'task' => RemoveDataFromConnectedDataSourceSubJob::JOB_NAME,
            self::CONNECTED_DATA_SOURCE_ID => $connectedDataSourceId
        ];

        //update total rows for data set and connected data source
        $jobs = array_merge($jobs, [
            ['task' => UpdateDataSetTotalRowSubJob::JOB_NAME],
            ['task' => UpdateAllConnectedDataSourcesTotalRowForDataSetSubJob::JOB_NAME]
        ]);

        $this->scheduler->addJob($jobs, $dataSetId, $params);
    }
}