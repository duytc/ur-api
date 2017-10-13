<?php

namespace UR\Service\Parser;

use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Transformer\Collection\Augmentation;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;
use UR\Service\Parser\Transformer\Column\NumberFormat;

class Parser implements ParserInterface
{
    /**@var UREventDispatcherInterface $urEventDispatcher
     */
    protected $urEventDispatcher;

    /** @var EntityManagerInterface */
    protected $em;

    private $reformatDataService;

    /**
     * Parser constructor.
     * @param UREventDispatcherInterface $eventDispatcher
     * @param EntityManagerInterface $em
     */
    public function __construct(UREventDispatcherInterface $eventDispatcher, EntityManagerInterface $em)
    {
        $this->urEventDispatcher = $eventDispatcher;
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
        $collection = $this->createCollection($rows, $fileCols, $parserConfig, $connectedDataSource);
        $collection = $this->addTemporaryFields($collection, $connectedDataSource);

        /** Check before fire any UR event to custom transform bundle */
        if ($rows->count() < 1) {
            return $collection;
        }

        $collection = $this->urEventDispatcher->postLoadDataEvent($connectedDataSource, $collection);

        $collection = $this->urEventDispatcher->preFilterDataEvent($connectedDataSource, $collection);

        $collection = $this->urEventDispatcher->preTransformColumnDataEvent($connectedDataSource, $collection);

        $collection = $this->urEventDispatcher->preTransformCollectionDataEvent($connectedDataSource, $collection);

        $collection = $this->executeTransforms($parserConfig, $connectedDataSource, $collection);

        $collection = $this->getFinalCollection($parserConfig, $connectedDataSource, $collection);

        $collection = $this->urEventDispatcher->postParseDataEvent($connectedDataSource, $collection);

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
        try {
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
        } catch (\Exception $e) {
            return 0;
        }
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
    private function switchFieldInFileToFieldInDataSet(SplDoublyLinkedList $rows, array $columns, ParserConfig $parserConfig)
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

    /**
     * @param SplDoublyLinkedList $rows
     * @param array $fileCols
     * @param ParserConfig $parserConfig
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return Collection
     */
    private function createCollection(SplDoublyLinkedList $rows, array $fileCols, ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource)
    {
        $columnsMapping = $parserConfig->getAllColumnMappings();

        $fileCols = array_map("trim", $fileCols);

        $columns = array_intersect($fileCols, $parserConfig->getAllColumnMappings());

        $dataSetColumns = array_merge($connectedDataSource->getDataSet()->getDimensions(), $connectedDataSource->getDataSet()->getMetrics());
        $types = [];
        foreach ($dataSetColumns as $dsColumn => $type) {
            if (!array_key_exists($dsColumn, $columnsMapping)) {
                $types[$dsColumn] = $type;
                continue;
            }

            $types[$columnsMapping[$dsColumn]] = $type;
        }
        $collection = new Collection($columns, $rows, $types);

        return $collection;
    }

    /**
     * @param ParserConfig $parserConfig
     * @return array
     */
    private function getFromDateFormats(ParserConfig $parserConfig)
    {
        $fromDateFormat = [];
        foreach ($parserConfig->getTransforms() as $transform) {
            if ($transform instanceof DateFormat) {
                $fromDateFormat[$transform->getField()] = array('formats' => $transform->getFromDateFormats(), 'timezone' => $transform->getTimezone());
            }
        }

        return $fromDateFormat;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param ParserConfig $parserConfig
     * @param Collection $collection
     * @return Collection
     */
    private function overWriteDuplicate(ConnectedDataSourceInterface $connectedDataSource, ParserConfig $parserConfig, Collection $collection)
    {
        //overwrite duplicate
        if ($connectedDataSource->getDataSet()->getAllowOverwriteExistingData()) {
            $mappedDimensions = array_intersect_key(array_flip($parserConfig->getAllColumnMappings()), $connectedDataSource->getDataSet()->getDimensions());
            $this->overrideDuplicate($collection, array_flip($mappedDimensions));
        }

        return $collection;
    }

    /**
     * @param ParserConfig $parserConfig
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    private function executeTransforms(ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource, Collection $collection)
    {
        /** Execute other transforms */
        $allFieldsTransforms = $parserConfig->getTransforms();

        if (!is_array($allFieldsTransforms)) {
            return $collection;
        }

        /** Execute last transform Augmentation */
        $fromDateFormat = $this->getFromDateFormats($parserConfig);

        foreach ($allFieldsTransforms as $transform) {
            if ($transform instanceof DateFormat || $transform instanceof NumberFormat) {
                $transform->transformCollection($collection, $connectedDataSource);
                continue;
            }

            if ($transform instanceof Augmentation) {
                $collection = $transform->transform($collection, $this->em, $connectedDataSource, $fromDateFormat);
                continue;
            }

            if ($transform instanceof GroupByColumns || $transform instanceof SubsetGroup) {
                $collection = $transform->transform($collection, $this->em, $connectedDataSource);
                continue;
            }

            $collection = $transform->transform($collection);
        }

        return $collection;
    }
    
    /**
     * @param ParserConfig $parserConfig
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    private function getFinalCollection(ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource, Collection $collection)
    {
        $collection = $this->overWriteDuplicate($connectedDataSource, $parserConfig, $collection);
        $collection = $this->removeTemporaryFields($collection, $connectedDataSource);

        if ($collection->getRows()->count() < 1) {
            return $collection;
        }
        $columns = $this->getColumnsAfterDoCollectionTransforms($collection->getRows()[0], array_flip($parserConfig->getAllColumnMappings()), $collection->getColumns());

        $collection = $this->switchFieldInFileToFieldInDataSet($collection->getRows(), $columns, $parserConfig);

        return $collection;
    }
}