<?php

namespace UR\Service\LargeReport;


use UR\Model\Core\ReportViewInterface;

interface LargeReportMaintainerInterface
{
    /**
     * Maintain large report view for fast query
     *
     * @param ReportViewInterface $reportView
     * @return mixed
     */
    public function maintainerLargeReport(ReportViewInterface $reportView);
}