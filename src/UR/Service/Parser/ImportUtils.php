<?php

namespace UR\Service\Parser;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\TableDiff;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FilterType;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSet\TransformType;
use UR\Service\DataSet\Type;
use UR\Service\DataSource\DataSourceInterface;
use UR\Service\Parser\Filter\DateFilter;
use UR\Service\Parser\Filter\NumberFilter;
use UR\Service\Parser\Filter\TextFilter;
use UR\Service\Parser\Transformer\Collection\AddCalculatedField;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\ComparisonPercent;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Column\DateFormat;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ImportUtils
{
    function createEmptyDataSetTable(DataSetInterface $dataSet, Locator $dataSetLocator, Synchronizer $dataSetSynchronizer, Connection $conn)
    {
        $schema = new Schema();
        $dataSetTable = $schema->createTable($dataSetLocator->getDataSetImportTableName($dataSet->getId()));
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
                $dataSetTable->addColumn($key, "decimal", ["notnull" => false, "default" => null]);
            } else if (strcmp($value, Type::DECIMAL) === 0) {
                $dataSetTable->addColumn($key, $value, ["precision" => 25, "scale" => 12, "notnull" => false, "default" => null]);
            } else if (strcmp($value, Type::MULTI_LINE_TEXT) === 0) {
                $dataSetTable->addColumn($key, Type::TEXT, ["notnull" => false, "default" => null]);
            } else if (strcmp($value, Type::DATE) === 0) {
                $dataSetTable->addColumn($key, Type::DATE, ["notnull" => false, "default" => null]);
            } else {
                $dataSetTable->addColumn($key, $value, ["notnull" => false, "default" => null]);
            }
        }

        // create table
        try {
            $dataSetSynchronizer->syncSchema($schema);
            $truncateSql = $conn->getDatabasePlatform()->getTruncateTableSQL($dataSetLocator->getDataSetImportTableName($dataSet->getId()));
            $conn->exec($truncateSql);
        } catch (\Exception $e) {
            echo "could not sync schema";
            exit(1);
        }
    }

    function alterDataSetTable(DataSetInterface $dataSet, Connection $conn, $deletedColumns, $newColumns)
    {
        $schema = new Schema();
        $dataSetLocator = new Locator($conn);
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataTable = $dataSetLocator->getDataSetImportTable($dataSet->getId());
        // check if table not existed
        if (!$dataTable) {
            return;
        }
        $dataTable->getName();
        $delCols = [];
        $addCols = [];
        foreach ($deletedColumns as $deletedColumn => $type) {
            $delCols[] = $dataTable->getColumn($deletedColumn);
        }

        foreach ($newColumns as $newColumn => $type) {

            if (strcmp($type, Type::NUMBER) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, "decimal", ["notnull" => false, "default" => null]);
            } else if (strcmp($type, Type::DECIMAL) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["precision" => 25, "scale" => 12, "notnull" => false, "default" => null]);
            } else if (strcmp($type, Type::MULTI_LINE_TEXT) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, Type::TEXT, ["notnull" => false, "default" => null]);
            } else {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["notnull" => false, "default" => null]);
            }
        }

        $updateTable = new TableDiff($dataTable->getName(), $addCols, array(), $delCols);
        try {
            $dataSetSynchronizer->syncSchema($schema);
            $truncateSqls = $conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
            foreach ($truncateSqls as $truncateSql) {
                $conn->exec($truncateSql);
            }
        } catch (\Exception $e) {
            echo "could not sync schema";
            exit(1);
        }
    }

    function mappingFile(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig, DataSourceInterface $file)
    {
        $columns = $file->getColumns();

        foreach ($columns as $column) {
            {
                $column = strtolower(trim($column));
                foreach ($connectedDataSource->getMapFields() as $k => $v) {
                    if (strcmp($column, $k) === 0) {
                        $parserConfig->addColumn($k, $v);
                        break;
                    }
                }
            }
        }
    }

    function filterDataSetTable(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {
        $filters = $connectedDataSource->getFilters();
        foreach ($filters as $filter) {
            // filter Date
            if (strcmp($filter[FilterType::TYPE], Type::DATE) === 0) {
                $parserConfig->filtersColumn($filter[FilterType::FIELD], new DateFilter($filter[FilterType::FORMAT], $filter[FilterType::FROM], $filter[FilterType::TO]));
            }

            if (strcmp($filter[FilterType::TYPE], Type::TEXT) === 0) {
                $parserConfig->filtersColumn($filter[FilterType::FIELD], new TextFilter($filter[FilterType::COMPARISON], $filter[FilterType::COMPARE_VALUE]));
            }

            if (strcmp($filter[FilterType::TYPE], Type::NUMBER) === 0) {
                $parserConfig->filtersColumn($filter[FilterType::FIELD], new NumberFilter($filter[FilterType::COMPARISON], $filter[FilterType::COMPARE_VALUE]));
            }
        }
    }

    function transformDataSetTable(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {
        $transforms = $connectedDataSource->getTransforms();

        $sortByColumns = array();
        foreach ($transforms as $transform) {

            if (TransformType::isDateOrNumberTransform($transform[TransformType::TYPE]) && $parserConfig->hasColumnMapping($transform[TransformType::FIELD])) {

                //
                //TODO WILL BE CHANGE IN FUTURE
                if (strcmp($transform[TransformType::TYPE], TransformType::DATE) === 0) {
                    $parserConfig->transformColumn($transform[TransformType::FIELD], new DateFormat($transform[TransformType::FROM], 'Y-m-d'));
                }

                if (strcmp($transform[TransformType::TYPE], TransformType::NUMBER) === 0) {

                }
            } else {

                if (strcmp($transform[TransformType::TYPE], TransformType::GROUP_BY) === 0) {
                    $parserConfig->transformCollection(new GroupByColumns($transform[TransformType::FIELDS]));
                    continue;
                }

                if (strcmp($transform[TransformType::TYPE], TransformType::SORT_BY) === 0) {
                    $sortByColumns[] = $transform[TransformType::FIELDS];
                    continue;
                }

                if (strcmp($transform[TransformType::TYPE], TransformType::ADD_FIELD) === 0) {

                    foreach ($transform[TransformType::FIELDS] as $addfields) {
                        $parserConfig->transformCollection(new AddField($addfields[TransformType::FIELD], $addfields[TransformType::VALUE]));
                    }
                    continue;
                }

                if (strcmp($transform[TransformType::TYPE], TransformType::ADD_CALCULATED_FIELD) === 0) {
                    $expressionLanguage = new ExpressionLanguage;
                    foreach ($transform[TransformType::FIELDS] as $f => $addCalculatedFields) {
                        $parserConfig->transformCollection(new AddCalculatedField($expressionLanguage, $addCalculatedFields[TransformType::FIELD], $addCalculatedFields[TransformType::EXPRESSION]));
                    }
                    continue;
                }

                if (strcmp($transform[TransformType::TYPE], TransformType::COMPARISON_PERCENT) === 0) {
                    foreach ($transform[TransformType::FIELDS] as $comparisonPercents) {
                        $parserConfig->transformCollection(new ComparisonPercent($comparisonPercents[TransformType::FIELD], $comparisonPercents[TransformType::NUMERATOR], $comparisonPercents[TransformType::DENOMINATOR]));
                    }
                    continue;
                }
            }
        }

        if ($sortByColumns) {
            $parserConfig->transformCollection(new SortByColumns($sortByColumns));
        }
    }
}