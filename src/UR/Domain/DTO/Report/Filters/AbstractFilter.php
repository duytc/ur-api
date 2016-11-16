<?php


namespace UR\Domain\DTO\Report\Filters;


abstract class AbstractFilter implements AbstractFilterInterface
{
    const TYPE_DATE = 1;
    const TYPE_TEXT = 2;
    const TYPE_NUMBER = 3;

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var int
     */
    protected $fieldType;

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @return int
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }
}