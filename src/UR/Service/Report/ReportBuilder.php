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
     * @var GrouperInterface
     */
    protected $reportGrouper;

    /**
     * ReportBuilder constructor.
     * @param ReportSelectorInterface $reportSelector
     * @param GrouperInterface $reportGrouper
     */
    public function __construct(ReportSelectorInterface $reportSelector, GrouperInterface $reportGrouper)
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

        return $this->reportGrouper->group($params->getTransforms(), $reportData);
    }
}