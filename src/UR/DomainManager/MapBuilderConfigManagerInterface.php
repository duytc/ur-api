<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSetInterface;

interface MapBuilderConfigManagerInterface extends ManagerInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getByDataSet(DataSetInterface $dataSet);

    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function getByMapDataSet(DataSetInterface $dataSet);
}