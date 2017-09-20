<?php


namespace UR\Service\Parser\Transformer\Collection;


use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DTO\Collection;
use UR\Service\Parser\Transformer\Column\DateFormat;

class SubsetGroup implements CollectionTransformerInterface
{
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
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        $mappedFields = array_flip($connectedDataSource->getMapFields());
        $allFields = $connectedDataSource->getDataSet()->getAllDimensionMetrics();
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
            $field = $mapField[self::DATA_SOURCE_SIDE];
            if (in_array($field, $columns)) {
                continue;
            }

            $columns[] = $field;
        }

        // create subset
        $copyCollection = clone $collection;
        $aggregationFields = array_keys(array_intersect(array_flip($connectedDataSource->getMapFields()), $this->aggregationFields));
        $groupByTransform = new GroupByColumns($this->groupFields, $this->aggregateAll, $aggregationFields);
        $subsetRows = $groupByTransform->transform($copyCollection, $em, $connectedDataSource, $fromDateFormats, $connectedDataSource->getMapFields())->getRows();
        $subsetKeys = [];

        $subSetData = [];
        foreach ($subsetRows as $row) {
            $subsetKeys[] = $this->getJoinKey($this->groupFields, $row, $connectedDataSource);
            $subSetData[] = $row;
        }

        $subsetRows = array_combine($subsetKeys, $subSetData);

        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            $joinKey = $this->getJoinKey($this->groupFields, $row, $connectedDataSource);

            if (!isset($subsetRows[$joinKey])) {
                continue;
            }

            $subsetRow = $subsetRows[$joinKey];
            foreach ($this->mapFields as $mapField) {
                $leftSide = $mapField[self::DATA_SOURCE_SIDE];
                $rightSide = $mapField[self::GROUP_DATA_SET_SIDE];
                $isNumber = array_key_exists($leftSide, $allFields) && $allFields[$leftSide] == FieldType::NUMBER;
                $row[$leftSide] = $isNumber ? round($subsetRow[$rightSide]) : $subsetRow[$rightSide];
            }
            $newRows->push($row);
            unset($row);
        }

        unset($rows, $row);
        unset($collection, $copyCollection, $subsetRows, $subSetData, $subsetKeys);
        return new Collection($columns, $newRows, $types);
    }

    /**
     * @param array $columns
     * @param array $row
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return string
     */
    protected function getJoinKey(array $columns, array $row, ConnectedDataSourceInterface $connectedDataSource)
    {
        $data = [];

        // need to guarantee column order is the same or key hash will be different
        foreach ($columns as $column) {
            $fieldType = $this->getFieldType($column, $connectedDataSource);
            if (isset($row[$column])) {
                if ($fieldType == FieldType::DATETIME || $fieldType == FieldType::DATE) { // include format type DATE because now support partial match
                    $date = DateTime::createFromFormat(GroupByColumns::TEMPORARY_DATE_FORMAT, $row[$column]);
                    if ($date instanceof DateTime) {
                        $data[] = $date->format(DateFormat::DEFAULT_DATE_FORMAT);
                        continue;
                    }

                    $data[] = DateFormat::getDateFromDateTime($row[$column], $column, $connectedDataSource);
                } else {
                    $data[] = $row[$column];
                }
            }
        }

        $key = md5(join('|', $data));

        return $key;
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
     * @param $fieldFromFile
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return string
     */
    private function getFieldType($fieldFromFile, ConnectedDataSourceInterface $connectedDataSource)
    {
        $mapFields = $connectedDataSource->getMapFields();
        if (!array_key_exists($fieldFromFile, $mapFields)) {
            return null;
        }

        $field = $mapFields[$fieldFromFile];
        $dataSet = $connectedDataSource->getDataSet();
        $allFields = array_merge($dataSet->getDimensions(), $dataSet->getMetrics());

        if (!array_key_exists($field, $allFields)) {
            return null;
        }
        return $allFields[$field];
    }
}