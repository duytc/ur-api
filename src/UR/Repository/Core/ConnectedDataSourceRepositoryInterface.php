<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;

interface ConnectedDataSourceRepositoryInterface extends ObjectRepository
{
    /**
     * @param DataSetInterface $dataSet
     * @return ConnectedDataSourceInterface[]
     */
    public function getConnectedDataSourceByDataSet(DataSetInterface $dataSet);
}