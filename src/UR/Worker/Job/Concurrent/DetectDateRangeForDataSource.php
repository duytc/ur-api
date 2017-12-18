<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\DateTime\DateRangeService;

class DetectDateRangeForDataSource  implements LockableJobInterface
{
    const JOB_NAME = 'detect_date_range_for_data_source';

    const DATA_SOURCE_ID = 'data_source_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var DateRangeService  */
    private $dateRangeService;

    public function __construct(LoggerInterface $logger, DateRangeService $dateRangeService)
    {
        $this->logger = $logger;
        $this->dateRangeService = $dateRangeService;
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

        if (!is_integer($dataSourceId)) {
            return;
        }

        $this->dateRangeService->calculateDateRangeForDataSource($dataSourceId);
    }
}