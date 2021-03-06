<?php


namespace UR\Service;


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
     * @return DataSetManagerInterface
     */
    protected abstract function getDataSetManager();
}