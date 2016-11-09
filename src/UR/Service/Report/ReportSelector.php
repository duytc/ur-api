<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use UR\Domain\DTO\Report\Filters\AbstractFilterInterface;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Model\Core\DataSetInterface;
use UR\Repository\Report\DataSetRepositoryInterface;

class ReportSelector implements ReportSelectorInterface
{
    /**
     * @var DataSetRepositoryInterface
     */
    protected $repository;


    public function __construct(DataSetRepositoryInterface $repository)
    {
        $this->repository = $repository;
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
            $result = $this->repository->getData($dataSet, $filters);

            if (is_array($result)) {
                $reports[] = $result;
            }
        }

        return $reports;
    }
}