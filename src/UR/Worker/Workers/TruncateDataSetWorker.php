<?php


namespace UR\Worker\Workers;


use stdClass;
use Monolog\Logger;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\Import\LoadingDataService;

class TruncateDataSetWorker
{
    /**
     * @var LoadingDataService
     */
    protected $loadingDataService;

    /**
     * @var ImportHistoryManagerInterface
     */
    protected $importHistoryManager;

    /**
     * @var DataSetManagerInterface
     */
    protected $dataSetManager;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * TruncateDataSetWorker constructor.
     * @param LoadingDataService $loadingDataService
     * @param ImportHistoryManagerInterface $importHistoryManager
     * @param DataSetManagerInterface $dataSetManager
     * @param Logger $logger
     */
    public function __construct(LoadingDataService $loadingDataService, ImportHistoryManagerInterface $importHistoryManager, DataSetManagerInterface $dataSetManager, Logger $logger)
    {
        $this->loadingDataService = $loadingDataService;
        $this->importHistoryManager = $importHistoryManager;
        $this->dataSetManager = $dataSetManager;
        $this->logger = $logger;
    }


    /**
     * @param stdClass $data
     * @return bool
     */
    public function truncateDataSet(StdClass $data)
    {
        $dataSetId = $data->id;
        $dataSet = $this->dataSetManager->find($dataSetId);

        if (!$dataSet instanceof DataSetInterface) {
            $this->logger->error(sprintf('Data Set %d does not exist!', $dataSetId));
            return false;
        }

        $importHistories = $this->importHistoryManager->getImportedHistoryByDataSet($dataSet);
        $this->loadingDataService->reloadDataAugmentationWhenUndo($importHistories);

        return true;
    }
}