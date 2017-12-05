<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use UR\Domain\DTO\Report\DataSets\DataSetInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\PublicSimpleException;

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

    /**
     * @param ParamsInterface $params
     * @param array $dataSets
     * @param array $joinConfig
     * @param array $transforms
     * @param array $searches
     * @param null $showInTotal
     * @param null $overridingFilters
     * @return mixed
     */
    public function buildGroupQuery(ParamsInterface $params, array $dataSets, array $joinConfig, $transforms = [], $searches = [], $showInTotal = null, $overridingFilters = null);

    /**
     * @param ParamsInterface $params
     * @param DataSetInterface $dataSet
     * @param array $transforms
     * @param array $searches
     * @param null $showInTotal
     * @param null $overridingFilters
     * @return mixed
     */
    public function buildGroupQueryForSingleDataSet(ParamsInterface $params, DataSetInterface $dataSet, $transforms = [], $searches = [], $showInTotal = null, $overridingFilters = null);

    /**
     * @param ParamsInterface $params
     * @param array $overridingFilters
     * @return mixed
     * @throws PublicSimpleException
     */
    public function buildSQLForMultiDataSets(ParamsInterface $params, $overridingFilters = []);

    /**
     * @param ParamsInterface $params
     * @param array $overridingFilters
     * @return mixed
     */
    public function buildSQLForSingleDataSet(ParamsInterface $params, $overridingFilters = []);

    /**
     * @param ParamsInterface $params
     * @return QueryBuilder
     */
    public function createReturnSQl(ParamsInterface $params);

    /**
     * @param ParamsInterface $params
     * @param $overridingFilters
     * @param $preCalculateTable
     * @return QueryBuilder
     */
    public function createReturnSQlForPreCalculateTable(ParamsInterface $params, $overridingFilters, $preCalculateTable);

    /**
     * @param ParamsInterface $params
     * @return mixed
     */
    public function removeTemporaryTables(ParamsInterface $params);

    /**
     * @param ParamsInterface $params
     * @param $preCalculateTable
     * @return mixed
     */
    public function buildSQLForPreCalculateTable(ParamsInterface $params, $preCalculateTable);

    /**
     * @param ParamsInterface $params
     * @param $preCalculateTable
     * @return mixed
     */
    public function buildIndexSQLForPreCalculateTable(ParamsInterface $params, $preCalculateTable);
}