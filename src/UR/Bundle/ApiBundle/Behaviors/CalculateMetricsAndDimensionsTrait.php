<?php


namespace UR\Bundle\ApiBundle\Behaviors;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\ReportViews\ReportViewInterface as ReportViewDTO;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\StringUtilTrait;

trait CalculateMetricsAndDimensionsTrait
{
    use StringUtilTrait;

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
        $joinBy = $params->getJoinByFields();
        foreach ($dataSets as $dataSet) {
            foreach ($dataSet->getMetrics() as $item) {
                $metrics[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
            }

            foreach ($dataSet->getDimensions() as $item) {
                if ($joinBy === $this->removeIdSuffix($item)) {
                    continue;
                }

                $dimensions[] = sprintf('%s_%d', $item, $dataSet->getDataSetId());
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

        // add joinBy to dimensions/metrics
        $joinByConfig = $params->getJoinByFields();
        if (is_array($joinByConfig) && count($joinByConfig) > 0
            && array_key_exists('joinFields', $joinByConfig[0])
            && array_key_exists('outputField', $joinByConfig[0])
        ) {
            $joinFields = $joinByConfig[0]['joinFields'];
            $outputJoinField = $joinByConfig[0]['outputField'];

            foreach ($dataSets as $dataSet) {
                if (!is_array($joinFields)) {
                    continue;
                }

                // remove joinFields from dimensions/metrics of report view
                foreach ($joinFields as $joinField) {
                    $dataSetId = $joinField['dataSet'];
                    $joinBy = $joinField['field'];
                    $joinByWithDataSetId = sprintf('%s_%d', $joinBy, $dataSetId);

                    if ($dataSetId != $dataSet->getDataSetId()) {
                        continue;
                    }

                    if (in_array($joinByWithDataSetId, $dimensions)) {
                        // remove joinFields from dimensions of report view
                        $foundIdx = array_search($joinByWithDataSetId, $dimensions);
                        if ($foundIdx !== false) {
                            unset($dimensions[$foundIdx]);
                        }

                        // add outputJoinField to dimensions of report view
                        if (!in_array($outputJoinField, $dimensions)) {
                            $dimensions[] = $outputJoinField;
                        }

                        continue;
                    }

                    if (in_array($joinByWithDataSetId, $metrics)) {
                        // remove joinFields from metrics of report view
                        $foundIdx = array_search($joinByWithDataSetId, $metrics);
                        if ($foundIdx !== false) {
                            unset($metrics[$foundIdx]);
                        }

                        // add outputJoinField to metrics of report view
                        if (!in_array($outputJoinField, $metrics)) {
                            $metrics[] = $outputJoinField;
                        }

                        continue;
                    }
                }
            }
        }

        return array(
            $this->getMetricsKey() => array_values($metrics),
            $this->getDimensionsKey() => array_values($dimensions)
        );
    }

    protected abstract function getMetricsKey();
    protected abstract function getDimensionsKey();
}