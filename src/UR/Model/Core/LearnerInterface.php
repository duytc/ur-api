<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface LearnerInterface extends ModelInterface
{

    public function getId();

    /**
     * @return mixed
     */
    public function getIdentifier();

    /**
     * @param mixed $identifier
     */
    public function setIdentifier($identifier);

    /**
     * @return mixed
     */
    public function getSegmentValues();

    /**
     * @param mixed $segmentValues
     */
    public function setSegmentValues($segmentValues);

    /**
     * @return mixed
     */
    public function getModelPath();

    /**
     * @param mixed $modelPath
     */
    public function setModelPath($modelPath);

    /**
     * @return mixed
     */
    public function getMetricsPredictiveValues();

    /**
     * @param mixed $metricsPredictiveValues
     */
    public function setMetricsPredictiveValues($metricsPredictiveValues);

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
    public function getUpdatedDate();

    /**
     * @param mixed $updatedDate
     */
    public function setUpdatedDate($updatedDate);

    /**
     * @return mixed
     */
    public function getOptimizationRule();

    /**
     * @param mixed $optimizationRule
     */
    public function setOptimizationRule($optimizationRule);

    /**
     * @inheritdoc
     */
    public function getMathModel();

    /**
     * @inheritdoc
     */
    public function setMathModel($mathModel);
}