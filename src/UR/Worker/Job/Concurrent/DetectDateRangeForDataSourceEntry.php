<?php

namespace UR\Worker\Job\Concurrent;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\Job\LockableJobInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\DateTime\DateRangeService;

class DetectDateRangeForDataSourceEntry implements LockableJobInterface
{
    const JOB_NAME = 'detect_date_range_for_data_source_entry';

    const ENTRY_ID = 'entry_id';
    const DATA_SOURCE_ID = 'data_source_id';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var DateRangeService */
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

    public function getLockKey(JobParams $params): string
    {
        return sprintf('ur-data-source-date-range-%d', $params->getRequiredParam(self::DATA_SOURCE_ID));
    }


    public function run(JobParams $params)
    {
        // do something here

        // we can process update total row one time after a batch of files are loaded
        // this can save a lot of processing time during linear load
        $entryId = $params->getRequiredParam(self::ENTRY_ID);

        if (!is_integer($entryId)) {
            return;
        }

        $this->dateRangeService->calculateDateRangeForDataSourceEntry($entryId);
    }
}