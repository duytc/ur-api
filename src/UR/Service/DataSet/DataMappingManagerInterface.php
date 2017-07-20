<?php


namespace UR\Service\DataSet;


use UR\Model\Core\DataSetInterface;
use UR\Model\PagerParam;

interface DataMappingManagerInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @param PagerParam $param
     * @param array $filters
     * @return mixed
     */
    public function getRows(DataSetInterface $dataSet, PagerParam $param, $filters = []);
}