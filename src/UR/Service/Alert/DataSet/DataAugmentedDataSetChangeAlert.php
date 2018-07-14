<?php

namespace UR\Service\Alert\DataSet;


use UR\Model\Core\DataSetInterface;

class DataAugmentedDataSetChangeAlert extends AbstractDataSetAlert
{
    /**
     * DataAugmentedDataSetChangeAlert constructor.
     * @param $alertType
     * @param $alertCode
     * @param DataSetInterface $dataSet
     */
    public function __construct($alertType, $alertCode, DataSetInterface $dataSet)
    {
        parent::__construct($alertType, $alertCode, $dataSet);
    }

    /**
     * @inheritdoc
     */
    public function getDetails()
    {
        return [
            self::MESSAGE_NOTICE_PUBLISHER => 'Detected some DataSet Augmented mapped that its data was changed, please reload this DataSet again',
            self::DATA_SET_ID => $this->dataSet->getId(),
            self::DATA_SET_NAME => $this->dataSet->getName()
        ];
    }
}