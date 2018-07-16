<?php


namespace UR\Service;


use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;

trait ColumnUtilTrait
{
    public function convertColumn($column, $isShowDataSetName)
    {
        $lastOccur = strrchr($column, "_");
        $dataSetId = str_replace("_", "", $lastOccur);
        if (!preg_match('/^[0-9]+$/', $dataSetId)) {
            return ucwords(str_replace("_", " ", $column));
        }
        $column = str_replace($lastOccur, "", $column);
        $dataSetId = filter_var($dataSetId, FILTER_VALIDATE_INT);
        $column = ucwords(str_replace("_", " ", $column));

        if (!$isShowDataSetName) {
            return $column;
        }

        $dataSet = $this->getDataSetManager()->find($dataSetId);

        if (!$dataSet instanceof DataSetInterface) {
            return sprintf('%s %d', $column, $dataSetId);
        }

        return sprintf("%s (%s)", $column, $dataSet->getName());
    }

    public function convertColumnForDataSet($column, $isShowDataSetName)
    {
        if (!$isShowDataSetName) {
            return ucwords(str_replace("_", " ", $column));
        }

        $lastOccur = strrchr($column, "_");
        $dataSetId = str_replace("_", "", $lastOccur);
        if (!preg_match('/^[0-9]+$/', $dataSetId)) {
            return ucwords(str_replace("_", " ", $column));
        }
        $column = str_replace($lastOccur, "", $column);
        $dataSetId = filter_var($dataSetId, FILTER_VALIDATE_INT);
        $column = ucwords(str_replace("_", " ", $column));

        if (!$isShowDataSetName) {
            return $column;
        }

        $dataSet = $this->getDataSetManager()->find($dataSetId);

        if (!$dataSet instanceof DataSetInterface) {
            return sprintf('%s %d', $column, $dataSetId);
        }

        return sprintf("%s (%s)", $column, $dataSet->getName());
    }

    /**
     * @param ParamsInterface $params
     * @return array
     */
    public function getSameDimensionsMetricsFromDataSets(ParamsInterface $params)
    {
        /** @var DataSet[] $dataSetDTOs */
        $dataSetDTOs = $params->getDataSets();
        if (!is_array($dataSetDTOs) || count($dataSetDTOs) < 2) {
            return [];
        }

        $sameDimensionsMetrics = [];
        $allDimensionsMetrics = [];

        foreach ($dataSetDTOs as $dataSetDTO) {
            $dataSetId = $dataSetDTO->getDataSetId();
            $dataSet = $this->getDataSetManager()->find($dataSetId);
            if (!$dataSet instanceof DataSetInterface) {
                return [];
            }

            $dimensions = array_keys($dataSet->getDimensions());
            $metrics = array_keys($dataSet->getMetrics());
            $allDimensionsMetricsOfOneDataSet = array_merge($dimensions, $metrics);

            foreach ($allDimensionsMetricsOfOneDataSet as $dimensionOrMetric) {
                if (in_array($dimensionOrMetric, $allDimensionsMetrics)) {
                    $sameDimensionsMetrics[] = $dimensionOrMetric;
                }
            }

            $allDimensionsMetrics = array_merge($allDimensionsMetrics, $allDimensionsMetricsOfOneDataSet);
        }

        return array_values(array_unique($sameDimensionsMetrics));
    }

    /**
     * @param DataSetInterface[] $dataSets
     * @return array
     */
    public function getSameDimensionsMetricsFromDataSetEntities(array $dataSets)
    {
        $sameDimensionsMetrics = [];
        $allDimensionsMetrics = [];

        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                return [];
            }

            $dimensions = array_keys($dataSet->getDimensions());
            $metrics = array_keys($dataSet->getMetrics());
            $allDimensionsMetricsOfOneDataSet = array_merge($dimensions, $metrics);

            foreach ($allDimensionsMetricsOfOneDataSet as $dimensionOrMetric) {
                if (in_array($dimensionOrMetric, $allDimensionsMetrics)) {
                    $sameDimensionsMetrics[] = $dimensionOrMetric;
                }
            }

            $allDimensionsMetrics = array_merge($allDimensionsMetrics, $allDimensionsMetricsOfOneDataSet);
        }

        return array_values(array_unique($sameDimensionsMetrics));
    }

    /**
     * @return DataSetManagerInterface
     */
    protected abstract function getDataSetManager();
}