<?php


namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\DataSetInterface;

interface AutoOptimizationConfigDataSetRepositoryInterface extends ObjectRepository
{
    /**
     * @param DataSetInterface $dataSet
     * @return mixed
     */
    public function findByDataSet(DataSetInterface $dataSet);
}