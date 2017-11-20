<?php


namespace UR\Entity\Core;

use \UR\Model\Core\AutoOptimizationConfigDataSet  as AutoOptimizationConfigDataSetModel;

class AutoOptimizationConfigDataSet extends AutoOptimizationConfigDataSetModel
{
    protected $id;
    protected $filters;
    protected $dimensions;
    protected $metrics;
    protected $autoOptimizationConfig;
    protected $dataSet;

    /**
     * AutoOptimizationConfigDataSet constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }


}