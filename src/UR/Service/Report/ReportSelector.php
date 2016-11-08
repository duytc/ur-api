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
    protected $conn;


    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    /**
     * @param DataSetInterface $dataSet
     * @return Table
     */
    public function getDataSetTableSchema(DataSetInterface $dataSet)
    {
        $sm = $this->conn->getSchemaManager();
        $tableName = sprintf(self::DATA_SET_TABLE_NAME_TEMPLATE, $dataSet->getId());

        return $sm->listTableDetails($tableName);
    }

    public function getReportData(ParamsInterface $params)
    {
        $dataSets = $params->getDataSets();

        $reports = [];
        /**
         * @var DataSetInterface $dataSet
         */
        foreach($dataSets as $dataSet) {
            $result = $this->getDataFromDataSet($dataSet);

            if (is_array($result)) {
                $reports[] = $result;
            }
        }

        return $reports;
    }

    /**
     * @param DataSetInterface $dataSet
     * @return array|false
     */
    protected function getDataFromDataSet(DataSetInterface $dataSet)
    {

    }
}