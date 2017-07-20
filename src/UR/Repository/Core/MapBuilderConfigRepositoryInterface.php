<?php


namespace UR\Repository\Core;
use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\DataSetInterface;

interface MapBuilderConfigRepositoryInterface extends ObjectRepository
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