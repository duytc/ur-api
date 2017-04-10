<?php

namespace UR\Service\Import;


use UR\DomainManager\DataSetImportJobManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Repository\Core\ImportHistoryRepositoryInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;
use UR\Worker\Manager;

class LoadingDataService
{
    /** @var Manager */
    private $workerManager;

    /**@var LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository */
    private $linkedMapDataSetRepository;

    /**@var DataSetImportJobManagerInterface $dataSetImportJobManager */
    private $dataSetImportJobManager;

    /**@var ImportHistoryRepositoryInterface $importHistoryRepository */
    private $importHistoryRepository;

    /**
     * LoadingDataService constructor.
     * @param Manager $workerManager
     * @param DataSetImportJobManagerInterface $dataSetImportJobManager
     * @param ImportHistoryRepositoryInterface $importHistoryRepository
     * @param LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository
     */
    public function __construct(Manager $workerManager, DataSetImportJobManagerInterface $dataSetImportJobManager, ImportHistoryRepositoryInterface $importHistoryRepository, LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository)
    {
        $this->workerManager = $workerManager;
        $this->linkedMapDataSetRepository = $linkedMapDataSetRepository;
        $this->dataSetImportJobManager = $dataSetImportJobManager;
        $this->importHistoryRepository = $importHistoryRepository;
    }

    /**
     * @param ConnectedDataSourceInterface[] $connectedDataSources
     * @param array $entryIds
     */
    public function doLoadDataFromEntryToDataBase($connectedDataSources, array $entryIds)
    {
        foreach ($connectedDataSources as $connectedDataSource) {
            $dataSet = $connectedDataSource->getDataSet();

            $dataSetImportJobEntity = $this->dataSetImportJobManager->createNewDataSetImportJob($dataSet);
            if (!$dataSetImportJobEntity instanceof DataSetImportJobInterface) {
                continue;
            }

            $this->workerManager->loadingDataFromFileToDataImportTable($connectedDataSource->getId(), $entryIds, $dataSet->getId(), $dataSetImportJobEntity->getJobId());

            $this->doLoadDataFromEntryToDataBaseForAugmentation($connectedDataSource);
        }
    }

    /**
     * @param ImportHistoryInterface[] $importHistories
     */
    public function undoImport($importHistories)
    {
        // delete
        $this->importHistoryRepository->deleteImportedData($importHistories);

        foreach ($importHistories as $importHistory) {
            // replay augmentation
            $dataSourceEntry = $importHistory->getDataSourceEntry();
            foreach ($dataSourceEntry->getDataSource()->getConnectedDataSources() as $connectedDataSource) {
                $this->doLoadDataFromEntryToDataBaseForAugmentation($connectedDataSource);
            }
        }
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     */
    public function doLoadDataFromEntryToDataBaseForAugmentation(ConnectedDataSourceInterface $connectedDataSource)
    {
        /*
         * find all data set are mapped via augmentation transform
         * replay all data of data sources connected with mapped data set
         */
        $mappedDataSet = $connectedDataSource->getDataSet();

        /**
         * @var LinkedMapDataSetInterface[] $linkedMapDataSets
         */
        $linkedMapDataSets = $this->linkedMapDataSetRepository->getByMapDataSet($mappedDataSet);

        foreach ($linkedMapDataSets as $linkedMapDataSet) {
            $linkedMapConnectedDataSource = $linkedMapDataSet->getConnectedDataSource();
            if ($linkedMapConnectedDataSource instanceof ConnectedDataSourceInterface) {
                $linkedMapDataSource = $linkedMapConnectedDataSource->getDataSource();

                /**
                 * @var  DataSourceEntryInterface[] $linkedMapDataSourceEntries
                 */
                $linkedMapDataSourceEntries = $linkedMapDataSource->getDataSourceEntries();
                $entryIds = [];
                foreach ($linkedMapDataSourceEntries as $dsEntry) {
                    $entryIds[] = $dsEntry->getId();
                }

                if (count($entryIds) < 1) {
                    continue;
                }

                $dataSetImportJobEntity = $this->dataSetImportJobManager->createNewDataSetImportJob($linkedMapConnectedDataSource->getDataSet());
                if (!$dataSetImportJobEntity instanceof DataSetImportJobInterface) {
                    continue;
                }

                $this->workerManager->loadingDataFromFileToDataImportTable($linkedMapDataSet->getConnectedDataSource()->getId(), $entryIds, $linkedMapDataSet->getConnectedDataSource()->getDataSet()->getId(), $dataSetImportJobEntity->getJobId());
            }
        }
    }
}