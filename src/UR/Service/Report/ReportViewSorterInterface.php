<?php

namespace UR\Service\Report;

use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\DTO\Report\ReportResultInterface;

interface ReportViewSorterInterface
{
    /**
     * @param ReportResultInterface $reportResult
     * @param ParamsInterface $params
     * @return ReportResultInterface
     */
    public function sortReports($reportResult, $params);
}