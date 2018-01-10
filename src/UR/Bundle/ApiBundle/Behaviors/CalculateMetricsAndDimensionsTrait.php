<?php


namespace UR\Bundle\ApiBundle\Behaviors;


use UR\Behaviors\JoinConfigUtilTrait;
use UR\Domain\DTO\Report\DataSets\DataSet;
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
//            $dimensions = array_merge($dimensions, $reportView->getDimensions());
        }

//        $transforms = $params->getTransforms();
        /**
         * @var TransformInterface $transform
         * @var ReportViewInterface $entity
         */
//        foreach ($transforms as $transform) {
//            $transform->getMetricsAndDimensions($metrics, $dimensions);
//        }

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

        $dataSetDimensions = [];
        $dataSetMetrics = [];

        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSet) {
                continue;
            }
            $dataSetId = $dataSet->getDataSetId();

            $subDimensions = array_map(function ($field) use ($dataSetId) {
                return sprintf("%s_%s", $field, $dataSetId);
            }, $dataSet->getDimensions());

            $subMetrics = array_map(function ($field) use ($dataSetId) {
                return sprintf("%s_%s", $field, $dataSetId);
            }, $dataSet->getMetrics());

            $dataSetDimensions = array_merge($dataSetDimensions, $subDimensions);
            $dataSetMetrics = array_merge($dataSetMetrics, $subMetrics);
        }

        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $isDimension = false;
                $alias = $this->getAliasForUpdateField($dataSet->getDataSetId(), $item, $joinBy, $dataSetMetrics, $isDimension);

                if (!in_array($alias, $metrics) && !$isDimension) {
                    $metrics[] = $alias;
                    continue;
                }

                if (!in_array($alias, $dimensions) && $isDimension) {
                    $dimensions[] = $alias;
                    continue;
                }
            }

            foreach ($dataSet->getDimensions() as $item) {
                $isDimension = true;
                $alias = $this->getAliasForUpdateField($dataSet->getDataSetId(), $item, $joinBy, $dataSetMetrics, $isDimension);

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

    /**
     * @param ParamsInterface $params
     * @return array
     */
    protected function getMetricsAndDimensionsForAutoOptimizationConfig(ParamsInterface $params)
    {
        // same as getMetricsAndDimensionsForSingleView()
        return $this->getMetricsAndDimensionsForSingleView($params);
    }

    protected abstract function getMetricsKey();

    protected abstract function getDimensionsKey();
}