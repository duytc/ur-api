<?php


namespace UR\Entity\Core;

use UR\Model\Core\ReportView as ReportViewModel;

class ReportView extends ReportViewModel
{
    protected $id;
    protected $joinBy;
    protected $name;
    protected $transforms;
    protected $createdDate;
    protected $weightedCalculations;
    protected $sharedKeysConfig;
    protected $showInTotal;
    protected $dimensions;
    protected $metrics;
    protected $formats;
    protected $fieldTypes;
    protected $publisher;
    protected $reportViewDataSets;
    protected $isShowDataSetName;
    protected $lastActivity;
    protected $enableCustomDimensionMetric;
    protected $lastRun;
    protected $subView;
    protected $masterReportView;
    protected $subReportViews;
    protected $filters;
    protected $largeReport;
    protected $availableToRun;
    protected $availableToChange;
    protected $preCalculateTable;
    protected $calculatedMetrics;

    /**
     * @inheritdoc
     */
    public function __construct()
    {
        parent::__construct();
    }
}