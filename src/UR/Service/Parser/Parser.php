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
use UR\Service\DataSource\DataSourceInterface;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Transformer\Augmentation;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\SubsetGroup;

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
     * @param DataSourceInterface $dataSource
     * @param ParserConfig $parserConfig
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return Collection
     */
    public function parse(DataSourceInterface $dataSource, ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource)
    {
        $columnsMapping = $parserConfig->getAllColumnMappings();
        $columnFromMap = array_flip($parserConfig->getAllColumnMappings());

        $fileCols = array_map("strtolower", $dataSource->getColumns());
        $fileCols = array_map("trim", $fileCols);

        $columns = array_intersect($fileCols, $parserConfig->getAllColumnMappings());
        $columns = array_map(function ($fromColumn) use ($columnFromMap) {
            return $columnFromMap[$fromColumn];
        }, $columns);

        $format = [];//format date
        foreach ($parserConfig->getColumnTransforms() as $field => $columnTransform) {
            foreach ($columnTransform as $item) {
                if ($item instanceof DateFormat) {
                    $format[$field] = $item->getFromDateFormat();
                }
            }
        }

        /* 1. get all row data */
        $rows = array_values($dataSource->getRows($format));

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

                $row[$fileColumn] = $this->reformatFileData($row, $fileColumn, $type, $cur_row);
            }

            $isValidFilter = $this->doFilter($parserConfig, $fileCols, $row, $cur_row);

            if (!$isValidFilter) {
                unset($rows[$cur_row]);
                continue;
            }
        }

        // TODO: may dispatch event after filtering data

        $collection = new Collection($columns, $rows, $types);

        if (count($rows) < 1) {
            return $collection;
        }

        /* 3. do transforming data */
        $allFieldsTransforms = $parserConfig->getCollectionTransforms();

        if (!$connectedDataSource->isUserReorderTransformsAllowed()) {
            usort($allFieldsTransforms, function (CollectionTransformerInterface $a, CollectionTransformerInterface $b) {
                if ($a->getDefaultPriority() == $b->getDefaultPriority()) {
                    return 0;
                }
                return ($a->getDefaultPriority() < $b->getDefaultPriority()) ? -1 : 1;
            });
        }

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

        // transform collection
        foreach ($allFieldsTransforms as $transform) {
            /** @var CollectionTransformerInterface $transform */
            try {
                if ($transform instanceof Augmentation || $transform instanceof SubsetGroup) {
                    $collection = $transform->transform($collection, $this->em, $connectedDataSource);
                } else {
                    $collection = $transform->transform($collection);
                }
            } catch (\Exception $e) {
                return $collection;
            }
        }

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
     * @param array $row
     * @param $fileColumn
     * @param string $type
     * @param int $cur_row
     * @return mixed|null
     * @throws ImportDataException
     */
    private function reformatFileData(array $row, $fileColumn, $type, $cur_row)
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
            case FieldType::MULTI_LINE_TEXT:
                if ($cellValue === "") {
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
     * @param int $cur_row
     * @return int
     * @throws ImportDataException
     */
    private function doFilter(ParserConfig $parserConfig, array $fileCols, array $row, $cur_row)
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
}