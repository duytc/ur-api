<?php


namespace UR\Entity\Core;

use UR\Model\Core\ReportView as ReportViewModel;

class ReportView extends ReportViewModel
{
    protected $id;
//    protected $dataSets;
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
//    protected $reportViews;
//    protected $filters;
    protected $multiView;
    protected $formats;
    protected $fieldTypes;
    protected $publisher;
    protected $subReportsIncluded;
    protected $reportViewDataSets;
    protected $reportViewMultiViews;

    /**
     * @inheritdoc
     */
    public function __construct()
    {
        parent::__construct();
    }
}