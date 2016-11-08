<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\DataSetInterface;
use \Doctrine\DBAL\Schema\Table;
interface ReportSelectorInterface
{
    /**
     * @param DataSetInterface $dataSet
     * @return Table
     */
    public function getDataSetTableSchema(DataSetInterface $dataSet);

    /**
     * @param ParamsInterface $params
     * @return array
     */
    public function getReportData(ParamsInterface $params);
}