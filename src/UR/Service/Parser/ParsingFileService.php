<?php

namespace UR\Service\Parser;


use Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;
use UR\Service\DataSet\MetadataField;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Filter\DateFilter;
use UR\Service\Parser\Filter\FilterFactory;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\ReplaceText;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;
use UR\Service\Parser\Transformer\TransformerFactory;

class ParsingFileService
{
    /**
     * @var string
     */
    private $uploadFileDir;

    protected $parser;

    /**
     * @var ParserConfig $parserConfig
     */
    private $parserConfig;

    /**
     * @var TransformerFactory $transformerFactory
     */
    private $transformerFactory;

    private $fileFactory;

    /**
     * ParsingFileService constructor.
     * @param string $uploadFileDir
     * @param Parser $parser
     * @param DataSourceFileFactory $fileFactory
     */
    public function __construct($uploadFileDir, Parser $parser, DataSourceFileFactory $fileFactory)
    {
        $this->uploadFileDir = $uploadFileDir;
        $this->parser = $parser;
        $this->fileFactory = $fileFactory;
        $this->transformerFactory = new TransformerFactory();
    }

    public function doParser(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource)
    {
        $this->parserConfig = new ParserConfig();
        $filePath = $this->uploadFileDir . $dataSourceEntry->getPath();
        if (!file_exists($filePath)) {
            throw  new ImportDataException(ImportFailureAlert::ALERT_CODE_FILE_NOT_FOUND, null, null, null, null);
        }

        $file = $this->fileFactory->getFile($connectedDataSource->getDataSource()->getFormat(), $filePath);

        $columns = $file->getColumns();
        $dataRow = $file->getDataRow();
        if (!is_array($columns) || count($columns) < 1) {
            throw  new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND, null, null, null, null);
        }

        if ($dataRow < 1) {
            throw  new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND, null, null, null, null);
        }

        /*
         * mapping field
         */
        $this->createMapFieldsConfigForConnectedDataSource($connectedDataSource, $this->parserConfig, $columns);
        if (count($this->parserConfig->getAllColumnMappings()) === 0) {
            throw  new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL, null, null, null, null);
        }

        $validRequires = true;
        $columnRequire = '';
        foreach ($connectedDataSource->getRequires() as $require) {
            if (!array_key_exists($require, $this->parserConfig->getAllColumnMappings())) {
                $columnRequire = $require;
                $validRequires = false;
                break;
            }
        }

        if (!$validRequires) {
            throw  new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL, null, $columnRequire, null, null);
        }

        //filter config
        $this->createFilterConfigForConnectedDataSource($connectedDataSource, $this->parserConfig);

        //transform config
        $this->createTransformConfigForConnectedDataSource($connectedDataSource, $this->parserConfig, $dataSourceEntry);

        return $this->parser->parse($file, $this->parserConfig, $connectedDataSource);
    }

    public function addTransformColumnAfterParsing($transforms)
    {
        /**
         * @var array $transforms
         */
        foreach ($transforms as $transform) {
            $transformObject = $this->transformerFactory->getTransform($transform);
            if ($transformObject instanceof NumberFormat)
                $this->parserConfig->addTransformColumn($transformObject->getField(), $transformObject);
        }
    }

    public function createMapFieldsConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig, array $columns)
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

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ParserConfig $parserConfig
     * @throws Exception
     */
    private function createFilterConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig)
    {
        $filters = $connectedDataSource->getFilters();
        if (!is_array($filters) && $filters !== null) {
            throw new Exception(sprintf("ConnectedDataSource Filters Setting should be an array"));
        }

        $filterFactory = new FilterFactory();
        foreach ($filters as $filter) {
            // filter Date
            if (strcmp($filter[ColumnFilterInterface::FIELD_TYPE_FILTER_KEY], FieldType::DATE) === 0) {
                $mapFields = $connectedDataSource->getMapFields();
                if (array_key_exists($filter[ColumnFilterInterface::FILED_NAME_FILTER_KEY], $mapFields)) {
                    $format = $this->getFormatDateFromTransform($connectedDataSource, $mapFields[$filter[ColumnFilterInterface::FILED_NAME_FILTER_KEY]]);
                    $filter[DateFilter::FORMAT_FILTER_KEY] = $format;
                }
            }

            $filterObject = $filterFactory->getFilter($filter);
            if ($filterObject === null) {
                continue;
            }

            $filterObject->validate();
            $parserConfig->addFiltersColumn($filterObject->getField(), $filterObject);
        }
    }

    private function getFormatDateFromTransform(ConnectedDataSourceInterface $connectedDataSource, $field)
    {
        $transforms = $connectedDataSource->getTransforms();
        foreach ($transforms as $transform) {
            if (strcmp($transform[DateFormat::FIELD_KEY], $field) === 0) {
                return $transform[DateFormat::FROM_KEY];
            }
        }

        return null;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ParserConfig $parserConfig
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    private function createTransformConfigForConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig, DataSourceEntryInterface $dataSourceEntry)
    {
        $dimensions = $connectedDataSource->getDataSet()->getDimensions();
        $metrics = $connectedDataSource->getDataSet()->getMetrics();
        $allFields = array_merge($dimensions, $metrics);
        foreach ($connectedDataSource->getTransforms() as $transform) {
            $transformObjects = $this->transformerFactory->getTransform($transform);

            if ($transformObjects instanceof DateFormat) {
                if (!array_key_exists($transformObjects->getField(), $parserConfig->getAllColumnMappings())) {
                    continue;
                }

                $transformObjects->setDateFormat('Y-m-d');
                $parserConfig->addTransformColumn($transformObjects->getField(), $transformObjects);
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
                    $internalFieldValue = $this->getMetadataInternalValue($transformObject->getTransformValue(), $dataSourceEntry);
                    if ($internalFieldValue !== null) {
                        $transformObject->setTransformValue($internalFieldValue);
                    }

                    if (!array_key_exists($transformObject->getColumn(), $allFields)) {
                        continue;
                    }

                    $transformObject->setType($allFields[$transformObject->getColumn()]);
                } else if ($transformObject instanceof ReplaceText || $transformObject instanceof ExtractPattern) {
                    $this->addInternalVariable($transformObject->getField(), $transformObject->getTargetField(), $allFields, $parserConfig, $dataSourceEntry);
                }

                $parserConfig->addTransformCollection($transformObject);
            }
        }
    }

    /**
     * @param $internalField
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed|null
     */
    private function getMetadataInternalValue($internalField, DataSourceEntryInterface $dataSourceEntry)
    {
        $metadata = $dataSourceEntry->getDataSourceEntryMetadata();
        $result = null;

        $result = str_replace(MetadataField::FILE_NAME, $dataSourceEntry->getFileName(), $internalField);
        if ($metadata === null)
            return $result;
        $result = str_replace(MetadataField::EMAIL_SUBJECT, $metadata->getEmailSubject(), $result);
        $result = str_replace(MetadataField::EMAIL_BODY, $metadata->getEmailBody(), $result);
        $result = str_replace(MetadataField::EMAIL_DATE_TIME, $metadata->getEmailDatetime(), $result);

        return $result;
    }

    /**
     * @param string $field
     * @param string $targetField
     * @param array $allFields
     * @param ParserConfig $parserConfig
     * @param DataSourceEntryInterface $dataSourceEntry
     */
    private function addInternalVariable($field, $targetField, $allFields, ParserConfig $parserConfig, DataSourceEntryInterface $dataSourceEntry)
    {
        $internalField = sprintf("[%s]", $field);
        if (in_array($internalField, MetadataField::$internalFields)) {
            $parserConfig->addTransformCollection(new AddField($field, $this->getSingleMetaDataFieldValue($internalField, $dataSourceEntry), FieldType::TEXT));
        }

        if ($targetField !== null || array_key_exists($targetField, $allFields)) {
            $targetFieldType = $allFields[$targetField];
            if (strcmp($targetFieldType, FieldType::DATE) === 0 && !$parserConfig->hasColumnMapping($targetField)) {
                $parserConfig->addColumn($targetField, $targetField);
            }
        }
    }

    public function overrideDuplicate($rows, $dimensions)
    {
        $duplicateRows = [];
        foreach ($rows as &$row) {
            $uniqueKeys = array_intersect_key($row, $dimensions);
            $uniqueId = md5(implode(":", $uniqueKeys));

            $duplicateRows[$uniqueId] = $row;
        }

        $rows = array_values($duplicateRows);
        return $rows;
    }

    public function getNoDataRows($allFields)
    {
        $allFields = array_map(function ($column) {
            return null;
        }, $allFields);

        $returnHeader[] = $allFields;

        return $returnHeader;
    }

    public function setDataOfColumnsNotMappedToNull($rows, $allFields)
    {
        foreach ($rows as &$row) {
            $temp = [];
            foreach ($allFields as $field => $type) {
                if (!array_key_exists($field, $row)) {
                    $temp[$field] = null;
                } else if ($row[$field] === null) {
                    $temp[$field] = null;
                } else {
                    $temp[$field] = strcmp($type, FieldType::NUMBER) === 0 ? round($row[$field]) : $row[$field];
                }
            }

            $row = $temp;
        }

        return new Collection(array_keys($allFields), $rows);
    }

    public function formatColumnsTransformsAfterParser($rows)
    {
        foreach ($rows as &$row) {
            foreach ($this->parserConfig->getColumnTransforms() as $column => $transforms) {
                /** @var ColumnTransformerInterface[] $transforms */
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                foreach ($transforms as $transform) {
                    if ($transform instanceof DateFormat) {
                        // very important: after parse, all date already in format Y-m-d
                        // so that from date format need be updated, and isCustomFormatDateFrom is must be set to false
                        // TODO: check if we do not convert directly data to Y-m-d before doing transform...
                        // TODO: the private var dateFormat should be removed
                        $transform->setFromDateFormat('Y-m-d');
                        $transform->setIsCustomFormatDateFrom(false);
                        $transform->setDateFormat($transform->getToDateFormat());
                    }

                    $row[$column] = $transform->transform($row[$column]);
                    if ($row[$column] === 2) {
                        throw new ImportDataException(ImportFailureAlert::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE, 0, $column, null, null);
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @param $internalField
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return mixed|null
     */
    private function getSingleMetaDataFieldValue($internalField, DataSourceEntryInterface $dataSourceEntry)
    {
        $metadata = $dataSourceEntry->getDataSourceEntryMetadata();
        $result = null;

        if (strcmp($internalField, MetadataField::FILE_NAME) === 0) {
            return $dataSourceEntry->getFileName();
        }

        if ($metadata === null) {
            return null;
        }

        switch ($internalField) {
            case MetadataField::EMAIL_SUBJECT:
                $result = $metadata->getEmailSubject();
                break;
            case MetadataField::EMAIL_BODY:
                $result = $metadata->getEmailBody();
                break;
            case MetadataField::EMAIL_DATE_TIME:
                $result = $metadata->getEmailDatetime();
                break;
            default:
                return null;
        }

        return $result;
    }
}