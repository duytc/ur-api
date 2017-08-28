<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;

interface SqlBuilderInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @param null $page
     * @param null $limit
     * @param array $transforms
     * @param array $searches
     * @param $sortField
     * @param $sortDirection
     * @param $overridingFilters
     * @return Statement
     */
    public function buildQueryForSingleDataSet(DataSetInterface $dataSet, $page = null, $limit = null, $transforms = [], $searches = [], $sortField = null, $sortDirection = null, $overridingFilters = null);

    /**
     * @param array $dataSets
     * @param array $joinConfig
     * @param $page
     * @param $limit
     * @param $transforms
     * @param $searches
     * @param $sortField
     * @param $sortDirection
     * @param $overridingFilters
     * @return Statement
     */
    public function buildQuery(array $dataSets, array $joinConfig, $page = null, $limit = null, $transforms = [], $searches = [], $sortField = null, $sortDirection = null, $overridingFilters = null);

    public function buildGroupQuery($subQuery, array $dataSets, array $joinConfig, $transforms = [], $searches = [], $showInTotal = null, $overridingFilters = null);

    public function buildGroupQueryForSingleDataSet($subQuery, DataSetInterface $dataSet, $transforms = [], $searches = [], $showInTotal = null, $overridingFilters = null);
}