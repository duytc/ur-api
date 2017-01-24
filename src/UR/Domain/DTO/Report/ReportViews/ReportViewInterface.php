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


	public function setFilters($filters);

    /**
     * @return array
     */
    public function getMetrics();

    /**
     * @return array
     */
    public function getDimensions();
}