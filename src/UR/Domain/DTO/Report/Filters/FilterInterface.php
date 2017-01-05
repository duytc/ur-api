<?php


namespace UR\Domain\DTO\Report\Filters;


interface FilterInterface
{
    /**
     * @return string
     */
    public function getFieldName();

    /**
     * @param $fieldName
     * @return mixed
     */
    public function setFieldName($fieldName);

    /**
     * @return int
     */
    public function getFieldType();

    /**
     * @param $dataSetId
     * @return mixed
     */
    public function trimTrailingAlias($dataSetId);
}