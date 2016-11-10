<?php


namespace UR\Service\Report;


interface ReportGrouperInterface
{
    /**
     * @param array $report
     * @return array
     */
    public function groupReports(array $report);
}