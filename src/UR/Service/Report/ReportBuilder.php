<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;

class ReportBuilder implements ReportBuilderInterface
{
    /**
     * @var ReportSelectorInterface
     */
    protected $reportSelector;

    /**
     * @var ReportGrouperInterface
     */
    protected $reportGrouper;

    /**
     * ReportBuilder constructor.
     * @param ReportSelectorInterface $reportSelector
     * @param ReportGrouperInterface $reportGrouper
     */
    public function __construct(ReportSelectorInterface $reportSelector, ReportGrouperInterface $reportGrouper)
    {
        $this->reportSelector = $reportSelector;
        $this->reportGrouper = $reportGrouper;
    }

    public function getReport(ParamsInterface $params)
    {
        $reportData = $this->reportSelector->getReportData($params);

        if (!$params->needToGroup()) {
            return $reportData;
        }

        return $this->reportGrouper->groupReports($params->getTransforms(), $reportData, [], []);
    }
}