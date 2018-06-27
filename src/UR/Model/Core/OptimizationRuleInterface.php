<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface OptimizationRuleInterface extends ModelInterface
{
    const IDENTIFIER_COLUMN = 'identifier';

    public function getId();

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
    public function  getDateField();

    /**
     * @param mixed $dateField
     */
    public function setDateField($dateField);

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
    public function getIdentifierFields();

    /**
     * @param mixed $identifierFields
     */
    public function setIdentifierFields($identifierFields);

    /**
     * @inheritdoc
     */
    public function getOptimizeFields();

    /**
     * @inheritdoc
     */
    public function setOptimizeFields($optimizeFields);

    /**
     * @return mixed
     */
    public function getSegmentFields();

    /**
     * @param mixed $segmentField
     */
    public function setSegmentFields($segmentField);

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
    public function getReportView();

    /**
     * @param mixed $reportView
     */
    public function setReportView($reportView);

    /**
     * @return mixed
     */
    public function getPublisher();

    /**
     * @param $publisher
     * @return mixed
     */
    public function setPublisher($publisher);

    /**
     * @return mixed
     */
    public function getToken();

    /**
     * @param mixed $token
     */
    public function setToken($token);


    /**
     * @return mixed
     */
    public function getOptimizationIntegrations();

    /**
     * @param mixed $optimizationIntegrations
     * @return self
     */
    public function setOptimizationIntegrations($optimizationIntegrations);

    /**
     * @return mixed
     */
    public function getLearners();

    /**
     * @param mixed $learners
     */
    public function setLearners($learners);

    /**
     * @return boolean
     */
    public function isFinishLoading();

    /**
     * @param boolean $finishLoading
     * @return self
     */
    public function setFinishLoading($finishLoading);

    /**
     * @return mixed
     */
    public function getAlerts();

    /**
     * @param mixed $alerts
     * @return self
     */
    public function setAlerts($alerts);

    /**
     * @return mixed
     */
    public function getLastTrainingDataCheckSum();

    /**
     * @param $lastTrainingDataCheckSum
     * @return mixed
     */
    public function setLastTrainingDataCheckSum($lastTrainingDataCheckSum);

    /**
     * @return mixed
     */
    public function getRuleCheckSum();

    /**
     * @param mixed $ruleCheckSum
     */
    public function setRuleCheckSum($ruleCheckSum);
}