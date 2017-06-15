<?php

namespace UR\Worker\Job\Linear;

use Psr\Log\LoggerInterface;
use Pubvantage\Worker\JobParams;
use UR\Service\Import\ImportHistoryService;

class UndoImportHistorySubJob implements SubJobInterface
{
    const JOB_NAME = 'undoImportHistorySubJob';

    const DATA_SET_ID = 'data_set_id';

    const IMPORT_HISTORY_IDS = 'import_history_ids';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ImportHistoryService
     */
    private $importHistoryService;

    public function __construct(
        LoggerInterface $logger,
        ImportHistoryService $importHistoryService
    )
    {
        $this->logger = $logger;
        $this->importHistoryService = $importHistoryService;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        // do something here
        $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
        $importHistoryIds = (array)$params->getRequiredParam(self::IMPORT_HISTORY_IDS);
        if (!is_array($importHistoryIds)) {
            $this->logger->error('IMPORT_HISTORY_IDS must be array');
            return;
        }

        $this->importHistoryService->deleteImportedDataByImportHistories($importHistoryIds, $dataSetId);
    }
}