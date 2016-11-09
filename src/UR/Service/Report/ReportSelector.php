<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\DataSetInterface;

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
     * ReportSelector constructor.
     * @param Connection $connection
     * @param SqlBuilderInterface $sqlBuilder
     */
    public function __construct(Connection $connection, SqlBuilderInterface $sqlBuilder)
    {
        $this->connection = $connection;
        $this->sqlBuilder = $sqlBuilder;
    }


    public function getReportData(ParamsInterface $params)
    {
        $dataSets = $params->getDataSets();
        $filters = $params->getFilters();

        $reports = [];
        /**
         * @var DataSetInterface $dataSet
         */
        foreach($dataSets as $dataSet) {
            $result = $this->getData($dataSet, [], $filters);

            if (is_array($result)) {
                $reports[] = $result;
            }
        }

        return $reports;
    }

    public function getData(DataSetInterface $dataSet, array $fields, array $filters)
    {
        $table = $this->getDataSetTableSchema($dataSet);
        $query = $this->sqlBuilder->buildSelectQuery($table, $fields, $filters);

        return $this->connection->query($query);
    }


    /**
     * @param DataSetInterface $dataSet
     * @return Table
     */
    protected function getDataSetTableSchema(DataSetInterface $dataSet)
    {
        $sm = $this->connection->getSchemaManager();
        $tableName = sprintf(self::DATA_SET_TABLE_NAME_TEMPLATE, $dataSet->getId());

        return $sm->listTableDetails($tableName);
    }
}