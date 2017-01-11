<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface ReportViewMultiViewInterface extends ModelInterface
{
    /**
     * @return ReportViewInterface
     */
    public function getReportView();

    /**
     * @param ReportViewInterface $reportView
     * @return self
     */
    public function setReportView($reportView);

    /**
     * @return array
     */
    public function getFilters();

    /**
     * @param array $filters
     * @return self
     */
    public function setFilters($filters);

    /**
     * @return ReportViewInterface
     */
    public function getSubView();

    /**
     * @param ReportViewInterface $subView
     * @return self
     */
    public function setSubView($subView);

    /**
     * @return array
     */
    public function getDimensions();

    /**
     * @param array $dimensions
     * @return self
     */
    public function setDimensions($dimensions);

    /**
     * @return array
     */
    public function getMetrics();

    /**
     * @param array $metrics
     * @return self
     */
    public function setMetrics($metrics);
}