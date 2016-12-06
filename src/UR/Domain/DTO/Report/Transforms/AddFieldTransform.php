<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class AddFieldTransform extends AbstractTransform implements TransformInterface
{
    const PRIORITY = 3;
    const FIELD_NAME_KEY = 'field';
    const FIELD_VALUE = 'value';
    const TYPE_KEY = 'type';

    protected $fieldName;

    protected $value;

    protected $type;

    /**
     * AddFieldTransform constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct();
        if (!array_key_exists(self::FIELD_NAME_KEY, $data) || !array_key_exists(self::FIELD_VALUE, $data) || !array_key_exists(self::TYPE_KEY, $data)) {
            throw new InvalidArgumentException('either "fields" or "fieldValue" or "type" is missing');
        }

        $this->fieldName = $data[self::FIELD_NAME_KEY];
        $this->value = $data[self::FIELD_VALUE];
        $this->type = $data[self::TYPE_KEY];
    }

    /**
     * @return mixed
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }


    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $joinBy
     * @return mixed|void
     */
    public function transform(Collection $collection,  array &$metrics, array &$dimensions, $joinBy = null)
    {
        $collection->addColumn($this->fieldName);
        $rows = $collection->getRows();
        $columns = $collection->getColumns();
        $types = $collection->getTypes();
        if (is_numeric($this->fieldName)) {
            $this->fieldName = strval($this->fieldName);
        }

        $newRows = array_map(function ($row) {
            $row[$this->fieldName] = $this->value;
            return $row;
        }, $rows);

        $collection->setRows($newRows);

        if (!in_array($this->fieldName, $metrics)) {
            $metrics[] = $this->fieldName;
        }

        if (!in_array($this->fieldName, $columns)) {
            $columns[] = $this->fieldName;
            $types[$this->fieldName] = $this->type;
            $collection->setColumns($columns);
            $collection->setTypes($types);
        }
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (in_array($this->fieldName, $metrics) || in_array($this->fieldName, $dimensions)) {
            return;
        }

        $metrics[] = $this->fieldName;
    }
}