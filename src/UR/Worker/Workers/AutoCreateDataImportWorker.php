<?php

namespace UR\Worker\Workers;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Liuggio\ExcelBundle\Factory;
use UR\DomainManager\DataSetManagerInterface;
use UR\DomainManager\DataSourceEntryImportHistoryManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Entity\Core\DataSourceEntryImportHistory;
use UR\Entity\Core\ImportHistory;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSet\FilterType;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSet\TransformType;
use UR\Service\DataSet\Type;
use UR\Service\DataSource\Csv;
use UR\Service\DataSource\Excel;
use UR\Service\DataSource\Json;
use UR\Service\Parser\Filter\DateFilter;
use UR\Service\Parser\Filter\NumberFilter;
use UR\Service\Parser\Filter\TextFilter;
use UR\Service\Parser\Parser;
use UR\Service\Parser\ParserConfig;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\ComparisonPercent;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;

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

    /**
     * @var DataSourceEntryImportHistoryManagerInterface
     */
    private $dataSourceEntryImportHistoryManager;

    /**
     * @var Factory
     */
    private $phpExcel;

    function __construct(DataSetManagerInterface $dataSetManager, ImportHistoryManagerInterface $importHistoryManager, DataSourceEntryImportHistoryManagerInterface $dataSourceEntryImportHistoryManager, EntityManagerInterface $em, Factory $phpExcel)
    {
        $this->dataSetManager = $dataSetManager;
        $this->importHistoryManager = $importHistoryManager;
        $this->dataSourceEntryImportHistoryManager = $dataSourceEntryImportHistoryManager;
        $this->em = $em;
        $this->phpExcel = $phpExcel;
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
//            $importHistoryEntity->setDescription();
            $this->importHistoryManager->save($importHistoryEntity);

            //create or update empty dataSet table
            $this->createEmptyDataSetTable($dataSet, $dataSetLocator, $dataSetSynchronizer, $conn);

            //get all dataSource entries
            $dse = $connectedDataSource->getDataSource()->getDataSourceEntries();

            $parser = new Parser();

            /**@var DataSourceEntryInterface $item */
            foreach ($dse as $item) {

                if (strcmp($connectedDataSource->getDataSource()->getFormat(), 'csv') === 0) {
                    /**@var Csv $file*/
                    $file = (new Csv($item->getPath()))->setDelimiter(',');
                } else if (strcmp($connectedDataSource->getDataSource()->getFormat(), 'excel') === 0) {
                    /**@var Excel $file*/
                    $file = new \UR\Service\DataSource\Excel($item->getPath(), $this->phpExcel);
                } else {
                    $file = new Json($item->getPath());
                }
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

                $validRequires = true;
                foreach ($connectedDataSource->getRequires() as $require) {
                    if (!array_key_exists($require, $parserConfig->getAllColumnMappings())) {
                        $validRequires = false;
                        break;
                    }
                }

                if (!$validRequires) {
                    $this->createDataSourceEntryHistory($item, $importHistoryEntity, "failure", "error when mapping require fields");
                    continue;
                }

                //filter
                $this->filterDataSetTable($connectedDataSource, $parserConfig);

                //transform
                $this->transformDataSetTable($connectedDataSource, $parserConfig);

                // import

                $collectionParser = $parser->parse($file, $parserConfig);

                if (is_array($collectionParser)) {
                    $desc = "";
                    if (strcmp($collectionParser["error"], "filter")===0) {
                        $desc = "error when Filter file at row " . $collectionParser["row"] . " column " . $collectionParser["column"];
                    }

                    if (strcmp($collectionParser["error"], "transform")===0) {
                        $desc = "error when Transform file at row " . $collectionParser["row"] . " column " . $collectionParser["column"];
                    }

                    $this->createDataSourceEntryHistory($item, $importHistoryEntity, "failure", $desc);
                    continue;
                }

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

            if (strcmp($value, Type::NUMBER) === 0) {
                $dataSetTable->addColumn($key, "decimal", ["notnull" => false]);
            } else if (strcmp($value, Type::DECIMAL) === 0) {
                $dataSetTable->addColumn($key, $value, ["scale" => 2, "notnull" => false]);
            } else {
                $dataSetTable->addColumn($key, $value, ["notnull" => false]);
            }
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

    function filterDataSetTable(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {
        $filters = $connectedDataSource->getFilters();
        foreach ($filters as $field => $filter) {
            // filter Date
            if (strcmp($filter[FilterType::TYPE], Type::DATE) === 0) {
                $parserConfig->filtersColumn($field, new DateFilter($filter[FilterType::FORMAT], $filter[FilterType::FROM], $filter[FilterType::TO]));
            }

            if (strcmp($filter[FilterType::TYPE], Type::TEXT) === 0) {
                $parserConfig->filtersColumn($field, new TextFilter($filter[FilterType::COMPARISON], $filter[FilterType::COMPARE_VALUE]));
            }

            if (strcmp($filter[FilterType::TYPE], Type::NUMBER) === 0) {
                $parserConfig->filtersColumn($field, new NumberFilter($filter[FilterType::COMPARISON], $filter[FilterType::COMPARE_VALUE]));
            }
        }
    }

    function transformDataSetTable(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {
        $transforms = $connectedDataSource->getTransforms();

        foreach ($transforms[Type::SINGLE_FIELD] as $field => $trans) {

            if ($parserConfig->hasColumnMapping($field)) {

                //TODO WILL BE CHANGE IN FUTURE
                if (strcmp($trans[TransformType::TYPE], TransformType::DATE) === 0) {
                    $parserConfig->transformColumn($field, new DateFormat($trans[FilterType::FROM], 'Y-m-d'));
                }

                if (strcmp($trans[TransformType::TYPE], TransformType::NUMBER) === 0) {
                }
            }
        }

        foreach ($transforms[Type::ALL_FIELD] as $field => $trans) {

            //todo will be change in the future
            if (strcmp($field, TransformType::GROUP_BY) === 0) {
                $parserConfig->transformCollection(new GroupByColumns($trans));
                continue;
            }

            if (strcmp($field, TransformType::SORT_BY) === 0) {
                $parserConfig->transformCollection(new SortByColumns($trans));
                continue;
            }

            if (strcmp($field, TransformType::ADD_FIELD) === 0) {

                foreach ($trans as $f => $v) {
                    $parserConfig->transformCollection(new AddField($f, $v));
                }
                continue;
            }

            if (strcmp($field, TransformType::ADD_CALCULATED_FIELD) === 0) {

                foreach ($trans as $f => $expression) {
//                    $parserConfig->transformCollection(new AddCalculatedField($expressionLanguage, $f, $expression, 0));
                }
                continue;
            }

            if (strcmp($field, TransformType::COMPARISON_PERCENT) === 0) {
                foreach ($trans as $f => $expression) {
                    $parserConfig->transformCollection(new ComparisonPercent($f, $expression[0], $expression[1]));
                }
                continue;
            }
        }
    }

    function createDataSourceEntryHistory(DataSourceEntryInterface $item, $importHistoryEntity, $status, $desc)
    {
        $dseImportHistoryEntity = new DataSourceEntryImportHistory();
        $dseImportHistoryEntity->setDataSourceEntry($item);
        $dseImportHistoryEntity->setImportHistory($importHistoryEntity);
        $dseImportHistoryEntity->setStatus($status);
        $dseImportHistoryEntity->setDescription($desc);
        $this->dataSourceEntryImportHistoryManager->save($dseImportHistoryEntity);
    }
}