<?php


namespace UR\Entity\Core;

use UR\Model\Core\ReportViewDataSet as ReportViewDataSetModel;

class ReportViewDataSet extends ReportViewDataSetModel
{
    protected $id;
    protected $reportView;
    protected $filters;
    protected $dimensions;
    protected $metrics;
    protected $dataSet;
    protected $lastActivity;
}