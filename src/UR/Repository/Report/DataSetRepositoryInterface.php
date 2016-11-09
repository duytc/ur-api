<?php


namespace UR\Repository\Report;


use UR\Model\Core\DataSetInterface;

interface DataSetRepositoryInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @param array $filters
     * @return array
     */
    public function getData(DataSetInterface $dataSet, array $filters);
}