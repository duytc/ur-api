<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface ReportViewDataSetInterface extends ModelInterface
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
     * @return DataSetInterface
     */
    public function getDataSet();

    /**
     * @param DataSetInterface $dataSet
     * @return self
     */
    public function setDataSet($dataSet);

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
     * @return mixed
     */
    public function getMetrics();

    /**
     * @param mixed $metrics
     * @return self
     */
    public function setMetrics($metrics);

    /**
     * @param $id
     */
    public function setId($id);
}