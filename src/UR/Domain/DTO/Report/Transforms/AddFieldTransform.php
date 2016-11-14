<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

class AddFieldTransform implements TransformInterface
{
    protected $fieldName;

    protected $value;

    /**
     * AddFieldTransform constructor.
     * @param $fieldName
     * @param $value
     */
    public function __construct($fieldName, $value)
    {
        $this->fieldName = $fieldName;
        $this->value = $value;
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

    public function transform(Collection $collection)
    {
        // TODO: Implement transform() method.
    }
}