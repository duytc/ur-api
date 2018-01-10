<?php


namespace UR\Model\Core;


class Learner implements LearnerInterface
{
    protected $id;
    protected $identifier;
    protected $model;
    protected $type;
    protected $autoOptimizationConfig;
    protected $updatedDate;
    protected $forecastFactorValues;

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @inheritdoc
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @inheritdoc
     */
    public function setModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAutoOptimizationConfig()
    {
        return $this->autoOptimizationConfig;
    }

    /**
     * @inheritdoc
     */
    public function setAutoOptimizationConfig($autoOptimizationConfig)
    {
        $this->autoOptimizationConfig = $autoOptimizationConfig;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedDate()
    {
        return $this->updatedDate;
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedDate($updatedDate)
    {
        $this->updatedDate = $updatedDate;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getForecastFactorValues()
    {
        return $this->forecastFactorValues;
    }

    /**
     * @inheritdoc
     */
    public function setForecastFactorValues($forecastFactorValues)
    {
        $this->forecastFactorValues = $forecastFactorValues;

        return $this;
    }
}