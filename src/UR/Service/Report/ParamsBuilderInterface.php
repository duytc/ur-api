<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\ReportViewInterface;

interface ParamsBuilderInterface
{
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
     * @return mixed
     */
    public function getFilters();

    /**
     * @return mixed
     */
    public function getJoinBy();

    /**
     * @return mixed
     */
    public function getTransformations();

}