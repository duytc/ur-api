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
     * @param array|null $showInTotal
     * @return ParamsInterface
     */
    public function buildFromReportView(ReportViewInterface $reportView, $showInTotal = null);

    /**
     * @param ReportViewInterface $reportView
     * @param array $paginationParams
     * @return ParamsInterface
     */
    public function buildFromReportViewForSharedReport(ReportViewInterface $reportView, array $paginationParams);
}