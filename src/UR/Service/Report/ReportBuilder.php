<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\SortByTransformInterface;

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
     * @var ReportSorterInterface
     */
    protected $reportSorter;

    /**
     * ReportBuilder constructor.
     * @param ReportSelectorInterface $reportSelector
     * @param ReportGrouperInterface $reportGrouper
     * @param ReportSorterInterface $reportSorter
     */
    public function __construct(ReportSelectorInterface $reportSelector, ReportGrouperInterface $reportGrouper, ReportSorterInterface $reportSorter)
    {
        $this->reportSelector = $reportSelector;
        $this->reportGrouper = $reportGrouper;
        $this->reportSorter = $reportSorter;
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
                    $metrics[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }

                foreach($dataSet->getDimensions() as $item) {
                    $dimensions[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }
            }
        }

        $groupedReports =  $this->reportGrouper->groupReports($groupBy, $statement, $metrics, $dimensions);

        $sortBy = $params->getSortByTransform();

        if ($sortBy instanceof SortByTransformInterface) {
            $this->reportSorter->sort($groupedReports, $sortBy);
        }

        return $groupedReports;
    }
}