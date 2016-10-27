<?php

namespace UR\Service\DataSet;

class DataSet
{
    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function addDimension($column, $type, array $options = [])
    {
        return $this;
    }

    public function addMetric($column, $type, array $options = [])
    {
        return $this;
    }
}