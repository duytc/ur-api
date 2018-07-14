<?php

namespace UR\Service\Alert\DataSet;

use UR\Model\Core\DataSetInterface;

class DataSetAlertFactory
{
    /**
     * @param $alertType
     * @param $alertCode
     * @param DataSetInterface $dataSet
     * @return null|DataReceivedAlert|WrongFormatAlert
     */
    public function getAlert($alertType, $alertCode, DataSetInterface $dataSet)
    {
        return $this->getDataAugmentedDataSetChangeAlert($alertType, $alertCode , $dataSet);
    }

    /**
     * @param $alertType
     * @param $alertCode
     * @param $dataSet
     * @return null|DataAugmentedDataSetChangeAlert
     */
    private function getDataAugmentedDataSetChangeAlert($alertType, $alertCode, DataSetInterface $dataSet)
    {
        return new DataAugmentedDataSetChangeAlert(
            $alertType,
            $alertCode,
            $dataSet
        );
    }
}