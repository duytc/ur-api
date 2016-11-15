<?php

namespace UR\Worker\Workers;

use StdClass;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Import\AutoImportDataInterface;

class ReImportWhenNewEntryReceived
{

    /** @var AutoImportDataInterface $autoImport */
    private $autoImport;

    /**
     * @var DataSetManagerInterface
     */
    private $dataSetManager;

    /**
     * @var DataSourceEntryManagerInterface
     */
    private $dataSourceEntryManager;


    function __construct(DataSetManagerInterface $dataSetManager, AutoImportDataInterface $autoImport, DataSourceEntryManagerInterface $dataSourceEntryManager)
    {
        $this->dataSetManager = $dataSetManager;
        $this->autoImport = $autoImport;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
    }

    function reImportWhenNewEntryReceived(StdClass $params)
    {
        $entryIds = $params->entryIds;
        foreach ($entryIds as $entryId) {
            /**@var DataSourceEntryInterface $dataSourceEntry */
            $dataSourceEntry = $this->dataSourceEntryManager->find($entryId);
            $connectedDataSources = $dataSourceEntry->getDataSource()->getConnectedDataSources();

            $this->autoImport->autoCreateDataImport($connectedDataSources, $dataSourceEntry);
        }
    }
}