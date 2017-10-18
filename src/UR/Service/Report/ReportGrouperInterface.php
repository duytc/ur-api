<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Query\QueryBuilder;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\DTO\Collection;

interface ReportGrouperInterface
{
    /**
     * @param QueryBuilder $subQuery
     * @param Collection $collection
     * @param ParamsInterface $params
     * @param null $overridingFilters
     * @return mixed
     */
    public function groupForSingleView($subQuery, Collection $collection, ParamsInterface $params, $overridingFilters = null);
}