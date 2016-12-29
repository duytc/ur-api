<?php


namespace UR\Domain\DTO\Report\ReportViews;


interface ReportViewInterface
{
    /**
     * @return int
     */
    public function getReportViewId();

    /**
     * @return array
     */
    public function getFilters();

    /**
     * @return array
     */
    public function getMetrics();

    /**
     * @return array
     */
    public function getDimensions();
}