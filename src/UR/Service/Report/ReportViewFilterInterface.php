<?php

namespace UR\Service\Report;

use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\DTO\Collection;
use UR\Service\DTO\Report\ReportResultInterface;

interface ReportViewFilterInterface
{
    /**
     * @param Collection $reportCollection
     * @param ParamsInterface $params
     * @return ReportResultInterface
     */
    public function filterReports(Collection $reportCollection, $params);
}