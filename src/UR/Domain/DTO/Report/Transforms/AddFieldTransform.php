<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class AddFieldTransform extends AbstractTransform implements TransformInterface
{
    const PRIORITY = 1;
    const FIELD_NAME_KEY = 'field';
    const FIELD_VALUE = 'value';

    protected $fieldName;

    protected $value;

    /**
     * AddFieldTransform constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct();
        if (!array_key_exists(self::FIELD_NAME_KEY, $data) || !array_key_exists(self::FIELD_VALUE, $data)) {
            throw new InvalidArgumentException('either "fields" or "fieldValue" is missing');
        }

        $this->fieldName = $data[self::FIELD_NAME_KEY];
        $this->value = $data[self::FIELD_VALUE];
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
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @return mixed|void
     */
    public function transform(Collection $collection,  array $metrics, array $dimensions)
    {
        $columns = $collection->getColumns();
        // new field already existed
        if (in_array($this->fieldName, $columns)) {
            return;
        }

        $collection->addColumn($this->fieldName);
        $rows = $collection->getRows();

        if (is_numeric($this->fieldName)) {
            $this->fieldName = strval($this->fieldName);
        }

        $newRows = [];
        foreach ($rows as $row) {
            $tmp[$this->fieldName] = $this->value;
            $newRows[] = ($row + $tmp);
        }

        $collection->setRows($newRows);
    }
}