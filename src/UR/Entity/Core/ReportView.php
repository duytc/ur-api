<?php


namespace UR\Entity\Core;

use UR\Model\Core\ReportView as ReportViewModel;
class ReportView extends ReportViewModel
{
    protected $id;
    protected $dataSets;
    protected $joinBy;
    protected $name;
    protected $transforms;
    protected $createdDate;
    protected $weightedCalculations;
    protected $publisher;
    protected $dimensions;
    protected $metrics;
    protected $reportViews;
    protected $filters;
    protected $multiView;

}