<?php


namespace UR\Entity\Core;

use \UR\Model\Core\AutoOptimizationConfig as AutoOptimizationConfigModel;

class AutoOptimizationConfig extends AutoOptimizationConfigModel
{
    protected $id;
    protected $name;
    protected $transforms;
    protected $filters;
    protected $metrics;
    protected $dimensions;
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
    protected $identifiers;
    protected $positiveFactors;
    protected $negativeFactors;
    protected $token;
    protected $learners;

    /**
     * AutoOptimizationConfig constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}