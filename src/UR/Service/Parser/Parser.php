<?php

namespace UR\Service\Parser;

use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
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
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;
use UR\Service\Report\SqlBuilder;

class Parser implements ParserInterface
{
    /**@var EventDispatcherInterface $eventDispatcher
     */
    protected $eventDispatcher;

    /** @var EntityManagerInterface */
    protected $em;

    private $reformatDataService;

    /**
     * Parser constructor.
     * @param EventDispatcherInterface $eventDispatcher
     * @param EntityManagerInterface $em
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, EntityManagerInterface $em)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->em = $em;
        $this->reformatDataService = new ReformatDataService();
    }

    /**
     * @param array $fileCols
     * @param SplDoublyLinkedList $rows
     * @param ParserConfig $parserConfig
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return Collection
     */
    public function parse(array $fileCols, SplDoublyLinkedList $rows, ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource)
    {
        $columnsMapping = $parserConfig->getAllColumnMappings();
        $columnFromMap = array_flip($parserConfig->getAllColumnMappings());

//        $fileCols = array_map("strtolower", $fileCols);
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

//        $rows = $postLoadDataEvent->getRows();

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

//        $rows = $preFilterEvent->getRows();

        // TODO: may dispatch event after filtering data
        $collection = new Collection($columns, $rows, $types);

        if ($rows->count() < 1) {
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

        $fromDateFormat = [];
        foreach ($parserConfig->getColumnTransforms() as $column => $transforms) {
            foreach ($transforms as $transform) {
                if ($transform instanceof DateFormat) {
                    $fromDateFormat[$column] = array('formats' => $transform->getFromDateFormats(), 'timezone' => $transform->getTimezone());
                }
            }
        }

        $mapFields = $connectedDataSource->getMapFields();

        // transform collection
        foreach ($allFieldsTransforms as $transform) {
            /** @var CollectionTransformerInterface $transform */
            if ($transform instanceof Augmentation) {
                continue;
            }
            if ($transform instanceof SubsetGroup || $transform instanceof GroupByColumns) {
                $collection = $transform->transform($collection, $this->em, $connectedDataSource, $fromDateFormat, $mapFields);
            } else {
                $collection = $transform->transform($collection);
            }
        }

        /** Augmentation need other transforms completed first */

        foreach ($allFieldsTransforms as $transform) {
            /** @var CollectionTransformerInterface $transform */
            if ($transform instanceof Augmentation) {
                $collection = $transform->transform($collection, $this->em, $connectedDataSource, $fromDateFormat, $mapFields);
            }
        }

        //overwrite duplicate
        if ($connectedDataSource->getDataSet()->getAllowOverwriteExistingData()) {
            $mappedDimensions = array_intersect_key($columnsMapping, $connectedDataSource->getDataSet()->getDimensions());
            $this->overrideDuplicate($collection, array_flip($mappedDimensions));
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
        $rows = $collection->getRows();
        if ($rows->count() < 1) {
            return $collection;
        }

        $columns = $this->getColumnsAfterDoCollectionTransforms($rows[0], $columnFromMap, $collection->getColumns());

        $collection = $this->getFinalParserCollection($rows, $columns, $parserConfig);

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

        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            foreach ($temporaryFields as $temp) {
                $columns[] = $temp;
                $types[$temp] = FieldType::TEMPORARY;
                $row[$temp] = '';
            }

            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);
        $collection->setColumns($columns);
        $collection->setTypes($types);
        $collection->setRows($newRows);

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

        $tempFieldAsKeys = array_flip($temporaryFields);
        $columns = array_diff_key($columns, $tempFieldAsKeys);
        $types = array_diff_key($types, $tempFieldAsKeys);
        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            $row = array_diff_key($row, $tempFieldAsKeys);
            $newRows->push($row);
            unset($row);
        }

        unset ($rows, $row);

        $collection->setColumns($columns);
        $collection->setRows($newRows);
        $collection->setTypes($types);

        return $collection;
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
     * @param SplDoublyLinkedList $rows
     * @param array $columns
     * @param ParserConfig $parserConfig
     * @return Collection
     * @throws ImportDataException
     */
    private function getFinalParserCollection(SplDoublyLinkedList $rows, array $columns, ParserConfig $parserConfig)
    {
        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row = array_intersect_key($row, $columns);
            if (empty($row)) {
                // after array)intersect_key, the $row may be an empty array
                // so that we must remove to avoid an empty row data
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
                continue;
            }

            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);

        return new Collection($columns, $newRows);
    }


    /**
     * @param Collection $collection
     * @param array $dimensions
     * @return void
     */
    private function overrideDuplicate(Collection $collection, array $dimensions)
    {
        $rows = $collection->getRows();
        $duplicateRows = [];
        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $uniqueKeys = array_intersect_key($row, $dimensions);
            $uniqueId = md5(implode(":", $uniqueKeys));
            if (array_key_exists($uniqueId, $duplicateRows)) {
                continue;
            }

            $duplicateRows[] = $uniqueId;
            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);
        $collection->setRows($newRows);
    }

    public function combineRowsWithColumns(array $fileCols, SplDoublyLinkedList $rows, ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource) {

        $columnsMapping = $parserConfig->getAllColumnMappings();
        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $isMapped = count(array_diff_key(array_flip($fileCols), $row));
            if ($isMapped > 0 && count($fileCols) === count($row)) {
                $row = array_combine($fileCols, $row);
            }

            $dataSetColumns = array_merge($connectedDataSource->getDataSet()->getDimensions(), $connectedDataSource->getDataSet()->getMetrics());
            foreach ($dataSetColumns as $dsColumn => $type) {
                if (!array_key_exists($dsColumn, $columnsMapping)) {
                    continue;
                }

                $fileColumn = $columnsMapping[$dsColumn];

                if (!array_key_exists($fileColumn, $row)) {
                    continue;
                }

                $row[$fileColumn] = $this->reformatDataService->reformatData($row[$fileColumn], $type);
            }

            $isValidFilter = $this->doFilter($parserConfig, $fileCols, $row);

            if (!$isValidFilter) {
                continue;
            }

            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);
        return $newRows;
    }
}