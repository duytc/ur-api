<?php


namespace UR\Entity\Core;

use UR\Model\Core\ReportView as ReportViewModel;

class ReportView extends ReportViewModel
{
    protected $id;
    protected $joinBy;
    protected $name;
    protected $alias;
    protected $transforms;
    protected $createdDate;
    protected $weightedCalculations;
    protected $sharedKeysConfig;
    protected $showInTotal;
    protected $dimensions;
    protected $metrics;
    protected $multiView;
    protected $formats;
    protected $fieldTypes;
    protected $publisher;
    protected $subReportsIncluded;
    protected $reportViewDataSets;
    protected $reportViewMultiViews;
    protected $isShowDataSetName;
    protected $lastActivity;
    protected $enableCustomDimensionMetric;
    protected $lastRun;

    /**
     * @inheritdoc
     */
    public function __construct()
    {
        parent::__construct();
    }
}