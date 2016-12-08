<?php


namespace UR\Domain\DTO\Report\Filters;


abstract class AbstractFilter
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

    public function trimTrailingAlias($dataSetId)
    {
        $this->fieldName = str_replace(sprintf('_%d', $dataSetId), '', $this->fieldName);
        return $this;
    }
}