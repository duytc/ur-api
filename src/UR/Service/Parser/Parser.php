<?php

namespace UR\Service\Parser;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PostLoadDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PostParseDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreFilterDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformCollectionDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformColumnDataEvent;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Transformer\Collection\Augmentation;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;

class Parser implements ParserInterface
{
    /**@var EventDispatcherInterface $eventDispatcher
     */
    protected $eventDispatcher;

    /** @var EntityManagerInterface */
    protected $em;

    /**
     * Parser constructor.
     * @param EventDispatcherInterface $eventDispatcher
     * @param EntityManagerInterface $em
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, EntityManagerInterface $em)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->em = $em;
    }

    /**
     * @param array $fileCols
     * @param array $rows
     * @param ParserConfig $parserConfig
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return Collection
     */
    public function parse(array $fileCols, array $rows, ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource)
    {
        $columnsMapping = $parserConfig->getAllColumnMappings();
        $columnFromMap = array_flip($parserConfig->getAllColumnMappings());

        $fileCols = array_map("strtolower", $fileCols);
        $fileCols = array_map("trim", $fileCols);

        $columns = array_intersect($fileCols, $parserConfig->getAllColumnMappings());

        // dispatch event after loading data
        $postLoadDataEvent = new PostLoadDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $rows
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_POST_LOADED_DATA,
            $postLoadDataEvent
        );

        $rows = $postLoadDataEvent->getRows();

        $dataSetColumns = array_merge($connectedDataSource->getDataSet()->getDimensions(), $connectedDataSource->getDataSet()->getMetrics());
        $types = [];
        foreach ($dataSetColumns as $dsColumn => $type) {
            if (!array_key_exists($dsColumn, $columnsMapping)) {
                $types[$dsColumn] = $type;
                continue;
            }

            $types[$columnsMapping[$dsColumn]] = $type;
        }

        /* 2. do filtering data */
        // dispatch event pre filtering data
        $preFilterEvent = new PreFilterDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $rows
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_FILTER_DATA,
            $preFilterEvent
        );

        $rows = $preFilterEvent->getRows();

        $cur_row = -1;
        foreach ($rows as &$row) {
            $cur_row++;
            if (!is_array($row)) {
                unset($rows[$cur_row]);
                continue;
            }

            $isMapped = count(array_diff_key(array_flip($fileCols), $row));
            if ($isMapped > 0 && count($fileCols) === count($row)) {
                $row = array_combine($fileCols, $row);
            }

            foreach ($dataSetColumns as $dsColumn => $type) {
                if (!array_key_exists($dsColumn, $columnsMapping)) {
                    continue;
                }

                $fileColumn = $columnsMapping[$dsColumn];

                if (!array_key_exists($fileColumn, $row)) {
                    continue;
                }

                $row[$fileColumn] = $this->reformatFileData($row, $fileColumn, $type);
            }

            $isValidFilter = $this->doFilter($parserConfig, $fileCols, $row);

            if (!$isValidFilter) {
                unset($rows[$cur_row]);
                continue;
            }
        }

        //overwrite duplicate
        if ($connectedDataSource->getDataSet()->getAllowOverwriteExistingData()) {
            $dateFormats = [];
            $columnTransforms = $parserConfig->getColumnTransforms();
            $mappedFields = $parserConfig->getAllColumnMappings();
            foreach($columnTransforms as $transforms) {
                foreach($transforms as $transform) {
                    if (!$transform instanceof DateFormat) {
                        continue;
                    }

                    $dateFormats[$mappedFields[$transform->getField()]] = $transform->getFromDateFormat();
                }
            }
            $mappedDimensions = array_intersect_key($columnsMapping, $connectedDataSource->getDataSet()->getDimensions());
            $rows = $this->overrideDuplicate($rows, array_flip($mappedDimensions), $dateFormats);
        }

        // TODO: may dispatch event after filtering data
        $collection = new Collection($columns, $rows, $types);

        if (count($rows) < 1) {
            return $collection;
        }

        /* 3. do transforming data */
        $allFieldsTransforms = $parserConfig->getCollectionTransforms();

        // dispatch event pre transforming collection data
        $preTransformCollectionEvent = new PreTransformCollectionDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $collection
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_TRANSFORM_COLLECTION_DATA,
            $preTransformCollectionEvent
        );

        $collection = $preTransformCollectionEvent->getCollection();
        $collection = $this->addTemporaryFields($collection, $connectedDataSource);

        // transform collection
        foreach ($allFieldsTransforms as $transform) {
            /** @var CollectionTransformerInterface $transform */
            if ($transform instanceof Augmentation || $transform instanceof SubsetGroup) {
                $collection = $transform->transform($collection, $this->em, $connectedDataSource);
            } else {
                $collection = $transform->transform($collection);
            }
        }

        $collection = $this->removeTemporaryFields($collection, $connectedDataSource);

        // dispatch event pre transforming column data
        $preTransformColumnEvent = new PreTransformColumnDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $collection
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_TRANSFORM_COLUMN_DATA,
            $preTransformColumnEvent
        );

        $collection = $preTransformColumnEvent->getCollection();

        // transform column
        $rows = array_values($collection->getRows());

        if (count($rows) < 1) {
            return $collection;
        }

        $columns = $this->getColumnsAfterDoCollectionTransforms($rows[0], $columnFromMap, $collection->getColumns());

        $types = $collection->getTypes();

        $collection = $this->getFinalParserCollection($rows, $columns, $parserConfig);

        // todo refactor, this is messy to have to set types like this

        $collection->setTypes($types);

        // dispatch event post parse data
        $postParseDataEvent = new PostParseDataEvent(
            $connectedDataSource->getDataSet()->getPublisherId(),
            $connectedDataSource->getId(),
            $connectedDataSource->getDataSource()->getId(),
            $collection
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_POST_PARSE_DATA,
            $postParseDataEvent
        );

        $collection = $postParseDataEvent->getCollection();

        return $collection;
    }

    /**
     * @param Collection $collection
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return Collection
     */
    private function addTemporaryFields(Collection $collection, ConnectedDataSourceInterface $connectedDataSource)
    {
        $columns = $collection->getColumns();
        $types = $collection->getTypes();
        $rows = $collection->getRows();
        $temporaryFields = $connectedDataSource->getTemporaryFields();

        if (empty($temporaryFields)) {
            return $collection;
        }

        foreach ($temporaryFields as $temp) {
            $columns[] = $temp;
            $types[$temp] = FieldType::TEMPORARY;

            foreach ($rows as &$row) {
                $row[$temp] = '';
            }
        }

        $collection->setColumns($columns);
        $collection->setTypes($types);
        $collection->setRows($rows);

        return $collection;
    }

    /**
     * @param Collection $collection
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return Collection
     */
    private function removeTemporaryFields(Collection $collection, ConnectedDataSourceInterface $connectedDataSource)
    {
        $columns = $collection->getColumns();
        $types = $collection->getTypes();
        $rows = $collection->getRows();
        $temporaryFields = $connectedDataSource->getTemporaryFields();

        if (empty($temporaryFields)) {
            return $collection;
        }

        foreach ($temporaryFields as $temp) {
            if (($key = array_search($temp, $columns)) !== false) {
                unset($columns[$key]);
            }

            if (array_key_exists($temp, $types)) {
                unset($types[$temp]);
            }

            foreach ($rows as &$row) {
                if (array_key_exists($temp, $row)) {
                    unset($row[$temp]);
                }
            }
        }

        $collection->setColumns($columns);
        $collection->setRows($rows);
        $collection->setTypes($types);

        return $collection;
    }

    /**
     * @param array $row
     * @param $fileColumn
     * @param string $type
     * @return mixed|null
     * @internal param int $cur_row
     */
    private function reformatFileData(array $row, $fileColumn, $type)
    {
        $cellValue = $row[$fileColumn];

        switch ($type) {
            case FieldType::DECIMAL:
            case FieldType::NUMBER:
                $cellValue = preg_replace('/[^\d.-]+/', '', $cellValue);

                // advance process on dash character
                // if dash is at first position => negative flag
                // else => remove dash
                $firstNegativePosition = strpos($cellValue, '-');
                if ($firstNegativePosition === 0) {
                    $afterFirstNegative = substr($cellValue, 1);
                    $afterFirstNegative = preg_replace('/\-{1,}/', '', $afterFirstNegative);
                    $cellValue = '-' . $afterFirstNegative;
                } else if ($firstNegativePosition > 0) {
                    $cellValue = preg_replace('/\-{1,}/', '', $cellValue);
                }

                // advance process on dot character
                // if dash is at first position => append 0
                // else => remove dot
                $firstDotPosition = strpos($cellValue, '.');
                if ($firstDotPosition !== false) {
                    $first = substr($cellValue, 0, $firstDotPosition);
                    if (!is_numeric($first)) {
                        $first = '0';
                    }

                    $second = substr($cellValue, $firstDotPosition + 1);
                    $second = preg_replace('/\.{1,}/', '', $second);
                    $cellValue = $first . '.' . $second;
                }

                if (!is_numeric($cellValue)) {
                    $cellValue = null;
                }

                break;

            case FieldType::DATE:
            case FieldType::DATETIME:
                // the cellValue may be a DateTime instance if file type is excel. The object is return by excel reader library
                if ($cellValue instanceof \DateTime) {
                    break;
                }

                // make sure date value contain number,
                // else the value is invalid, then we return 'null' for the date transformer removes entire row due to date null
                // e.g:
                // "1/21/17" is valid, "Jan 21 17" is valid,
                // "Jan abc21 17" is valid (but when date transformer creates date, it will be invalid),
                // "total" is invalid date, so we return null, then date transformer remove entire row contains this date
                if (!preg_match('/[\d]+/', $cellValue)) {
                    $cellValue = null;
                }

                break;

            case FieldType::TEXT:
            case FieldType::LARGE_TEXT:
                if ($cellValue === '') {
                    return null; // treat empty string as null value
                }

                break;

            default:
                break;
        }

        return $cellValue;
    }

    /**
     * @param ParserConfig $parserConfig
     * @param array $fileCols
     * @param array $row
     * @return int
     * @throws ImportDataException
     */
    private function doFilter(ParserConfig $parserConfig, array $fileCols, array $row)
    {
        $isValidFilter = 1;
        foreach ($parserConfig->getColumnFilters() as $column => $filters) {
            /**@var ColumnFilterInterface[] $filters */
            if (!in_array($column, $fileCols)) {
                continue;
            }

            foreach ($filters as $filter) {
                $filterResult = $filter->filter($row[$column]);
                $isValidFilter = $isValidFilter & $filterResult;
            }
        }

        return $isValidFilter;
    }

    /**
     * @param array $row
     * @param array $columnFromMap
     * @param array $extraColumns
     * @return array
     */
    private function getColumnsAfterDoCollectionTransforms(array $row, array $columnFromMap, array $extraColumns)
    {
        $columns = [];
        foreach ($row as $field => $value) {
            if (array_key_exists($field, $columnFromMap)) {
                $columns[$field] = $columnFromMap[$field];
                continue;
            }

            if (in_array($field, $extraColumns)) {
                $columns[$field] = $field;
            }
        }

        return $columns;
    }

    /**
     * @param array $rows
     * @param array $columns
     * @param ParserConfig $parserConfig
     * @return Collection
     * @throws ImportDataException
     */
    private function getFinalParserCollection(array $rows, array $columns, ParserConfig $parserConfig)
    {
        foreach ($rows as $cur_row => &$row) {
            if (!is_array($row)) {
                unset($rows[$cur_row]);
                continue;
            }

            $row = array_intersect_key($row, $columns);
            if (empty($row)) {
                // after array)intersect_key, the $row may be an empty array
                // so that we must remove to avoid an empty row data
                unset($rows[$cur_row]);
                continue;
            }

            $keys = array_map(function ($key) use ($columns) {
                return $columns[$key];
            }, array_keys($row));

            $row = array_combine($keys, $row);

            $isNeedRemoveRow = false; // true if current row is need remove from rows, this depends on the transform result
            foreach ($parserConfig->getColumnTransforms() as $column => $transforms) {
                /** @var ColumnTransformerInterface[] $transforms */
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                foreach ($transforms as $transform) {
                    // special for type DATE and DATETIME: if value is null, we unset row data
                    // and do not do transform on null value
                    if (null === $row[$column] && $transform instanceof DateFormat) {
                        $isNeedRemoveRow = true;
                        break; // break loop transforms of one columns
                    }

                    // do transform, throw exception on invalid value
                    $row[$column] = $transform->transform($row[$column]);
                }

                if ($isNeedRemoveRow) {
                    break; // break loop columns
                }
            }

            if ($isNeedRemoveRow) {
                unset($rows[$cur_row]);
            }
        }

        return new Collection($columns, $rows);
    }


    /**
     * @param array $rows
     * @param array $dimensions
     * @param array $dateFormats
     * @return array
     */
    private function overrideDuplicate(array $rows, array $dimensions, array $dateFormats = [])
    {
        $duplicateRows = [];
        foreach ($rows as $index => &$row) {
            if (!is_array($row)) {
                continue;
            }

            $uniqueKeys = array_intersect_key($row, $dimensions);
            foreach($uniqueKeys as $key => &$value) {
                if ($value instanceof \DateTime && array_key_exists($key, $dateFormats)) {
                    // todo Date/Time support, how do we know which format to use?
                    // remove this todo if worked
                    $value = $value->format($dateFormats[$key]);
                }
            }
            $uniqueId = md5(implode(":", $uniqueKeys));

            $duplicateRows[$uniqueId] = $row;
        }

        $rows = array_values($duplicateRows);

        return $rows;
    }
}