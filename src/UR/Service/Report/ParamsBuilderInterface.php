<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\OptimizationRuleInterface;
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
     * @param OptimizationRuleInterface $optimizationRule
     * @param ParamsInterface $multiParams
     * @return ParamsInterface
     */
    public function buildFromOptimizationRule(OptimizationRuleInterface $optimizationRule, ParamsInterface $multiParams = null);

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

    /**
     * @param ReportViewInterface $reportView
     * @param array $data
     * @return ParamsInterface
     */
    public function buildFromReportViewAndParamsForDashboard(ReportViewInterface $reportView, $data);
}