<?php

namespace UR\Service\Parser;


use Exception;
use SplDoublyLinkedList;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DataSource\DataSourceInterface;
use UR\Service\DataSource\Excel;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;
use UR\Service\Metadata\DataSourceEntryMetadataFactory;
use UR\Service\Metadata\Email\EmailMetadata;
use UR\Service\Metadata\MetadataInterface;
use UR\Service\Parser\Filter\FilterFactory;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\Augmentation;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\ReplaceText;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;
use UR\Service\Parser\Transformer\TransformerFactory;

class ParsingFileService
{
    protected $parser;

    /**
     * @var ParserConfig $parserConfig
     */
    private $parserConfig;

    /**
     * @var TransformerFactory $transformerFactory
     */
    private $transformerFactory;

    /**
     * @var DataSourceEntryMetadataFactory
     */
    private $dataSourceEntryMetadataFactory;

    private $fileFactory;

    /**
     * ParsingFileService constructor.
     * @param Parser $parser
     * @param DataSourceFileFactory $fileFactory
     */
    public function __construct(Parser $parser, DataSourceFileFactory $fileFactory)
    {
        $this->parser = $parser;
        $this->fileFactory = $fileFactory;
    }

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param int $limit
     * @return Collection
     * @throws Exception
     * @throws ImportDataException
     */
    public function doParser(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource, $limit = null)
    {
        /** @var DataSourceInterface $dataSourceFileData */
        $dataSourceFileData = $this->fileFactory->getFile($connectedDataSource->getDataSource()->getFormat(), $dataSourceEntry->getPath());

        $columnsInFile = $dataSourceFileData->getColumns();
        $columnsInFile = array_map("trim", $columnsInFile);

        $dataRow = $dataSourceFileData->getDataRow();

        if (!is_array($columnsInFile) || count($columnsInFile) < 1) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_HEADER_FOUND);
        }

        if ($dataRow < 1) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND);
        }

        if ($dataSourceFileData instanceof Excel) {
            $formats = $this->getFormatDateForEachFieldInDataSourceFile();
            $dataSourceFileData->setFromDateFormats($formats);
        }

        /* 1. get all row data */
        if (is_numeric($limit) && $limit > 0) {
            $rows = $dataSourceFileData->getLimitedRows($limit);
        } else {
            $rows = $dataSourceFileData->getRows();
        }

        $columnsInFile = array_map('strtolower', $columnsInFile);
        $this->addPrefixForColumnsFromFile($columnsInFile);

        /* mapping field */
        $this->createMapFieldsConfigForConnectedDataSource($connectedDataSource, $this->parserConfig, $columnsInFile);
        if (count($this->parserConfig->getAllColumnMappings()) === 0) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_MAPPING_FAIL);
        }

        $this->validateMissingRequiresColumns($connectedDataSource);

        //filter config
        $this->createFilterConfigForConnectedDataSource($connectedDataSource, $this->parserConfig);

        $newRows = $this->parser->combineRowsWithColumns($columnsInFile, $rows, $this->parserConfig, $connectedDataSource);

        $newRows = $this->removeInvalidRowsDependOnRequiredFields($newRows, $connectedDataSource);

        $newRows = $this->removeNullDateTimeRows($newRows, $connectedDataSource);

        //transform config
        $this->createTransformConfigForConnectedDataSource($connectedDataSource, $this->parserConfig, $dataSourceEntry);

        $collections = $this->parser->parse($columnsInFile, $newRows, $this->parserConfig, $connectedDataSource);

        return $collections;
    }

    /**UpdateConnectedDataSourceWhenDataSetChangedListener
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ParserConfig $parserConfig
     * @param array $fileColumns
     */
    public function createMapFieldsConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig, array $fileColumns)
    {
        $mapFields = $connectedDataSource->getMapFields();
        if (!is_array($mapFields)) {
            return;
        }

        foreach ($fileColumns as $fileColumn) {
            $fileColumn = trim($fileColumn);

            if (array_key_exists($fileColumn, $mapFields)) {
                $parserConfig->addColumn($fileColumn, $mapFields[$fileColumn]);
            }
        }
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ParserConfig $parserConfig
     * @throws Exception
     */
    private function createFilterConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {
        $filters = $connectedDataSource->getFilters();
        if (!is_array($filters)) {
            throw new Exception(sprintf("ConnectedDataSource Filters Setting should be an array"));
        }

        $filterFactory = new FilterFactory();
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $filterObject = $filterFactory->getFilter($filter);
            if ($filterObject === null) {
                continue;
            }

            $filterObject->validate();
            $parserConfig->addFiltersColumn($filterObject->getField(), $filterObject);
        }
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ParserConfig $parserConfig
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    private function createTransformConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig, DataSourceEntryInterface $dataSourceEntry)
    {
        $allDimensionMetrics = $connectedDataSource->getDataSet()->getAllDimensionMetrics();
        $tempFields = array_flip($connectedDataSource->getTemporaryFields());
        $allFields = array_merge($allDimensionMetrics, $tempFields);

        /** Extra fields is field not mapping with data source, they come from Transformation ad Add Field, Extract Pattern ... and need reformat in the end */
        $extraFields = [];

        foreach ($connectedDataSource->getTransforms() as $transform) {
            if (!is_array($transform)) {
                continue;
            }

            $transformObjects = $this->transformerFactory->getTransform($transform);

            if ($transformObjects instanceof DateFormat || $transformObjects instanceof NumberFormat) {
                if (in_array($transformObjects->getField(), $extraFields)) {
                    $parserConfig->addExtraTransformColumn($transformObjects->getField(), $transformObjects);
                } else {
                    $parserConfig->addTransformColumn($transformObjects->getField(), $transformObjects);
                }

                continue;
            }

            /*
             * Sort By or Group By
             */
            if ($transformObjects instanceof GroupByColumns || $transformObjects instanceof SortByColumns) {
                $transformObjects->validate();
                $parserConfig->addTransformCollection($transformObjects);
                continue;
            }

            /**
             * @var CollectionTransformerInterface $transformObject
             * other transform
             */
            foreach ($transformObjects as $transformObject) {
                $transformObject->validate();

                if ($transformObject instanceof AddField) {
                    $internalFieldValue = $this->getMetadataInternalValueForAddField($transformObject->getTransformValue(), $dataSourceEntry);
                    if ($internalFieldValue !== null) {
                        $transformObject->setTransformValue($internalFieldValue);
                    }

                    if (!array_key_exists($transformObject->getColumn(), $allFields)) {
                        continue;
                    }

                    $type = array_key_exists($transformObject->getColumn(), $tempFields) ? FieldType::TEXT : $allFields[$transformObject->getColumn()];
                    $transformObject->setType($type);
                } else if ($transformObject instanceof ReplaceText || $transformObject instanceof ExtractPattern) {
                    $extraFields[] = $transformObject->getTargetField();
                    $this->addInternalVariableToTransform($transformObject->getField(), $transformObject->getTargetField(), $allFields, $parserConfig, $dataSourceEntry);
                }

                $parserConfig->addTransformCollection($transformObject);

                $this->addExtraColumnParserConfig($parserConfig, $transformObject, $connectedDataSource->getDataSet()->getDimensions());
            }
        }
    }

    /**
     * @param $addFieldValue
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed|null
     */
    private function getMetadataInternalValueForAddField($addFieldValue, DataSourceEntryInterface $dataSourceEntry)
    {
        // replace by filename
        $result = str_replace(MetadataInterface::FILE_NAME, $dataSourceEntry->getFileName(), $addFieldValue);

        // replace by metadata
        $metadata = $this->dataSourceEntryMetadataFactory->getMetadata($dataSourceEntry);
        if (!$metadata instanceof MetadataInterface) {
            return $result;
        }

        if ($metadata instanceof EmailMetadata) {
            $result = str_replace(EmailMetadata::EMAIL_SUBJECT, $metadata->getEmailSubject(), $result);
            $result = str_replace(EmailMetadata::EMAIL_BODY, $metadata->getEmailBody(), $result);
            $result = str_replace(EmailMetadata::EMAIL_DATE_TIME, $metadata->getEmailDatetime(), $result);
            $result = str_replace(EmailMetadata::INTEGRATION_REPORT_DATE, $metadata->getIntegrationReportDate(), $result);
        }

        return $result;
    }

    /**
     * @param string $field
     * @param string $targetField
     * @param array $allFields
     * @param ParserConfig $parserConfig
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    private function addInternalVariableToTransform($field, $targetField, array $allFields, ParserConfig $parserConfig, DataSourceEntryInterface $dataSourceEntry)
    {
        $internalField = sprintf("[%s]", $field);
        $metadataFields = array_merge(EmailMetadata::$internalFields);
        if (in_array($internalField, $metadataFields)) {
            $parserConfig->addTransformCollection(new AddField($field, $this->getSingleMetaDataFieldValue($internalField, $dataSourceEntry), FieldType::TEXT));
        }

        if ($targetField !== null && array_key_exists($targetField, $allFields)) {
            $targetFieldType = $allFields[$targetField];
            if ($targetFieldType === FieldType::DATE && !$parserConfig->hasColumnMapping($targetField)) {
                $parserConfig->addColumn($targetField, $targetField);
            }
        }
    }

    /**
     * @param $rows
     * @param array $allFields
     * @return Collection
     */
    public function setDataOfColumnsNotMappedToNull(SplDoublyLinkedList $rows, array $allFields)
    {
        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $temp = [];
            foreach ($allFields as $field => $type) {
                if (!array_key_exists($field, $row)) {
                    $temp[$field] = null;
                } else if ($row[$field] === null) {
                    $temp[$field] = null;
                } else if ($type === FieldType::NUMBER) {
                    $temp[$field] = round($row[$field]);
                } else if ($type === FieldType::DECIMAL) {
                    $temp[$field] = floatval($row[$field]);
                } else {
                    $temp[$field] = $row[$field];
                }
            }

            $newRows->push($temp);
            unset($row);
        }

        unset($rows, $row);
        return new Collection(array_keys($allFields), $newRows);
    }

    /**
     * @param $internalField
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed|null
     */
    private function getSingleMetaDataFieldValue($internalField, DataSourceEntryInterface $dataSourceEntry)
    {
        if ($internalField === MetadataInterface::FILE_NAME) {
            return $dataSourceEntry->getFileName();
        }

        $dataSourceEntryMetadata = $this->dataSourceEntryMetadataFactory->getMetadata($dataSourceEntry);
        if ($dataSourceEntryMetadata === null) {
            return null;
        }

        return $dataSourceEntryMetadata->getMetadataValueByInternalVariable($internalField);
    }

    private function getFormatDateForEachFieldInDataSourceFile()
    {
        $formats = [];//format date
        foreach ($this->parserConfig->getColumnTransforms() as $field => $columnTransform) {
            foreach ($columnTransform as $item) {
                if ($item instanceof DateFormat) {
                    $formats[$field] = $item->getFromDateFormats();
                }
            }
        }

        return $formats;
    }

    private function addExtraColumnParserConfig(ParserConfig $parserConfig, CollectionTransformerInterface $transformObject, array $dimensions)
    {
        $extraColumns = [];
        if ($transformObject instanceof AddField) {
            $extraColumns[] = $transformObject->getColumn();
        }


        if ($transformObject instanceof ExtractPattern || $transformObject instanceof ReplaceText) {
            if (!$transformObject->isIsOverride()) {
                $extraColumns[] = $transformObject->getTargetField();
            }
        }

        if ($transformObject instanceof Augmentation || $transformObject instanceof SubsetGroup) {
            foreach ($transformObject->getMapFields() as $mapField) {
                $extraColumns[] = $mapField[Augmentation::DATA_SOURCE_SIDE];
            }
        }

        foreach ($extraColumns as $extraColumn) {
            if (!array_key_exists($extraColumn, $dimensions) || $parserConfig->hasColumnMapping($extraColumn)) {
                continue;
            }

            $parserConfig->addColumn($extraColumn, $extraColumn);
        }
    }

    private function addPrefixForColumnsFromFile(array &$columns)
    {
        foreach ($columns as &$column) {
            $column = ConnectedDataSourceInterface::PREFIX_FILE_FIELD . $column;
        }
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @throws ImportDataException
     */
    private function validateMissingRequiresColumns($connectedDataSource)
    {
        $mapFields = $connectedDataSource->getMapFields();
        $requireFields = $connectedDataSource->getRequires();

        if (!is_array($mapFields)) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_WRONG_TYPE_MAPPING, null, null);
        }

        if (!is_array($requireFields)) {
            $requireFields = [];
        }

        foreach ($requireFields as $require) {
            if (!array_key_exists($require, $this->parserConfig->getAllColumnMappings())) {
                throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_REQUIRED_FAIL, null, $require);
            }
        }
    }

    /**
     * @param mixed $rows
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return SplDoublyLinkedList
     */
    private function removeInvalidRowsDependOnRequiredFields(SplDoublyLinkedList $rows, $connectedDataSource)
    {
        $requireFields = $connectedDataSource->getRequires();
        if (!is_array($requireFields)) {
            return $rows;
        }

        $requireFields = array_values($requireFields);

        if (empty($requireFields)) {
            return $rows;
        }

        $fromDateFormats = $connectedDataSource->getTransforms();

        $mapFields = array_flip($connectedDataSource->getMapFields());
        $dataSet = $connectedDataSource->getDataSet();
        $types = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
        $requireFields = array_filter($requireFields, function ($requireField) use ($mapFields) {
           return  array_key_exists($requireField, $mapFields);
        });

        if (count($requireFields) < 1) {
            return $rows;
        }

        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            foreach ($requireFields as $requireField) {
                $type = $types[$requireField];
                $fieldInFile = $mapFields[$requireField];
                if (!array_key_exists($fieldInFile, $row)) {
                    break;
                    continue;
                }

                $value = FieldType::convertValue($row[$fieldInFile], $type, $fromDateFormats, $requireField);
                if (in_array($type, [FieldType::TEXT, FieldType::LARGE_TEXT, FieldType::DATE, FieldType::DATETIME]) && empty($value)) {
                    break;
                    continue;
                }

                if (in_array($type, [FieldType::NUMBER, FieldType::DECIMAL]) && $value == null) {
                    break;
                    continue;
                }
            }

            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);
        return $newRows;
    }

    /**
     * @param $rows
     * @param ConnectedDataSourceInterface|null $connectedDataSource
     * @return SplDoublyLinkedList
     */
    private function removeNullDateTimeRows(SplDoublyLinkedList $rows, $connectedDataSource = null)
    {
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return $rows;
        }

        $dataSet = $connectedDataSource->getDataSet();
        /*
         * $types:
         * [
         *      <field_in_data_set>:<type>
         * ]
         */
        $types = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());
        $dateTimeTypes = array_filter($types, function ($type) {
            return $type == FieldType::DATE || $type == FieldType::DATETIME;
        });

        if (empty($dateTimeTypes)) {
            return $rows;
        }

        /** Get field in files as $$FILE$$date, $$FILE$$revenue...*/
        /*
         * $mapFields:
         * [
         *      _$$FILE$$<field_in_file>:<field_in_data_set>
         * ]
         */
        $mapFields = $connectedDataSource->getMapFields();
        $fieldInFiles = [];
        foreach ($dateTimeTypes as $field => $type) {
            if (in_array($field, $mapFields)) {
                foreach ($mapFields as $fieldInFile => $fieldInDataSet) {
                    if ($field == $fieldInDataSet) {
                        $fieldInFiles[$fieldInFile] = $type;
                    }
                }
            }
        }

        /** Remove row if have invalid date, datetime value*/
        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {

            $invalidRow = false;
            foreach ($fieldInFiles as $fieldInFile => $type) {
                if (!array_key_exists($fieldInFile, $row)) {
                    $invalidRow = true;
                    break;
                }

                $value = $row[$fieldInFile];
                if (empty($value)) {
                    $invalidRow = true;
                    break;
                }
            }

            if ($invalidRow) {
                continue;
            }

            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);
        return $newRows;
    }

    public function resetInjectParams()
    {
        $this->parserConfig = new ParserConfig();
        $this->transformerFactory = new TransformerFactory();
        $this->dataSourceEntryMetadataFactory = new DataSourceEntryMetadataFactory();
    }
}