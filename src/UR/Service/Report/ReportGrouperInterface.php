<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\DTO\Collection;

interface ReportGrouperInterface
{
    /**
     * @param Collection $collection
     * @param ParamsInterface $params
     * @param null $overridingFilters
     * @return mixed
     */
    public function groupForSingleView(Collection $collection, ParamsInterface $params, $overridingFilters = null);
}