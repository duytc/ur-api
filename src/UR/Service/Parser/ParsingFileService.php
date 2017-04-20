<?php

namespace UR\Service\Parser;


use Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DataSource\DataSourceInterface;
use UR\Service\DataSource\Excel;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;
use UR\Service\Metadata\DataSourceEntryMetadataFactory;
use UR\Service\Metadata\Email\EmailMetadata;
use UR\Service\Metadata\MetadataInterface;
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
    protected $parser;

    /**
     * @var ParserConfig $parserConfig
     */
    private $parserConfig;

    /**
     * @var TransformerFactory $transformerFactory
     */
    private $transformerFactory;

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
        $this->transformerFactory = new TransformerFactory();
        $this->parserConfig = new ParserConfig();
        $this->dataSourceEntryMetadataFactory = new DataSourceEntryMetadataFactory();
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

        $dataRow = $dataSourceFileData->getDataRow();

        if (!is_array($columnsInFile) || count($columnsInFile) < 1) {
            throw new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_NO_HEADER_FOUND);
        }

        if ($dataRow < 1) {
            throw new ImportDataException(ImportFailureAlert::ALERT_CODE_DATA_IMPORT_NO_DATA_ROW_FOUND);
        }

        if ($dataSourceFileData instanceof Excel) {
            $formats = $this->getFormatDateForEachFieldInDataSourceFile();
            $dataSourceFileData->setFromDateFormats($formats);
        }

        /* 1. get all row data */
        if (is_numeric($limit)){
            $rows = $dataSourceFileData->getLimitedRows($limit);
        } else {
            $rows = $dataSourceFileData->getRows();
        }
        $rows = array_values($rows);

//        /* adding hidden column __report_date for files received via integration */
//        if ($dataSourceEntry->getReceivedVia() === DataSourceEntryInterface::RECEIVED_VIA_INTEGRATION) {
//            $this->addExtraColumnsAndRowsDataForIntegrationEntry($columnsInFile, $rows, $dataSourceEntryMetadata);
//        }

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

                if (array_key_exists($filter[ColumnFilterInterface::FIELD_NAME_FILTER_KEY], $mapFields)) {
                    $format = $this->getFormatDateFromTransform($connectedDataSource, $mapFields[$filter[ColumnFilterInterface::FIELD_NAME_FILTER_KEY]]);
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
                    $internalFieldValue = $this->getMetadataInternalValueForAddField($transformObject->getTransformValue(), $dataSourceEntry);
                    if ($internalFieldValue !== null) {
                        $transformObject->setTransformValue($internalFieldValue);
                    }

                    if (!array_key_exists($transformObject->getColumn(), $allFields)) {
                        continue;
                    }

                    $transformObject->setType($allFields[$transformObject->getColumn()]);
                } else if ($transformObject instanceof ReplaceText || $transformObject instanceof ExtractPattern) {
                    $this->addInternalVariableToTransform($transformObject->getField(), $transformObject->getTargetField(), $allFields, $parserConfig, $dataSourceEntry);
                }

                $parserConfig->addTransformCollection($transformObject);
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
            if (strcmp($targetFieldType, FieldType::DATE) === 0 && !$parserConfig->hasColumnMapping($targetField)) {
                $parserConfig->addColumn($targetField, $targetField);
            }
        }
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
                    $temp[$field] = round($row[$field]);
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
                        $row[$column] = $transform->transformFromDatabaseToClient($row[$column]);
                    } else {
                        $row[$column] = $transform->transform($row[$column]);
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
        if (strcmp($internalField, MetadataInterface::FILE_NAME) === 0) {
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
                    $formats[$field] = $item->getFromDateFormat();
                }
            }
        }

        return $formats;
    }
}