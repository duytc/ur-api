<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Service\DTO\Collection;
use UR\Service\Report\Sorter\SortByInterface;

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
     * @var SortByInterface
     */
    private $sorter;

    /**
     * @param ReportSelectorInterface $reportSelector
     * @param ReportGrouperInterface $reportGrouper
     * @param SortByInterface $sorter
     */
    public function __construct(ReportSelectorInterface $reportSelector, ReportGrouperInterface $reportGrouper, SortByInterface $sorter)
    {
        $this->reportSelector = $reportSelector;
        $this->reportGrouper = $reportGrouper;
        $this->sorter = $sorter;
    }

    public function getReport(ParamsInterface $params)
    {
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();

        if (count($dataSets) < 2) {
            $metrics = $dataSets[0]->getMetrics();
            $dimensions = $dataSets[0]->getDimensions();
        } else {
            foreach ($dataSets as $dataSet) {
                foreach ($dataSet->getMetrics() as $item) {
                    $metrics[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }

                foreach ($dataSet->getDimensions() as $item) {
                    $dimensions[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }
            }
        }

        $statement = $this->reportSelector->getReportData($params);
        $collection = new Collection(array_merge($metrics, $dimensions), $statement->fetchAll());

//      $groupBy = $params->getGroupByTransform();
        $transforms = $params->getTransforms();
        usort($transforms, function(TransformInterface $a, TransformInterface $b){
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }
            return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
        });

        /**
         * @var TransformInterface $transform
         */
        foreach ($transforms as $transform) {
            $transform->transform($collection, $metrics, $dimensions);
        }


       /* if ($groupBy instanceof GroupByTransformInterface) {
            return $this->reportGrouper->groupReports($groupBy, $collection, $metrics, $dimensions);
        }

        $sortByFields = $params->getSortByFields();
        if (!empty($sortByFields)) {
            $this->sorter->sortByFields($sortByFields, $collection, $metrics, $dimensions);
        }*/


        return $collection->getRows();
    }
}