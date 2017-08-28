<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Query\QueryBuilder;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\ReportCollection;
use UR\Service\DTO\Collection;

interface ReportGrouperInterface
{
    /**
     * @param Collection $collection
     * @param array $metrics
     * @param $weightedCalculation
     * @param $dateRanges
     * @param $isShowDataSetName
     * @return mixed
     */
    public function groupForMultiView(Collection $collection, array $metrics, $weightedCalculation, $dateRanges, $isShowDataSetName);

    /**
     * @param QueryBuilder $subQuery
     * @param Collection $collection
     * @param ParamsInterface $params
     * @param null $overridingFilters
     * @return mixed
     */
    public function groupForSingleView($subQuery, Collection $collection, ParamsInterface $params, $overridingFilters = null);
}