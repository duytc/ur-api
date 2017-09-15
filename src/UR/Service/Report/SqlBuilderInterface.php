<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\ParamsInterface;

interface SqlBuilderInterface
{
    /**
     * @param ParamsInterface $params
     * @param $overridingFilters
     * @return Statement
     */
    public function buildQueryForSingleDataSet(ParamsInterface $params, $overridingFilters = null);

    /**
     * @param ParamsInterface $params
     * @param $overridingFilters
     * @return Statement
     */
    public function buildQuery(ParamsInterface $params, $overridingFilters = null);

    public function buildGroupQuery($subQuery, array $dataSets, array $joinConfig, $transforms = [], $searches = [], $showInTotal = null, $overridingFilters = null);

    public function buildGroupQueryForSingleDataSet($subQuery, DataSetInterface $dataSet, $transforms = [], $searches = [], $showInTotal = null, $overridingFilters = null);
}