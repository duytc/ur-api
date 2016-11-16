<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSetInterface;

interface ImportHistoryManagerInterface extends ManagerInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getImportedDataByDataSet(DataSetInterface $dataSet);
}