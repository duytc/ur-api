<?php

namespace UR\Service\Import;


use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;
use UR\Worker\Manager;

class LoadingDataService
{
    /** @var Manager */
    private $workerManager;

    /**
     * LoadingDataService constructor.
     * @param Manager $workerManager
     */
    public function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    public function doLoadDataFromEntryToDataBase(DataSourceEntryInterface $dataSourceEntry, LinkedMapDataSetRepositoryInterface $linkedMapDataSetRepository, $undo = false)
    {
        /*
         * load data from this entry to data base
         */
        if (!$undo)
            foreach ($dataSourceEntry->getDataSource()->getConnectedDataSources() as $connectedDataSource) {
                $this->workerManager->loadingDataFromFileToDataImportTable($connectedDataSource->getId(), $dataSourceEntry->getId(), $connectedDataSource->getDataSet()->getId());
            }

        /*
         * finding all connected data sources relate to entry
         * find all data set are mapped via augmentation transform
         * replay all data of data sources connected with mapped data set
         */
        foreach ($dataSourceEntry->getDataSource()->getConnectedDataSources() as $connectedDataSource) {
            $mappedDataSet = $connectedDataSource->getDataSet();

            /**
             * @var LinkedMapDataSetInterface[] $linkedMapDataSets
             */
            $linkedMapDataSets = $linkedMapDataSetRepository->getByMapDataSet($mappedDataSet);

            foreach ($linkedMapDataSets as $linkedMapDataSet) {
                $linkedMapDataSource = $linkedMapDataSet->getConnectedDataSource();
                if ($linkedMapDataSource instanceof ConnectedDataSourceInterface) {
                    $linkedMapDataSource = $linkedMapDataSource->getDataSource();

                    /**
                     * @var  DataSourceEntryInterface[] $linkedMapDataSourceEntries
                     */
                    $linkedMapDataSourceEntries = $linkedMapDataSource->getDataSourceEntries();
                    foreach ($linkedMapDataSourceEntries as $dsEntry) {
                        $this->workerManager->loadingDataFromFileToDataImportTable($linkedMapDataSet->getConnectedDataSource()->getId(), $dsEntry->getId(), $linkedMapDataSet->getConnectedDataSource()->getDataSet()->getId());
                    }
                }
            }
        }
    }
}