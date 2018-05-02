<?php


namespace UR\Model\Core;


class Learner implements LearnerInterface
{
    protected $id;
    protected $identifier;
    protected $segmentValues;
    protected $optimizeField;
    protected $modelPath;
    protected $mathModel;
    protected $metricsPredictiveValues;
    protected $createdDate;
    protected $updatedDate;
    protected $optimizationRule;

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
    public function getSegmentValues()
    {
        return $this->segmentValues;
    }

    /**
     * @inheritdoc
     */
    public function setSegmentValues($segmentValues)
    {
        $this->segmentValues = $segmentValues;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getModelPath()
    {
        return $this->modelPath;
    }

    /**
     * @inheritdoc
     */
    public function setModelPath($modelPath)
    {
        $this->modelPath = $modelPath;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMathModel()
    {
        return $this->mathModel;
    }

    /**
     * @inheritdoc
     */
    public function setMathModel($mathModel)
    {
        $this->mathModel = $mathModel;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMetricsPredictiveValues()
    {
        return $this->metricsPredictiveValues;
    }

    /**
     * @inheritdoc
     */
    public function setMetricsPredictiveValues($metricsPredictiveValues)
    {
        $this->metricsPredictiveValues = $metricsPredictiveValues;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;


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
    public function getOptimizationRule()
    {
        return $this->optimizationRule;
    }

    /**
     * @inheritdoc
     */
    public function setOptimizationRule($optimizationRule)
    {
        $this->optimizationRule = $optimizationRule;

        return $this;
    }
}