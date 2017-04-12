<?php

namespace UR\Service\Parser;


use Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceEntryMetadata;
use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;
use UR\Service\DataSet\MetadataField;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DataSource\DataSourceInterface;
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

    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return Collection
     * @throws ImportDataException
     */
    public function doParser(DataSourceEntryInterface $dataSourceEntry, ConnectedDataSourceInterface $connectedDataSource)
    {
        $this->parserConfig = new ParserConfig();
        $filePath = $this->uploadFileDir . $dataSourceEntry->getPath();
        if (!file_exists($filePath)) {
            throw new ImportDataException(ImportFailureAlert::ALERT_CODE_FILE_NOT_FOUND);
        }

        /** @var DataSourceInterface $dataSourceFileData */
        $dataSourceFileData = $this->fileFactory->getFile($connectedDataSource->getDataSource()->getFormat(), $filePath);

        $columnsInFile = $dataSourceFileData->getColumns();
        $dataRow = $dataSourceFileData->getDataRow();
        if (!is_array($columnsInFile) || count($columnsInFile) < 1) {
            throw new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND);
        }

        if ($dataRow < 1) {
            throw new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND);
        }

        $dataSourceEntryMetadata = $dataSourceEntry->getDataSourceEntryMetadata();

        $formats = $this->getFormatDateForEachFieldInDataSourceFile();

        /* 1. get all row data */
        $rows = array_values($dataSourceFileData->getRows($formats));

        /* adding hidden column __report_date for files received via integration */
        if ($dataSourceEntry->getReceivedVia() === DataSourceEntryInterface::RECEIVED_VIA_INTEGRATION) {
            $this->addExtraColumnsAndRowsDataForIntegrationEntry($columnsInFile, $rows, $dataSourceEntryMetadata);
        }

        /* mapping field */
        $this->createMapFieldsConfigForConnectedDataSource($connectedDataSource, $this->parserConfig, $columnsInFile);
        if (count($this->parserConfig->getAllColumnMappings()) === 0) {
            throw new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_MAPPING_FAIL);
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
            throw new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_REQUIRED_FAIL, null, $columnRequire);
        }

        //filter config
        $this->createFilterConfigForConnectedDataSource($connectedDataSource, $this->parserConfig);

        //transform config
        $this->createTransformConfigForConnectedDataSource($connectedDataSource, $this->parserConfig, $dataSourceEntry);


        return $this->parser->parse($columnsInFile, $rows, $this->parserConfig, $connectedDataSource);
    }

    /**
     * @param array $transforms
     */
    public function addTransformColumnAfterParsing(array $transforms)
    {
        foreach ($transforms as $transform) {
            $transformObject = $this->transformerFactory->getTransform($transform);
            if ($transformObject instanceof NumberFormat) {
                $this->parserConfig->addTransformColumn($transformObject->getField(), $transformObject);
            }
        }
    }

    /**
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
            $fileColumn = strtolower(trim($fileColumn));

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

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param $field
     * @return null
     */
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
        $allFields = $connectedDataSource->getDataSet()->getAllDimensionMetrics();
        foreach ($connectedDataSource->getTransforms() as $transform) {
            if (!is_array($transform)) {
                continue;
            }

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
        // replace by filename
        $result = str_replace(MetadataField::FILE_NAME, $dataSourceEntry->getFileName(), $internalField);

        // replace by metadata
        $metadata = $dataSourceEntry->getDataSourceEntryMetadata();
        if (!$metadata instanceof DataSourceEntryMetadata) {
            return $result;
        }

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
    private function addInternalVariable($field, $targetField, array $allFields, ParserConfig $parserConfig, DataSourceEntryInterface $dataSourceEntry)
    {
        $internalField = sprintf("[%s]", $field);
        if (in_array($internalField, MetadataField::$internalFields)) {
            $parserConfig->addTransformCollection(new AddField($field, $this->getSingleMetaDataFieldValue($internalField, $dataSourceEntry), FieldType::TEXT));
        }

        if ($targetField !== null && array_key_exists($targetField, $allFields)) {
            $targetFieldType = $allFields[$targetField];
            if (strcmp($targetFieldType, FieldType::DATE) === 0 && !$parserConfig->hasColumnMapping($targetField)) {
                $parserConfig->addColumn($targetField, $targetField);
            }
        }
    }

    /**
     * @param array $rows
     * @param array $dimensions
     * @return array
     */
    public function overrideDuplicate(array $rows, array $dimensions)
    {
        $duplicateRows = [];
        foreach ($rows as $index => &$row) {
            if (!is_array($row)) {
                continue;
            }

            $uniqueKeys = array_intersect_key($row, $dimensions);
            $uniqueId = md5(implode(":", $uniqueKeys));

            $duplicateRows[$uniqueId] = $row;
        }

        $rows = array_values($duplicateRows);

        return $rows;
    }

    /**
     * @param $allFields
     * @return array
     */
    public function getNoDataRows($allFields)
    {
        $allFields = array_map(function ($column) {
            return null;
        }, $allFields);

        return [$allFields];
    }

    /**
     * @param $rows
     * @param array $allFields
     * @return Collection
     */
    public function setDataOfColumnsNotMappedToNull($rows, array $allFields)
    {
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }

            $temp = [];
            foreach ($allFields as $field => $type) {
                if (!array_key_exists($field, $row)) {
                    $temp[$field] = null;
                } else if ($row[$field] === null) {
                    $temp[$field] = null;
                } else if (strcmp($type, FieldType::NUMBER) === 0) {
                    $temp[$field] = intval($row[$field]);
                } else if (strcmp($type, FieldType::DECIMAL) === 0) {
                    $temp[$field] = floatval($row[$field]);
                } else {
                    $temp[$field] = $row[$field];
                }
            }

            $row = $temp;
        }

        return new Collection(array_keys($allFields), $rows);
    }

    /**
     * @param array $rows
     * @return array
     * @throws ImportDataException
     */
    public function formatColumnsTransformsAfterParser(array $rows)
    {
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }

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

    private function getFormatDateForEachFieldInDataSourceFile()
    {
        $formats = [];//format date
        foreach ($this->parserConfig->getColumnTransforms() as $field => $columnTransform) {
            foreach ($columnTransform as $item) {
                if ($item instanceof DateFormat) {
                    $formats[$field] = $item->getFromDateFormat();
                }
            }
        }

        return $formats;
    }

    private function addExtraColumnsAndRowsDataForIntegrationEntry(&$columnsInFile, &$rows, DataSourceEntryMetadata $dataSourceEntryMetadata)
    {
        $columnsInFile[] = MetadataField::INTEGRATION_REPORT_DATE;

        foreach ($rows as &$row) {
            $row[] = $dataSourceEntryMetadata->getIntegrationReportDate();
        }
    }
}