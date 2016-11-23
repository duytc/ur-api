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
     * @param ReportSelectorInterface $reportSelector
     */
    public function __construct(ReportSelectorInterface $reportSelector)
    {
        $this->reportSelector = $reportSelector;
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
                    if ($item == $params->getJoinByFields()) {
                        $dimensions[] = $item;
                        continue;
                    }

                    $dimensions[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
                }
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

        return $collection->getRows();
    }
}