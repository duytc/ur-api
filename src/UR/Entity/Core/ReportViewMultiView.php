<?php


namespace UR\Entity\Core;

use UR\Model\Core\ReportViewMultiView as ReportViewMultiViewModel;

class ReportViewMultiView extends ReportViewMultiViewModel
{
    protected $id;
    protected $reportView;
    protected $filters;
    protected $subView;
    protected $dimensions;
    protected $metrics;
}