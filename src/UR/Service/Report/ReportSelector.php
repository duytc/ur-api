<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\DataSetInterface;
use UR\Domain\DTO\Report\DataSets\DataSetInterface as DataSetDTO;

class ReportSelector implements ReportSelectorInterface
{
    const DATA_SET_TABLE_NAME_TEMPLATE = '__data_set_%d';

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

    public function getReportData(ParamsInterface $params)
    {
        $dataSets = $params->getDataSets();

        $reports = [];
        /**
         * @var DataSetDTO $dataSet
         */
        foreach($dataSets as $dataSet) {
            $result = $this->getData($dataSet, [], $filters);

            if (is_array($result)) {
                $reports[] = $result;
            }
        }

        return $reports;
    }

    public function getData(DataSetDTO $dataSet, array $fields, array $filters)
    {
        $table = $this->getDataSetTableSchema($dataSet->getDataSetId());
        $query = $this->sqlBuilder->buildSelectQuery($table, $fields, $filters);

        return $this->connection->query($query);
    }


    /**
     * @param $dataSetId
     * @return Table
     */
    protected function getDataSetTableSchema($dataSetId)
    {
        $sm = $this->connection->getSchemaManager();
        $tableName = sprintf(self::DATA_SET_TABLE_NAME_TEMPLATE, $dataSetId);

        return $sm->listTableDetails($tableName);
    }
}