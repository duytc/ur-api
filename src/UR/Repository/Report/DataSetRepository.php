<?php


namespace UR\Repository\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use UR\Model\Core\DataSetInterface;

class DataSetRepository implements DataSetRepositoryInterface
{
    const DATA_SET_TABLE_NAME_TEMPLATE = '__data_set_%d';
    /**
     * @var Connection
     */
    protected $conn;


    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function getData(DataSetInterface $dataSet, array $filters)
    {
        // TODO: Implement getData() method.
    }


    /**
     * @param DataSetInterface $dataSet
     * @return Table
     */
    protected function getDataSetTableSchema(DataSetInterface $dataSet)
    {
        $sm = $this->conn->getSchemaManager();
        $tableName = sprintf(self::DATA_SET_TABLE_NAME_TEMPLATE, $dataSet->getId());

        return $sm->listTableDetails($tableName);
    }
}