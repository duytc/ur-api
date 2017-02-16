<?php


namespace UR\Domain\DTO\Report\JoinBy;


class JoinField implements JoinFieldInterface
{
    protected $dataSet;
    protected $field;

    /**
     * JoinField constructor.
     * @param $dataSet
     * @param $field
     */
    public function __construct($dataSet, $field)
    {
        $this->dataSet = $dataSet;
        $this->field = $field;
    }

    /**
     * @return mixed
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @param mixed $dataSet
     * @return self
     */
    public function setDataSet($dataSet)
    {
        $this->dataSet = $dataSet;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param mixed $field
     * @return self
     */
    public function setField($field)
    {
        $this->field = $field;
        return $this;
    }
}