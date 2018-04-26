<?php


namespace UR\Model\Core;


class OptimizationRule implements OptimizationRuleInterface
{
    protected $id;
    protected $name;
    protected $dateField;
    protected $dateRange;
    protected $identifierFields;
    protected $optimizeFields;
    protected $segmentFields;
    protected $token;
    protected $createdDate;
    protected $reportView;
    protected $publisher;
    protected $optimizationIntegrations;
    protected $learners;
    /** @var  boolean */
    protected $finishLoading;

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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getDateField()
    {
        return $this->dateField;
    }

    /**
     * @inheritdoc
     */
    public function setDateField($dateField)
    {
        $this->dateField = $dateField;
    }

    /**
     * @inheritdoc
     */
    public function getDateRange()
    {
        return $this->dateRange;
    }

    /**
     * @inheritdoc
     */
    public function setDateRange($dateRange)
    {
        $this->dateRange = $dateRange;
    }

    /**
     * @inheritdoc
     */
    public function getIdentifierFields()
    {
        return $this->identifierFields;
    }

    /**
     * @inheritdoc
     */
    public function setIdentifierFields($identifierFields)
    {
        $this->identifierFields = $identifierFields;
    }

    /**
     * @inheritdoc
     */
    public function getOptimizeFields()
    {
        return $this->optimizeFields;
    }

    /**
     * @inheritdoc
     */
    public function setOptimizeFields($optimizeFields)
    {
        $this->optimizeFields = $optimizeFields;
    }

    /**
     * @inheritdoc
     */
    public function getSegmentFields()
    {
        return $this->segmentFields;
    }

    /**
     * @inheritdoc
     */
    public function setSegmentFields($segmentFields)
    {
        $this->segmentFields = $segmentFields;
    }

    /**
     * @inheritdoc
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @inheritdoc
     */
    public function setToken($token)
    {
        $this->token = $token;

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
    public function getReportView()
    {
        return $this->reportView;
    }

    /**
     * @inheritdoc
     */
    public function setReportView($reportView)
    {
        $this->reportView = $reportView;
    }

    /**
     * @inheritdoc
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @inheritdoc
     */
    public function setPublisher($publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * @return mixed
     */
    public function getOptimizationIntegrations()
    {
        return $this->optimizationIntegrations;
    }

    /**
     * @param mixed $optimizationIntegrations
     * @return self
     */
    public function setOptimizationIntegrations($optimizationIntegrations)
    {
        $this->optimizationIntegrations = $optimizationIntegrations;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLearners()
    {
        return $this->learners;
    }

    /**
     * @param mixed $learners
     */
    public function setLearners($learners)
    {
        $this->learners = $learners;
    }

    /**
     * @inheritdoc
     */
    public function isFinishLoading()
    {
        return $this->finishLoading;
    }

    /**
     * @inheritdoc
     */
    public function setFinishLoading($finishLoading)
    {
        $this->finishLoading = $finishLoading;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAlerts()
    {
        return $this->alerts;
    }

    /**
     * @inheritdoc
     */
    public function setAlerts($alerts)
    {
        $this->alerts = $alerts;

        return $this;
    }
}