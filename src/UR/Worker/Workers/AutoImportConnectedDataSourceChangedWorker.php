<?php

namespace UR\Worker\Workers;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use StdClass;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\Import\AutoImportDataInterface;

class AutoImportConnectedDataSourceChangedWorker
{
    /** @var EntityManagerInterface $em */
    private $em;

    /** @var AutoImportDataInterface $autoImport */
    private $autoImport;

    /**
     * @var ConnectedDataSourceManagerInterface
     */
    private $connectedDataSourcemManager;

    function __construct(EntityManagerInterface $em, AutoImportDataInterface $autoImport, ConnectedDataSourceManagerInterface $connectedDataSourcemManager)
    {
        $this->em = $em;
        $this->autoImport = $autoImport;
        $this->connectedDataSourcemManager = $connectedDataSourcemManager;
    }

    function importDataWhenConnectedDataSourceChange(StdClass $params)
    {
        $conn = $this->em->getConnection();
        $dataSetLocator = new Locator($conn);
        $dataSetImporter = new Importer($conn);
        $entryIds = $params->entryIds;
        $dataSourceId = $params->dataSourceId;
        $this->autoImport->autoCreateDataImport($entryIds, $dataSourceId, $dataSetImporter, $dataSetLocator);
    }
}