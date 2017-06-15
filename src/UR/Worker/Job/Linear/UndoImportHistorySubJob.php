<?php

namespace UR\Worker\Job\Linear;

use Doctrine\ORM\EntityManagerInterface;
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

    private $entityManager;

    public function __construct(
        LoggerInterface $logger,
        ImportHistoryService $importHistoryService,
        EntityManagerInterface $entityManager
    )
    {
        $this->logger = $logger;
        $this->importHistoryService = $importHistoryService;
        $this->entityManager = $entityManager;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    public function run(JobParams $params)
    {
        // do something here
        try {
            $dataSetId = (int)$params->getRequiredParam(self::DATA_SET_ID);
            $importHistoryIds = (array)$params->getRequiredParam(self::IMPORT_HISTORY_IDS);
            if (!is_array($importHistoryIds)) {
                throw new \Exception('IMPORT_HISTORY_IDS must be array');
            }

            $this->importHistoryService->deleteImportedDataByImportHistories($importHistoryIds, $dataSetId);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf('could not undo import history, error occur: %s', $exception->getMessage()));
        } finally {
            $this->entityManager->clear();
            gc_collect_cycles();
        }
    }
}