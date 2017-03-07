<?php

namespace UR\Worker\Workers;

use StdClass;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Import\AutoImportDataInterface;

class LoadingDataFromFileToDataImportTable
{
    /** @var AutoImportDataInterface $autoImport */
    private $autoImport;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;

    /**
     * @var ConnectedDataSourceManagerInterface
     */
    private $connectedDataSourceManager;


    function __construct(AutoImportDataInterface $autoImport, DataSourceEntryManagerInterface $dataSourceEntryManager, ConnectedDataSourceManagerInterface $connectedDataSourceManager)
    {
        $this->autoImport = $autoImport;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
    }

    function loadingDataFromFileToDataImportTable(StdClass $params)
    {
        $connectedDataSourceId = $params->connectedDataSourceId;
        $entryId = $params->entryId;
        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($entryId);
        /**@var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);

        if ($dataSourceEntry !== null)
            $this->autoImport->loadingDataFromFileToDatabase($connectedDataSource, $dataSourceEntry);
    }
}