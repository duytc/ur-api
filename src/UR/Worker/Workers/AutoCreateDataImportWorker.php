<?php

namespace UR\Worker\Workers;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\ImportHistory;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;

class AutoCreateDataImportWorker
{
    /** @var EntityManagerInterface $em */
    private $em;

    /**
     * @var DataSetManagerInterface
     */
    private $dataSetManager;

    /**
     * @var ImportHistoryManagerInterface
     */
    private $importHistoryManager;

    function __construct(DataSetManagerInterface $dataSetManager, ImportHistoryManagerInterface $importHistoryManager, EntityManagerInterface $em)
    {
        $this->dataSetManager = $dataSetManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->em = $em;
    }

    function autoCreateDataImport($dataSetId)
    {
        // get all info of job..
        /**@var DataSetInterface $dataSet */
        $dataSet = $this->dataSetManager->find($dataSetId);

        if ($dataSet === null) {
            throw new InvalidArgumentException('not found Dataset with this ID');
        }

        $connectedDataSources = $dataSet->getConnectedDataSources();

        /**@var ConnectedDataSourceInterface $connectedDataSource */
        foreach ($connectedDataSources as $connectedDataSource) {

            // create importHistory: createdTime
            $importHistoryEntity = new ImportHistory();
            $importHistoryEntity->setConnectedDataSource($connectedDataSource);
            $this->importHistoryManager->save($importHistoryEntity);

            $conn= $this->em->getConnection();
            $dataSetLocator= new Locator($conn);
            $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
            $schema = new Schema();
            $dataSetTable = $schema->createTable($dataSetLocator->getDataSetName($importHistoryEntity->getId()));
            $dataSetTable->addColumn("__id", "integer", array("autoincrement" => true, "unsigned" => true));
            $dataSetTable->setPrimaryKey(array("__id"));
            $dataSetTable->addColumn("__data_source_id", "integer", array("unsigned" => true, "notnull" => true));
            $dataSetTable->addColumn("__import_id", "integer", array("unsigned" => true, "notnull" => true));

            //mapping
            // add dimensions
            foreach ($dataSet->getDimensions() as $key => $value){
                $dataSetTable->addColumn($key, $value);
            }

            // add metrics
            foreach ($dataSet->getMetrics() as $key => $value){
                $dataSetTable->addColumn($key, $value, ["unsigned" => true, "notnull" => false]);
            }

            try {
                $dataSetSynchronizer->syncSchema($schema);
                $truncateSql = $conn->getDatabasePlatform()->getTruncateTableSQL($dataSetLocator->getDataSetName($importHistoryEntity->getId()));
                $conn->exec($truncateSql);
            } catch (\Exception $e) {
                echo "could not sync schema";
                exit(1);
            }
        }

        // parse: Giang


        // filter


        // transform


        // import??


        // create dataImport: dynamic table


        // update importHistory: finishedTime
    }
}