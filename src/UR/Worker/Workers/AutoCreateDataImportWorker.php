<?php

namespace UR\Worker\Workers;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\ImportHistory;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSource\Csv;
use UR\Service\Parser\Parser;
use UR\Service\Parser\ParserConfig;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Column\DateFormat;

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

        $connectedDataSources = $dataSet->getConnectedDataSources();

        /**@var ConnectedDataSourceInterface $connectedDataSource */
        foreach ($connectedDataSources as $connectedDataSource) {

            // create importHistory: createdTime
            $importHistoryEntity = new ImportHistory();
            $importHistoryEntity->setConnectedDataSource($connectedDataSource);
            $this->importHistoryManager->save($importHistoryEntity);

            //create or update empty dataSet table
            $this->createEmptyDataSetTable($dataSet, $dataSetLocator, $dataSetSynchronizer, $conn);

            //get all dataSource entries
            $dse = $connectedDataSource->getDataSource()->getDataSourceEntries();

            $parser = new Parser();

            /**@var DataSourceEntryInterface $item */
            foreach ($dse as $item) {
                // parse: Giang
                $file = (new Csv($item->getPath()))->setDelimiter(',');
                // mapping
                $parserConfig = new ParserConfig();
                $columns = $file->getColumns();
                foreach ($columns as $column) {
                    foreach ($connectedDataSource->getMapFields() as $k => $v) {
                        if (strcmp($column, $k) === 0) {
                            $parserConfig->addColumn($k, $v);
                            break;
                        }
                    }
                }

                //transform
                $this->transformDataSetTable($connectedDataSource, $parserConfig);

                // import
                $collectionParser = $parser->parse($file, $parserConfig);

                $ds1 = $dataSetLocator->getDataSet($dataSetId);

                $dataSetImporter->importCollection($collectionParser, $ds1);

            }
        }

    }

    function createEmptyDataSetTable(DataSetInterface $dataSet, Locator $dataSetLocator, Synchronizer $dataSetSynchronizer, Connection $conn)
    {
        $schema = new Schema();
        $dataSetTable = $schema->createTable($dataSetLocator->getDataSetName($dataSet->getId()));
        $dataSetTable->addColumn("__id", "integer", array("autoincrement" => true, "unsigned" => true));
        $dataSetTable->setPrimaryKey(array("__id"));
        $dataSetTable->addColumn("__data_source_id", "integer", array("unsigned" => true, "notnull" => true));
        $dataSetTable->addColumn("__import_id", "integer", array("unsigned" => true, "notnull" => true));

        // create import table
        // add dimensions
        foreach ($dataSet->getDimensions() as $key => $value) {
            $dataSetTable->addColumn($key, $value);
        }

// add metrics
        foreach ($dataSet->getMetrics() as $key => $value) {
            $dataSetTable->addColumn($key, $value, ["unsigned" => true, "notnull" => false]);
        }

// create table
        try {
            $dataSetSynchronizer->syncSchema($schema);
            $truncateSql = $conn->getDatabasePlatform()->getTruncateTableSQL($dataSetLocator->getDataSetName($dataSet->getId()));
            $conn->exec($truncateSql);
        } catch (\Exception $e) {
            echo "could not sync schema";
            exit(1);
        }
    }

    function mappingDataSetTable(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {

    }

    function transformDataSetTable(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {
        $expressionLanguage = new ExpressionLanguage();
        $transforms = $connectedDataSource->getTransforms();

        foreach ($transforms['single-field'] as $field => $trans) {
            if ($parserConfig->hasColumnMapping($field)) {
                $parserConfig->transformColumn($field, new DateFormat($trans['from'], $trans['to']));
            }
        }

        foreach ($transforms['all-fields'] as $field => $trans) {
            if (strcmp($field, 'groupBy') === 0) {
                $parserConfig->transformCollection(new GroupByColumns($trans));
            }
            if (strcmp($field, 'sortBy') === 0) {
                $parserConfig->transformCollection(new SortByColumns($trans));
            }
        }
    }
}