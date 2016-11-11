<?php

namespace UR\Worker\Workers;

use StdClass;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\Import\AutoImportDataInterface;
use UR\Service\Parser\ImportUtils;

class AutoImportDataSetChangedWorker
{
    /** @var EntityManagerInterface $em */
    private $em;

    /** @var AutoImportDataInterface $autoImport */
    private $autoImport;

    /**
     * @var DataSetManagerInterface
     */
    private $dataSetManager;

    function __construct(DataSetManagerInterface $dataSetManager, AutoImportDataInterface $autoImport, EntityManagerInterface $em)
    {
        $this->dataSetManager = $dataSetManager;
        $this->autoImport = $autoImport;
        $this->em = $em;
    }

    function reImportWhenDataSetChange(StdClass $params)
    {
        $dataSetId = $params->dataSetId;

        $conn = $this->em->getConnection();
        $dataSetLocator = new Locator($conn);
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetImporter = new Importer($conn);

        // get all info of job..
        /**@var DataSetInterface $dataSet */
        $dataSet = $this->dataSetManager->find($dataSetId);

        if ($dataSet === null) {
            throw new InvalidArgumentException('not found Dataset with this ID');
        }
        $importUtils = new ImportUtils();
        //create or update empty dataSet table
        if (!$dataSetLocator->getDataSetImportTable($dataSetId)) {
            $importUtils->createEmptyDataSetTable($dataSet, $dataSetLocator, $dataSetSynchronizer, $conn);
        }

        $connectedDataSources = $dataSet->getConnectedDataSources();

        /**@var ConnectedDataSourceInterface $connectedDataSource */
        foreach ($connectedDataSources as $connectedDataSource) {
            $this->autoImport->autoCreateDataImport($connectedDataSource, $dataSetImporter, $dataSetLocator);
        }
    }
}