<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface AutoOptimizationConfigInterface extends ModelInterface
{
    /**
     * @inheritdoc
     */
    public function getId();

    /**
     * @param mixed $id
     */
    public function setId($id);

    /**
     * @return mixed
     */
    public function getTransforms();

    /**
     * @param mixed $transforms
     */
    public function setTransforms($transforms);

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
    public function getMetrics();

    /**
     * @param mixed $metrics
     */
    public function setMetrics($metrics);

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
    public function getName();

    /**
     * @param mixed $name
     */
    public function setName($name);

    /**
     * @return mixed
     */
    public function getFieldTypes();

    /**
     * @param mixed $fieldType
     */
    public function setFieldTypes($fieldType);

    /**
     * @return mixed
     */
    public function getJoinBy();

    /**
     * @param mixed $joinBy
     */
    public function setJoinBy($joinBy);

    /**
     * @return mixed
     */
    public function getCreatedDate();

    /**
     * @param mixed $createdDate
     */
    public function setCreatedDate($createdDate);

    /**
     * @return mixed
     */
    public function getPublisher();

    /**
     * @param mixed $publisher
     */
    public function setPublisher($publisher);

    /**
     * @return array
     */
    public function getAutoOptimizationConfigDataSets();

    /**
     * @param mixed $autoOptimizationConfigDataSets
     */
    public function setAutoOptimizationConfigDataSets($autoOptimizationConfigDataSets);

    /**
     * @return mixed
     */
    public function getFactors();

    /**
     * @param mixed $factors
     */
    public function setFactors($factors);

    /**
     * @return mixed
     */
    public function getObjective();

    /**
     * @param mixed $objective
     */
    public function setObjective($objective);

    /**
     * @return mixed
     */
    public function getDateRange();

    /**
     * @param mixed $dateRange
     */
    public function setDateRange($dateRange);

    /**
     * @return mixed
     */
    public function getActive();

    /**
     * @param mixed $active
     */
    public function setActive($active);
}