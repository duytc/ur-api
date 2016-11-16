<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class AddFieldTransform implements TransformInterface
{
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
     * @return Collection
     */
    public function transform(Collection $collection)
    {
        $collection->addColumn($this->fieldName);
        $rows = $collection->getRows();

        $newRows = [];
        foreach ($rows as $row) {
            $tmp[$this->fieldName] = $this->value;
            $newRows[] = array_merge($row, $tmp);
        }
        $collection->setRows($newRows);

        return $collection;
    }
}