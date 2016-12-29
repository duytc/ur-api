<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;

interface SqlBuilderInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @param $overridingFilters
     * @return Statement
     */
    public function buildQueryForSingleDataSet(DataSetInterface $dataSet, $overridingFilters = null);

    /**
     * @param array $dataSets
     * @param $joinedField
     * @param $overridingFilters
     * @return Statement
     */
    public function buildQuery(array $dataSets, $joinedField, $overridingFilters = null);
}