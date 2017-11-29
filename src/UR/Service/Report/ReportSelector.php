<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManagerInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\SqlUtilTrait;

class ReportSelector implements ReportSelectorInterface
{
    use SqlUtilTrait;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var  SqlBuilderInterface
     */
    protected $sqlBuilder;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * ReportSelector constructor.
     * @param EntityManagerInterface $em
     * @param SqlBuilderInterface $sqlBuilder
     */
    public function __construct(EntityManagerInterface $em, SqlBuilderInterface $sqlBuilder)
    {
        $this->sqlBuilder = $sqlBuilder;
        $this->em = $em;

        $this->connection = $this->em->getConnection();
    }

    /**
     * @param ParamsInterface $params
     * @param $overridingFilters
     * @return Statement
     */
    public function getReportData(ParamsInterface $params, $overridingFilters = null)
    {
        $dataSets = $params->getDataSets();

        if (count($dataSets) < 2) {
            return $this->sqlBuilder->buildQueryForSingleDataSet($params, $overridingFilters);
        }

        return $this->sqlBuilder->buildQuery($params, $overridingFilters);
    }

    /**
     * @param ParamsInterface $params
     * @param $overridingFilters
     * @return Statement
     */
    public function getTemporarySQL(ParamsInterface $params, $overridingFilters = null)
    {
        $dataSets = $params->getDataSets();

        if (count($dataSets) < 2) {
            return $this->sqlBuilder->buildSQLForSingleDataSet($params, $overridingFilters);
        }

        return $this->sqlBuilder->buildSQLForMultiDataSets($params, $overridingFilters);
    }

    /**
     * @param ParamsInterface $params
     * @param $overridingFilters
     * @return Statement
     */
    public function getFullSQL(ParamsInterface $params, $overridingFilters = null)
    {
        $temporarySQL = $this->getTemporarySQL($params, $overridingFilters);
        $queryBuilder = $this->sqlBuilder->createReturnSQl($params);

        return sprintf("%s %s;", $temporarySQL, $queryBuilder->getSQL());
    }

    /**
     * @param ParamsInterface $params
     * @param $overridingFilters
     * @param $preCalculateTable
     * @return Statement
     */
    public function getFullSQLForPreCalculateTable(ParamsInterface $params, $preCalculateTable, $overridingFilters = null)
    {
        $queryBuilder = $this->sqlBuilder->createReturnSQlForPreCalculateTable($params, $overridingFilters, $preCalculateTable);

        return $queryBuilder->getSQL();
    }
}