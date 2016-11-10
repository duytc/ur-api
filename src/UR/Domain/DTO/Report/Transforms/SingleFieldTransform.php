<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class SingleFieldTransform extends AbstractTransform implements SingleFieldTransformInterface
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
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }
}