<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;

interface SqlBuilderInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @return Statement
     */
    public function buildQueryForSingleDataSet(DataSetInterface $dataSet);

    /**
     * @param array $dataSets
     * @param $joinedField = null
     * @return Statement
     */
    public function buildQuery(array $dataSets, $joinedField = null);
}