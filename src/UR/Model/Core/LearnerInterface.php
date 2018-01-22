<?php


namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface LearnerInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getIdentifier();

    /**
     * @param mixed $identifier
     * @return self
     */
    public function setIdentifier($identifier);

    /**
     * @return mixed
     */
    public function getModel();

    /**
     * @param mixed $model
     * @return self
     */
    public function setModel($model);

    /**
     * @return mixed
     */
    public function getType();

    /**
     * @param mixed $type
     * @return self
     */
    public function setType($type);

    /**
     * @return AutoOptimizationConfigInterface
     */
    public function getAutoOptimizationConfig();

    /**
     * @param mixed $autoOptimizationConfig
     * @return self
     */
    public function setAutoOptimizationConfig($autoOptimizationConfig);

    /**
     * @return mixed
     */
    public function getUpdatedDate();

    /**
     * @param mixed $updatedDate
     * @return self
     */
    public function setUpdatedDate($updatedDate);

    /**
     * @return mixed
     */
    public function getForecastFactorValues();

    /**
     * @param mixed $forecastFactorValues
     * @return self
     */
    public function setForecastFactorValues($forecastFactorValues);

    /**
     * @return mixed
     */
    public function getCategoricalFieldWeights();
    
    /**
     * @param mixed $categoricalFieldWeights
     * @return self
     */
    public function setCategoricalFieldWeights($categoricalFieldWeights);
}