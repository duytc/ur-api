<?php


namespace UR\Model\Core;


use UR\Behaviors\AutoOptimizationUtilTrait;

class AutoOptimizationConfig implements AutoOptimizationConfigInterface
{
    use AutoOptimizationUtilTrait;

    protected $id;
    protected $transforms;
    protected $filters;
    protected $metrics;
    protected $dimensions;
    protected $name;
    protected $fieldTypes;
    protected $joinBy;
    protected $factors;
    protected $objective;
    protected $expectedObjective;
    protected $dateRange;
    protected $active;
    protected $createdDate;
    protected $publisher;
    protected $autoOptimizationConfigDataSets;
    protected $token;

    /** @var  array */
    protected $identifiers;

    /** @var  array */
    protected $identifierObjects;

    /** @var  array */
    protected $positiveFactors;

    /** @var  array */
    protected $negativeFactors;

    /** @var  LearnerInterface */
    protected $learners;

    public function __construct()
    {
        $this->transforms = [];
        $this->filters = [];
        $this->metrics = [];
        $this->dimensions = [];
        $this->fieldTypes = [];
        $this->joinBy = [];
        $this->identifiers = [];
    }

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
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTransforms()
    {
        return $this->transforms;
    }

    /**
     * @inheritdoc
     */
    public function setTransforms($transforms)
    {
        $this->transforms = $transforms;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @inheritdoc
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * @inheritdoc
     */
    public function setMetrics($metrics)
    {
        $this->metrics = $metrics;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * @inheritdoc
     */
    public function setDimensions($dimensions)
    {
        $this->dimensions = $dimensions;

        return $this;
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

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFieldTypes()
    {
        return $this->fieldTypes;
    }

    /**
     * @inheritdoc
     */
    public function setFieldTypes($fieldTypes)
    {
        $this->fieldTypes = $fieldTypes;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getJoinBy()
    {
        return $this->joinBy;
    }

    /**
     * @inheritdoc
     */
    public function setJoinBy($joinBy)
    {
        $this->joinBy = $joinBy;

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

        return $this;
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

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAutoOptimizationConfigDataSets()
    {
        return $this->autoOptimizationConfigDataSets;
    }

    /**
     * @inheritdoc
     */
    public function setAutoOptimizationConfigDataSets($autoOptimizationConfigDataSets)
    {
        $this->autoOptimizationConfigDataSets = $autoOptimizationConfigDataSets;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFactors()
    {
        return $this->factors;
    }

    /**
     * @inheritdoc
     */
    public function setFactors($factors)
    {
        $this->factors = $factors;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getObjective()
    {
        return $this->objective;
    }

    /**
     * @inheritdoc
     */
    public function setObjective($objective)
    {
        $this->objective = $objective;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getExpectedObjective()
    {
        return $this->expectedObjective;
    }

    /**
     * @inheritdoc
     */
    public function setExpectedObjective($expectedObjective)
    {
        $this->expectedObjective = $expectedObjective;

        return $this;
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
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @inheritdoc
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIdentifierObjects()
    {
        if (empty($this->identifierObjects) && is_array($this->getIdentifiers())) {
            $this->identifierObjects = $this->createIdentifierObjectsFromJsonArray($this->getIdentifiers());
        }

        return $this->identifierObjects;
    }

    /**
     * @inheritdoc
     */
    public function getIdentifiers()
    {
        return $this->identifiers;
    }

    /**
     * @inheritdoc
     */
    public function setIdentifiers($identifiers)
    {
        $this->identifiers = $identifiers;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPositiveFactors()
    {
        return $this->positiveFactors;
    }

    /**
     * @inheritdoc
     */
    public function setPositiveFactors($positiveFactors)
    {
        $this->positiveFactors = $positiveFactors;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNegativeFactors()
    {
        return $this->negativeFactors;
    }

    /**
     * @inheritdoc
     */
    public function setNegativeFactors($negativeFactors)
    {
        $this->negativeFactors = $negativeFactors;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLearners()
    {
        return $this->learners;
    }

    /**
     * @inheritdoc
     */
    public function setLearners($learners)
    {
        $this->learners = $learners;

        return $this;
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
}