<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface AutoOptimizationConfigDataSetInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param mixed $id
     */
    public function setId($id);

    /**
     * @return mixed
     */
    public function getFilters();

    /**
     * @param mixed $filters
     */
    public function setFilters($filters);

    /**
     * @return mixed
     */
    public function getDimensions();

    /**
     * @param mixed $dimensions
     */
    public function setDimensions($dimensions);

    /**
     * @return mixed
     */
    public function getMetrics();

    /**
     * @param mixed $metrics
     */
    public function setMetrics($metrics);

    /**
     * @return mixed
     */
    public function getAutoOptimizationConfig();

    /**
     * @param mixed $autoOptimizationConfig
     */
    public function setAutoOptimizationConfig($autoOptimizationConfig);

    /**
     * @return mixed
     */
    public function getDataSet();

    /**
     * @param mixed $dataSet
     */
    public function setDataSet($dataSet);

}