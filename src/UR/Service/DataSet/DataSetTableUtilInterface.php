<?php


namespace UR\Service\DataSet;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;

interface DataSetTableUtilInterface
{
    /**
     * @param DataSetInterface $dataSet
     */
    public function updateIndexes(DataSetInterface $dataSet);
}