<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\DataSourceCleaningService;
use UR\Service\DataSource\DataSourceCleaningServiceInterface;

class RemoveDuplicatedDateEntriesForDataSource implements LockableJobInterface
{
    const JOB_NAME = 'clean_up_time_series_for_data_source';

    const DATA_SOURCE_ID = 'data_source_id';

    /** @var DataSourceManagerInterface */
    protected $dataSourceManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var DataSourceCleaningService */
    private $dataSourceCleaningService;

    public function __construct(LoggerInterface $logger, DataSourceCleaningServiceInterface $dataSourceCleaningService, DataSourceManagerInterface $dataSourceManager)
    {
        $this->logger = $logger;
        $this->dataSourceCleaningService = $dataSourceCleaningService;
        $this->dataSourceManager = $dataSourceManager;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function getLockKeys(JobParams $params): array
    {
        return [sprintf('ur-data-source-date-range-%d', $params->getRequiredParam(self::DATA_SOURCE_ID))];
    }


    public function run(JobParams $params)
    {
        $dataSourceId = $params->getRequiredParam(self::DATA_SOURCE_ID);
        $dataSource = $this->dataSourceManager->find($dataSourceId);

        if (!$dataSource instanceof DataSourceInterface) {
            return;
        }

        $this->dataSourceCleaningService->removeDuplicatedDateEntries($dataSource);
    }
}