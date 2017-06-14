<?php


namespace UR\Service\Parser\Transformer\Collection;


use Doctrine\ORM\EntityManagerInterface;
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

    /**
     * @var array
     */
    protected $groupFields;

    /**
     * @var array
     */
    protected $mapFields;


    /**
     * SubsetGroup constructor.
     * @param array $groupFields
     * @param array $mapFields
     */
    public function __construct(array $groupFields, array $mapFields)
    {
        $this->groupFields = $groupFields;
        $this->mapFields = $mapFields;
    }


    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();
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
        $groupByTransform = new GroupByColumns($this->groupFields);
        $subsetRows = $groupByTransform->transform($collection, $em, $connectedDataSource, $fromDateFormats, $connectedDataSource->getMapFields())->getRows();
        $subsetKeys = [];

        foreach ($subsetRows as $row) {
            $subsetKeys[] = $this->getJoinKey($this->groupFields, $row, $connectedDataSource);
        }

        $subsetRows = array_combine($subsetKeys, $subsetRows);

        foreach ($rows as &$row) {
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
        }

        $collection->setRows($rows);
        $collection->setColumns($columns);

        return $collection;
    }

    /**
     * @param array $columns
     * @param array $row
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return string
     */
    protected function getJoinKey(array $columns, array $row, $connectedDataSource)
    {
        $data = [];

        // need to guarantee column order is the same or key hash will be different
        foreach ($columns as $column) {
            $fieldType = $this->getFieldType($column, $connectedDataSource);
            if (isset($row[$column])) {
                if ($fieldType == FieldType::DATETIME) {
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
    private function getFieldType($fieldFromFile, $connectedDataSource)
    {
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return null;
        }

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