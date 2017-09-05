<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManagerInterface;
use UR\Domain\DTO\Report\ParamsInterface;

class ReportSelector implements ReportSelectorInterface
{
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
}