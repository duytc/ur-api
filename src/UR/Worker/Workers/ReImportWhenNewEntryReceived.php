<?php

namespace UR\Worker\Workers;

use StdClass;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
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
            if ($dataSourceEntry === null)
                continue;

            $this->autoImport->autoCreateDataImport($dataSourceEntry);
        }
    }
}