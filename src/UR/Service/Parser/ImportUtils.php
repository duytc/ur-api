<?php

namespace UR\Service\Parser;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\TableDiff;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSet\FilterType;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DataSet\TransformType;
use UR\Service\DataSet\Type;
use UR\Service\Import\ImportDataLogger;
use UR\Service\Parser\Filter\DateFilter;
use UR\Service\Parser\Filter\NumberFilter;
use UR\Service\Parser\Filter\TextFilter;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Column\DateFormat;

class ImportUtils
{
    function createEmptyDataSetTable(DataSetInterface $dataSet, Locator $dataSetLocator, Synchronizer $dataSetSynchronizer, Connection $conn, ImportDataLogger $logger)
    {
        $schema = new Schema();

        $dataSetTable = $schema->createTable($dataSetLocator->getDataSetImportTableName($dataSet->getId()));
        $dataSetTable->addColumn("__id", "integer", array("autoincrement" => true, "unsigned" => true));
        $dataSetTable->setPrimaryKey(array("__id"));
        $dataSetTable->addColumn("__data_source_id", "integer", array("unsigned" => true, "notnull" => true));
        $dataSetTable->addColumn("__import_id", "integer", array("unsigned" => true, "notnull" => true));
        $dataSetTable->addColumn(DataSetInterface::UNIQUE_ID_COLUMN, Type::TEXT, array("notnull" => true));

        // create import table
        //// add dimensions
        foreach ($dataSet->getDimensions() as $key => $value) {
            $dataSetTable->addColumn($key, $value, ["notnull" => false, "default" => null]);
        }

        //// add metrics
        foreach ($dataSet->getMetrics() as $key => $value) {
            if (strcmp($value, Type::NUMBER) === 0) {
                $dataSetTable->addColumn($key, "decimal", ["precision" => 20, "scale" => 0, "notnull" => false, "default" => null]);
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

        //// create table
        try {
            $dataSetSynchronizer->syncSchema($schema);

            $truncateSql = $conn->getDatabasePlatform()->getTruncateTableSQL($dataSetLocator->getDataSetImportTableName($dataSet->getId()));

            $conn->exec($truncateSql);
            if ($dataSet->getAllowOverwriteExistingData()) {
                $sql = sprintf("ALTER IGNORE TABLE %s ADD UNIQUE INDEX %s (%s(255))", $dataSetTable->getName(), DataSetInterface::UNIQUE_HASH_INX, DataSetInterface::UNIQUE_ID_COLUMN);
                $conn->exec($sql);
            }
        } catch (\Exception $e) {
            $logger->doExceptionLogging("Cannot Sync Schema " . $schema->getName());
        }
    }

    function alterDataSetTable(DataSetInterface $dataSet, Connection $conn, $newColumns, $updateColumns, $deletedColumns, $uniqueKeyChanges)
    {
        $schema = new Schema();
        $dataSetLocator = new Locator($conn);
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataTable = $dataSetLocator->getDataSetImportTable($dataSet->getId());

        // check if table not existed
        if (!$dataTable) {
            return;
        }

        $delCols = [];
        $addCols = [];
        $editCols = [];
        foreach ($deletedColumns as $deletedColumn => $type) {
            $delCols[] = $dataTable->getColumn($deletedColumn);
            $dataTable->dropColumn($deletedColumn);
        }

        foreach ($newColumns as $newColumn => $type) {
            if (strcmp($type, Type::NUMBER) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, "decimal", ["precision" => 20, "scale" => 0, "notnull" => false, "default" => null]);
            } else if (strcmp($type, Type::DECIMAL) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["precision" => 25, "scale" => 12, "notnull" => false, "default" => null]);
            } else if (strcmp($type, Type::MULTI_LINE_TEXT) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, Type::TEXT, ["notnull" => false, "default" => null]);
            } else if (strcmp($type, Type::DATE) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, Type::DATE, ["notnull" => false, "default" => null]);
            } else {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["notnull" => false, "default" => null]);
            }
        }

        $updateTable = new TableDiff($dataTable->getName(), $addCols, $editCols, $delCols, [], [], []);
        try {
            $dataSetSynchronizer->syncSchema($schema);
            $alterSqls = $conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
            foreach ($updateColumns as $updateColumn) {
                foreach ($updateColumn as $oldName => $newName) {
                    $curCol = $dataTable->getColumn($oldName);
                    $sql = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldName, $newName, $curCol->getType()->getName());
                    if (strtolower($curCol->getType()->getName()) === strtolower(Type::NUMBER) || strtolower($curCol->getType()->getName()) === strtolower(Type::DECIMAL)) {
                        $sql .= sprintf("(%s,%s)", $curCol->getPrecision(), $curCol->getScale());
                    }
                    $alterSqls[] = $sql;
                }
            }

            if (count($uniqueKeyChanges) > 0) {
                if ($uniqueKeyChanges[0] === false && $uniqueKeyChanges[1] === true) {
                    if (!$dataTable->hasIndex(DataSetInterface::UNIQUE_HASH_INX)) {
                        $sql = sprintf("ALTER IGNORE TABLE %s ADD UNIQUE INDEX %s (%s(255))", $dataTable->getName(), DataSetInterface::UNIQUE_HASH_INX, DataSetInterface::UNIQUE_ID_COLUMN);
                        $alterSqls[] = $sql;
                    }
                }

                if ($uniqueKeyChanges[0] === true && $uniqueKeyChanges[1] === false) {
                    if ($dataTable->hasIndex(DataSetInterface::UNIQUE_HASH_INX)) {
                        $sql = sprintf("ALTER TABLE %s DROP INDEX %s", $dataTable->getName(), DataSetInterface::UNIQUE_HASH_INX);
                        $alterSqls[] = $sql;
                    }
                }
            }

            foreach ($alterSqls as $alterSql) {
                $conn->exec($alterSql);
            }

        } catch (\Exception $e) {
            throw new \mysqli_sql_exception("Cannot Sync Schema " . $schema->getName());
        }
    }

    function createMapFieldsConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig, array $columns)
    {
        foreach ($columns as $column) {
            $column = strtolower(trim($column));
            foreach ($connectedDataSource->getMapFields() as $k => $v) {
                if (strcmp($column, $k) === 0) {
                    $parserConfig->addColumn($k, $v);
                    break;
                }
            }
        }
    }

    function createFilterConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {
        $filters = $connectedDataSource->getFilters();
        foreach ($filters as $filter) {
            // filter Date
            if (strcmp($filter[FilterType::TYPE], Type::DATE) === 0) {
                $mapFields = $connectedDataSource->getMapFields();
                if (array_key_exists($filter[FilterType::FIELD], $mapFields)) {
                    $format = $this->getFormatDateFromTransform($connectedDataSource, $mapFields[$filter[FilterType::FIELD]]);
                    $filter[FilterType::FORMAT] = $format;
                }
                $parserConfig->addFiltersColumn($filter[FilterType::FIELD], new DateFilter($filter));
            }

            if (strcmp($filter[FilterType::TYPE], Type::TEXT) === 0) {
                $parserConfig->addFiltersColumn($filter[FilterType::FIELD], new TextFilter($filter));
            }

            if (strcmp($filter[FilterType::TYPE], Type::NUMBER) === 0) {
                $parserConfig->addFiltersColumn($filter[FilterType::FIELD], new NumberFilter($filter));
            }
        }
    }

    function getFormatDateFromTransform(ConnectedDataSourceInterface $connectedDataSource, $field)
    {
        $transforms = $connectedDataSource->getTransforms();
        foreach ($transforms as $transform) {
            if (strcmp($transform[TransformType::FIELD], $field) === 0) {
                return $transform[TransformType::FROM];
            }
        }

        return null;
    }

    function createTransformConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig, DataSourceEntryInterface $dataSourceEntry)
    {
        $transforms = $connectedDataSource->getTransforms();
        $dimensions = $connectedDataSource->getDataSet()->getDimensions();
        $metrics = $connectedDataSource->getDataSet()->getMetrics();
        $allFields = array_merge($dimensions, $metrics);
        $collectionTransforms = $connectedDataSource->getCollectionTransforms();

        if ($collectionTransforms->getGroupByTransforms() !== null) {
            $parserConfig->addTransformCollection($collectionTransforms->getGroupByTransforms());
        }

        if ($collectionTransforms->getSortByTransforms() !== null) {
            $parserConfig->addTransformCollection($collectionTransforms->getSortByTransforms());
        }

        foreach ($collectionTransforms->getAddFieldTransform() as $addFieldTransform) {
            $internalFieldValue = $this->getMetadataInternalValue($addFieldTransform->getTransformValue(), $dataSourceEntry);

            if ($internalFieldValue !== null) {
                $addFieldTransform->setTransformValue($internalFieldValue);
            }

            if (!array_key_exists($addFieldTransform->getColumn(), $allFields)) {
                continue;
            }

            $addFieldTransform->setType($allFields[$addFieldTransform->getColumn()]);
            $parserConfig->addTransformCollection($addFieldTransform);
        }

        foreach ($collectionTransforms->getAddCalculatedFieldTransforms() as $addCalculatedFieldTransform) {
            $parserConfig->addTransformCollection($addCalculatedFieldTransform);
        }

        foreach ($collectionTransforms->getComparisonPercentTransforms() as $comparisonPercentTransform) {
            $parserConfig->addTransformCollection($comparisonPercentTransform);
        }

        foreach ($collectionTransforms->getReplaceTextTransforms() as $replaceTextTransform) {
            $parserConfig->addTransformCollection($replaceTextTransform);
        }

        foreach ($collectionTransforms->getReplacePatternTransforms() as $replacePattern) {
            $internalField = sprintf("[%s]", $replacePattern->getField());
            if (in_array($internalField, TransformType::$internalFields)) {
                $parserConfig->addTransformCollection(new AddField($replacePattern->getField(), $this->getMetadataInternalValue($internalField, $dataSourceEntry), Type::TEXT));
            }

            $targetField = $replacePattern->getTargetField();

            if ($targetField !== null || array_key_exists($targetField, $allFields)) {
                $targetFieldType = $allFields[$targetField];
                if (strcmp($targetFieldType, Type::DATE) === 0 && !$parserConfig->hasColumnMapping($targetField)) {
                    $parserConfig->addColumn($targetField, $targetField);
                }
            }

            $parserConfig->addTransformCollection($replacePattern);
        }

        foreach ($transforms as $transform) {
            if (TransformType::isDateOrNumberTransform($transform[TransformType::TYPE]) && $parserConfig->hasColumnMapping($transform[TransformType::FIELD])) {
                if (strcmp($transform[TransformType::TYPE], TransformType::DATE) === 0) {
                    $parserConfig->addTransformColumn($transform[TransformType::FIELD], new DateFormat($transform[TransformType::FROM], 'Y-m-d'));
                }
            }
        }
    }

    private function getMetadataInternalValue($internalField, DataSourceEntryInterface $dataSourceEntry)
    {
        $metadata = $dataSourceEntry->getDataSourceEntryMetadata();
        $result = null;
        switch ($internalField) {
            case TransformType::FILE_NAME:
                $result = $dataSourceEntry->getFileName();
                break;

            case TransformType::EMAIL_SUBJECT:
                $result = $metadata->getEmailSubject();
                break;

            case TransformType::EMAIL_BODY:
                $result = $metadata->getEmailBody();
                break;

            case TransformType::EMAIL_DATE_TIME:
                $result = $metadata->getEmailDatetime();
                break;

            default:
                break;
        }

        return $result;
    }
}