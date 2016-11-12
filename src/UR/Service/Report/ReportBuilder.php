<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\AbstractTransformInterface;
use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;

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
        $groupBy = $params->getGroupByTransform();

        if (!$groupBy) {
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

        return $this->reportGrouper->groupReports($groupBy, $statement, $metrics, $dimensions);
    }
}