<?php

namespace UR\Worker\Workers;

use StdClass;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Exception\SqlLockTableException;
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

    private $queue;


    function __construct(AutoImportDataInterface $autoImport, DataSourceEntryManagerInterface $dataSourceEntryManager, ConnectedDataSourceManagerInterface $connectedDataSourceManager, $queue)
    {
        $this->autoImport = $autoImport;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSourceManager = $connectedDataSourceManager;
        $this->queue = $queue;
    }

    public function loadingDataFromFileToDataImportTable(StdClass $params, $job, $tube)
    {
        $connectedDataSourceId = $params->connectedDataSourceId;
        $entryId = $params->entryId;
        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->dataSourceEntryManager->find($entryId);
        /**@var ConnectedDataSourceInterface $connectedDataSource */
        try {
            $connectedDataSource = $this->connectedDataSourceManager->find($connectedDataSourceId);
        } catch (\Exception $exception) {
            stdOut('xxxxx');
        }

        if ($dataSourceEntry !== null) {
            try {
                $this->autoImport->loadingDataFromFileToDatabase($connectedDataSource, $dataSourceEntry);
            } catch (SqlLockTableException $exception) {
                $this->queue->putInTube($tube, $job->getData(), 0, 15);
            }
        }
    }
}