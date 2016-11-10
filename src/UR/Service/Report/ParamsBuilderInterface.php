<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\ReportViewInterface;

interface ParamsBuilderInterface
{

    /**
     * @return mixed
     */
    public function getDataSet();

    /**
     * @param array $params
     * @return ParamsInterface
     */
    public function buildFromArray(array $params);

    /**
     * @param ReportViewInterface $reportView
     * @return ParamsInterface
     */
    public function buildFromReportView(ReportViewInterface $reportView);

    /**
     * @param $dataSetId
     * @return mixed
     */
    public function getFiltersByDataSet($dataSetId);

    /**
     * @param $dataSetId
     * @return mixed
     */
    public function getMetricsByDataSet($dataSetId);

    /**
     * @param $dataSet
     * @return mixed
     */
    public function getDimensionByDataSet($dataSet);

    /**
     * @return mixed
     */
    public function getJoinByFields();

    /**
     * @return mixed
     */
    public function getTransformations();

}