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
     * @param array $joinConfig
     * @param $overridingFilters
     * @return Statement
     */
    public function buildQuery(array $dataSets, array $joinConfig, $overridingFilters = null);
}