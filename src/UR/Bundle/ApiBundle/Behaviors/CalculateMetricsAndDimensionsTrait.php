<?php


namespace UR\Bundle\ApiBundle\Behaviors;


use UR\Behaviors\JoinConfigUtilTrait;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\ReportViews\ReportViewInterface as ReportViewDTO;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\StringUtilTrait;

trait CalculateMetricsAndDimensionsTrait
{
    use StringUtilTrait;
    use JoinConfigUtilTrait;

    protected function getMetricsAndDimensionsForMultiView(ParamsInterface $params)
    {
        $metrics = [];
        $dimensions = [];
        $reportViews = $params->getReportViews();
        /**
         * @var ReportViewDTO $reportView
         */
        foreach ($reportViews as $reportView) {
            $metrics = array_merge($metrics, $reportView->getMetrics());
            $dimensions = array_merge($dimensions, $reportView->getDimensions());
        }

        $transforms = $params->getTransforms();
        /**
         * @var TransformInterface $transform
         * @var ReportViewInterface $entity
         */
        foreach ($transforms as $transform) {
            $transform->getMetricsAndDimensions($metrics, $dimensions);
        }

        return array(
            $this->getMetricsKey() => $metrics,
            $this->getDimensionsKey() => $dimensions
        );
    }

    protected function getMetricsAndDimensionsForSingleView(ParamsInterface $params)
    {
        $metrics = [];
        $dimensions = [];
        $dataSets = $params->getDataSets();
        $joinBy = $params->getJoinConfigs();
        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $alias = $this->getAliasForField($dataSet->getDataSetId(), $item, $joinBy);
                if ($alias === null) {
                    continue;
                }

                if (!in_array($alias, $metrics)) {
                    $metrics[] = $alias;
                }
            }

            foreach ($dataSet->getDimensions() as $item) {
                $alias = $this->getAliasForField($dataSet->getDataSetId(), $item, $joinBy);
                if ($alias === null) {
                    continue;
                }
                if (!in_array($alias, $dimensions)) {
                    $dimensions[] = $alias;
                }
            }
        }

        $transforms = $params->getTransforms();
        /**
         * @var TransformInterface $transform
         * @var ReportViewInterface $entity
         */
        foreach ($transforms as $transform) {
            $transform->getMetricsAndDimensions($metrics, $dimensions);
        }

        return array(
            $this->getMetricsKey() => array_values($metrics),
            $this->getDimensionsKey() => array_values($dimensions)
        );
    }

    protected abstract function getMetricsKey();
    protected abstract function getDimensionsKey();
}