<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
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
     * @param array|null $showInTotal
     * @param ParamsInterface $multiParams
     * @return ParamsInterface
     */
    public function buildFromReportView(ReportViewInterface $reportView, $showInTotal = null, ParamsInterface $multiParams = null);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param ParamsInterface $multiParams
     * @return ParamsInterface
     */
    public function buildFromAutoOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig, ParamsInterface $multiParams = null);

    /**
     * @param ReportViewInterface $reportView
     * @param array $fieldsToBeShared
     * @param array $paginationParams
     * @return ParamsInterface
     */
    public function buildFromReportViewForSharedReport(ReportViewInterface $reportView, array $fieldsToBeShared, array $paginationParams);

    /**
     * @param ReportViewInterface $reportView
     * @param array $data
     * @return ParamsInterface
     */
    public function buildFromReportViewAndParams(ReportViewInterface $reportView, $data);
}