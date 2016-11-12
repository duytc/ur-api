<?php

namespace UR\Worker\Workers;

use StdClass;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\ImportHistory;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\Import\AutoImportDataInterface;
use UR\Service\Parser\ImportUtils;

class AutoImportDataSetChangedWorker
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

    function reImportWhenDataSetChange(StdClass $params)
    {
        $entryIds = $params->entryIds;
        foreach ($entryIds as $entryId) {
            /**@var DataSourceEntryInterface $dataSourceEntry */
            $dataSourceEntry = $this->dataSourceEntryManager->find($entryId);
            $dataSets = $this->dataSetManager->getDataSetByDataSource($dataSourceEntry->getDataSource());
            foreach ($dataSets as $dataSet) {
                if ($dataSet === null) {
                    throw new InvalidArgumentException('not found Dataset with this ID');
                }

                $this->autoImport->autoCreateDataImport($dataSet, $dataSourceEntry);
            }
        }
    }
}