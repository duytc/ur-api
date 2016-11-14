<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class SingleFieldTransform extends Transform implements SingleFieldTransformInterface
{
    const TYPE_FORMAT_NUMBER = 1;
    const TYPE_FORMAT_TEXT = 2;

    /**
     * @var int
     */
    protected $type;

    /**
     * @var string
     */
    protected $fieldName;

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
    public function getType()
    {
        return $this->type;
    }
}