<?php


namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Behaviors\ParserUtilTrait;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;

class SubsetGroup implements CollectionTransformerInterface
{
    use ParserUtilTrait;

    const DATA_SOURCE_SIDE = 'leftSide';
    const GROUP_DATA_SET_SIDE = 'rightSide';

    const MAP_FIELDS_KEY = 'mapFields';
    const GROUP_FIELD_KEY = 'groupFields';
    const AGGREGATION_FIELDS_KEY = 'aggregationFields';
    const AGGREGATE_ALL_KEY = 'aggregateAll';

    /**
     * @var array
     */
    protected $groupFields;

    /**
     * @var array
     */
    protected $aggregationFields;

    /**
     * @var array
     */
    protected $mapFields;

    /**
     * @var bool
     */
    protected $aggregateAll;

    /**
     * SubsetGroup constructor.
     * @param array $groupFields
     * @param bool $aggregateAll
     * @param array $aggregationFields
     * @param array $mapFields
     */
    public function __construct(array $groupFields, $aggregateAll = true, array $aggregationFields, array $mapFields)
    {
        $this->groupFields = $groupFields;
        $this->mapFields = $mapFields;
        $this->aggregateAll = $aggregateAll;
        $this->aggregationFields = $aggregationFields;
    }


    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        $rows = $collection->getRows();
        if ($rows instanceof SplDoublyLinkedList && count($rows) < 1) {
            return $collection;
        }

        $collection = $this->updateColumns($connectedDataSource, $collection);
        $sumFields = $this->getSumFields();
        $groupedReports = $this->generateGroupedArray($this->groupFields, $collection);
        $newRows = new SplDoublyLinkedList();

        foreach ($groupedReports as $groupedReport) {
            $mapValuesAllRows = [];
            if (empty($sumFields)) {
                $headerRow = reset($groupedReport);
                $mapValuesAllRows = $this->getMapValues($headerRow);
            } else {
                foreach ($groupedReport as $row) {
                    $mapValuesOneRow = $this->getMapValues($row);
                    $mapValuesAllRows = $this->mergeSubSetResult($mapValuesAllRows, $mapValuesOneRow, $collection);
                }
            }
            $this->addMapValuesToRows($newRows, $mapValuesAllRows, $groupedReport);
        }

        return new Collection($collection->getColumns(), $newRows, $collection->getTypes());
    }

    /**
     * The idea is that some column transformers should run before others to avoid conflicts
     * i.e usually you would want to group columns before adding calculated fields
     * The parser config should read this priority value and order the transformers based on this value
     * Lower numbers mean higher priority, for example -10 is higher than 0.
     * Maybe we should allow the end user to override this if they know what they are doing
     *
     * @return int
     */
    public function getDefaultPriority()
    {
        return self::TRANSFORM_PRIORITY_SUBSET_GROUP;
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }

    /**
     * @return array
     */
    public function getGroupFields()
    {
        return $this->groupFields;
    }

    /**
     * @return array
     */
    public function getMapFields()
    {
        return $this->mapFields;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @param Collection $collection
     * @return Collection
     */
    private function updateColumns(ConnectedDataSourceInterface $connectedDataSource, Collection $collection)
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();

        if ($rows->count() < 1) {
            return $collection;
        }
        $mappedFields = array_flip($connectedDataSource->getMapFields());
        foreach ($rows as $row) {
            $dataColumns = array_keys($row);
            foreach ($this->groupFields as &$groupField) {
                if (!in_array($groupField, $dataColumns)) {
                    if (!array_key_exists($groupField, $mappedFields)) {
                        return $collection;
                    }

                    $groupField = $mappedFields[$groupField];
                }
            }

            break;
        }

        foreach ($this->mapFields as $mapField) {
            if (!array_key_exists(self::DATA_SOURCE_SIDE, $mapField)) {
                continue;
            }
            $field = $mapField[self::DATA_SOURCE_SIDE];

            if (in_array($field, $columns)) {
                continue;
            }

            $columns[] = $field;
        }

        $collection->setColumns($columns);

        return $collection;
    }

    /**
     * @return array
     */
    private function getSumFields()
    {
        if ($this->aggregateAll) {
            $mapFields = $this->getMapFields();

            if (!is_array($mapFields)) {
                return [];
            }

            $sumFields = [];

            foreach ($mapFields as $mapField) {
                if (!array_key_exists(self::GROUP_DATA_SET_SIDE, $mapField)) {
                    continue;
                }
                $sumFields[] = $mapField[self::GROUP_DATA_SET_SIDE];
            }

            return $sumFields;
        } else {
            return $this->aggregationFields == null ? [] : $this->aggregationFields;
        }
    }

    /**
     * @param array $row
     * @return array
     */
    private function getMapValues(array $row)
    {
        $map = [];
        foreach ($this->mapFields as $mapField) {
            if (!array_key_exists(self::DATA_SOURCE_SIDE, $mapField) || !array_key_exists(self::GROUP_DATA_SET_SIDE, $mapField)) {
                continue;
            }

            $leftSide = $mapField[self::DATA_SOURCE_SIDE];
            $rightSide = $mapField[self::GROUP_DATA_SET_SIDE];

            if (!array_key_exists($rightSide, $row)) {
                continue;
            }

            $map[$leftSide] = $row[$rightSide];
        }

        return $map;
    }

    /**
     * @param array $mapValueAllRows
     * @param array $mapValueOneRow
     * @param Collection $collection
     * @return array
     */
    private function mergeSubSetResult(array $mapValueAllRows, array $mapValueOneRow, Collection $collection)
    {
        foreach ($mapValueOneRow as $key => $value) {
            if (in_array($collection->getTypeOf($key), [FieldType::NUMBER, FieldType::DECIMAL])) {
                $mapValueOneRow[$key] += array_key_exists($key, $mapValueAllRows) ? $mapValueAllRows[$key] : 0;
            }
        }

        return $mapValueOneRow;
    }

    /**
     * @param SplDoublyLinkedList $newRows
     * @param array $map
     * @param array $groupedReport
     * @return SplDoublyLinkedList
     */
    private function addMapValuesToRows(SplDoublyLinkedList $newRows, array $map, array $groupedReport)
    {
        foreach ($groupedReport as $row) {
            foreach ($map as $field => $value) {
                $row[$field] = $value;
            }

            $newRows->push($row);
        }

        return $newRows;
    }

    /**
     * @return array
     */
    public function getAggregationFields()
    {
        return $this->aggregationFields;
    }

    /**
     * @param array $aggregationFields
     */
    public function setAggregationFields($aggregationFields)
    {
        $this->aggregationFields = $aggregationFields;
    }

    /**
     * @return boolean
     */
    public function isAggregateAll()
    {
        return $this->aggregateAll;
    }

    /**
     * @param boolean $aggregateAll
     */
    public function setAggregateAll($aggregateAll)
    {
        $this->aggregateAll = $aggregateAll;
    }
}