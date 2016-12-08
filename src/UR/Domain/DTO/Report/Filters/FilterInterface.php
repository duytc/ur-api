<?php


namespace UR\Domain\DTO\Report\Filters;


interface FilterInterface
{
    /**
     * @return string
     */
    public function getFieldName();

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