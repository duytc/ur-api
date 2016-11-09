<?php


namespace UR\Domain\DTO\Report\Transforms;


abstract class SingleFieldTransform extends AbstractTransform
{
    const TYPE_FORMAT_NUMBER = 1;
    const TYPE_FORMAT_TEXT = 2;

    protected $type;

    protected $fieldName;

    function __construct($fieldName, $type, $target )
    {
        parent::__construct($target);
        $this->fieldName = $fieldName;
        $this->type = $type;
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
    public function getType()
    {
        return $this->type;
    }
}