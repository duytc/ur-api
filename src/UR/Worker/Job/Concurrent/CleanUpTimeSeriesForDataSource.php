<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\CleanUpDataSourceTimeSeriesService;
use UR\Service\DataSource\CleanUpDataSourceTimeSeriesServiceInterface;

class CleanUpTimeSeriesForDataSource implements LockableJobInterface
{
    const JOB_NAME = 'clean_up_time_series_for_data_source';

    const DATA_SOURCE_ID = 'data_source_id';

    /** @var DataSourceManagerInterface */
    protected $dataSourceManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var CleanUpDataSourceTimeSeriesService */
    private $cleanUpDataSourceTimeSeriesService;

    public function __construct(LoggerInterface $logger, CleanUpDataSourceTimeSeriesServiceInterface $cleanUpDataSourceTimeServices, DataSourceManagerInterface $dataSourceManager)
    {
        $this->logger = $logger;
        $this->cleanUpDataSourceTimeSeriesService = $cleanUpDataSourceTimeServices;
        $this->dataSourceManager = $dataSourceManager;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function getLockKey(JobParams $params): string
    {
        return sprintf('ur-data-source-date-range-%d', $params->getRequiredParam(self::DATA_SOURCE_ID));
    }


    public function run(JobParams $params)
    {
        $dataSourceId = $params->getRequiredParam(self::DATA_SOURCE_ID);
        $dataSource = $this->dataSourceManager->find($dataSourceId);

        if (!$dataSource instanceof DataSourceInterface) {
            return;
        }

        $this->cleanUpDataSourceTimeSeriesService->cleanUpDataSourceTimeSeries($dataSource);
    }
}