<?php


namespace UR\Domain\DTO\Report\JoinBy;


interface JoinFieldInterface
{
    /**
     * @return mixed
     */
    public function getDataSet();

    /**
     * @param mixed $dataSet
     * @return self
     */
    public function setDataSet($dataSet);

    /**
     * @return mixed
     */
    public function getField();

    /**
     * @param mixed $field
     * @return self
     */
    public function setField($field);
}