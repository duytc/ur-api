<?php

namespace UR\Service\Import;


use UR\DomainManager\DataSetImportJobManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetImportJob;
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
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param array $entryIds
     */
    public function doLoadDataFromEntryToDataBase(ConnectedDataSourceInterface $connectedDataSource, array $entryIds)
    {
        sort($entryIds);

        $dataSet = $connectedDataSource->getDataSet();

        $parentImportJob = null;
        foreach ($entryIds as $entryId) {
            $jobData = [
                'connectedDataSourceId' => $connectedDataSource->getId(),
                'dataSourceEntryIds' => $entryId
            ];

            $dataSetImportJobEntity = DataSetImportJob::createEmptyDataSetImportJob(
                $dataSet,
                sprintf('load data from entries to data set "%s"', $dataSet->getName()),
                DataSetImportJob::JOB_TYPE_IMPORT,
                $jobData
            );

            $this->dataSetImportJobManager->save($dataSetImportJobEntity);

            if (!$dataSetImportJobEntity instanceof DataSetImportJobInterface) {
                continue;
            }

            $this->workerManager->loadingDataFromFileToDataImportTable($connectedDataSource->getId(), $entryId, $dataSet->getId(), $dataSetImportJobEntity->getJobId());
            $parentImportJob = $dataSetImportJobEntity;
        }

        $this->doLoadDataFromEntryToDataBaseForAugmentation($connectedDataSource, $parentImportJob);
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
     * @param DataSetImportJobInterface $parentImportJob
     */
    public function doLoadDataFromEntryToDataBaseForAugmentation(ConnectedDataSourceInterface $connectedDataSource, DataSetImportJobInterface $parentImportJob = null)
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
                foreach ($linkedMapDataSourceEntries as $dsEntry) {
                    $jobData = [
                        'connectedDataSourceId' => $linkedMapConnectedDataSource->getId(),
                        'dataSourceEntryIds' => $dsEntry->getId(),
                        'mappedDataSetId' => $mappedDataSet->getId(),
                        'mappedConnectedDataSourceId' => $connectedDataSource->getId(),
                    ];

                    $dataSetImportJobEntity = DataSetImportJob::createEmptyDataSetImportJob(
                        $linkedMapConnectedDataSource->getDataSet(),
                        sprintf('load data from entries to linked map data set "%s" with augmentation', $linkedMapConnectedDataSource->getDataSet()->getName()),
                        DataSetImportJob::JOB_TYPE_IMPORT,
                        $jobData,
                        $parentImportJob instanceof DataSetImportJobInterface ? $parentImportJob->getId() : null
                    );

                    $this->dataSetImportJobManager->save($dataSetImportJobEntity);
                    if (!$dataSetImportJobEntity instanceof DataSetImportJobInterface) {
                        continue;
                    }

                    $this->workerManager->loadingDataFromFileToDataImportTable($linkedMapDataSet->getConnectedDataSource()->getId(), $dsEntry->getId(), $linkedMapDataSet->getConnectedDataSource()->getDataSet()->getId(), $dataSetImportJobEntity->getJobId());
                }
            }
        }
    }
}