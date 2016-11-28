<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Service\DTO\Collection;

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
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();

        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $metrics[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }

            foreach ($dataSet->getDimensions() as $item) {
                $dimensions[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }
        }

        $statement = $this->reportSelector->getReportData($params);
        $collection = new Collection(array_merge($metrics, $dimensions), $statement->fetchAll());

        $transforms = $params->getTransforms();
        // sort transform by priority
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

        return $this->reportGrouper->group($collection, $metrics, $params->getWeightedCalculations());
    }
}