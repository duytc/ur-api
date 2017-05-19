<?php

namespace UR\Service\Report;

use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\DTO\Report\ReportResultInterface;

interface ReportViewFormatterInterface
{
    /**
     * format reports
     *
     * @param ReportResultInterface $reportResult
     * @param array $formats
     * @param array $metrics
     * @param array $dimensions
     */
    public function formatReports($reportResult, $formats, $metrics, $dimensions);

    /**
     * @param ReportResultInterface $reportResult
     * @param ParamsInterface $params
     * @return mixed
     */
    public function getSmartColumns($reportResult, $params);

    /**
     * @param ReportResultInterface $reportResult
     * @param ParamsInterface $params
     * @return mixed ;
     */
    public function getReportAfterApplyDefaultFormat($reportResult, $params);
}