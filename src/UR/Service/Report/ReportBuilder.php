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
        $statement = $this->reportSelector->getReportData($params);

        if (!$params->needToGroup()) {
            return $statement->fetchAll();
        }

        $metrics = [];
        $dimensions = [];

        $dataSets = $params->getDataSets();

        if (count($dataSets) < 2) {
            $metrics = $dataSets[0]->getMetrics();
            $dimensions = $dataSets[0]->getDimensions();
        } else {
            foreach($dataSets as $dataSet) {
                foreach($dataSet->getMetrics() as $item) {
                    $metrics[] = sprintf('%s%d', $item, $dataSet->getDataSetId());
                }

                foreach($dataSet->getDimensions() as $item) {
                    $dimensions[] = sprintf('%s%d', $item, $dataSet->getDataSetId());
                }
            }
        }


        return $this->reportGrouper->groupReports($params->getTransformations(), $statement, $metrics, $dimensions);
    }
}