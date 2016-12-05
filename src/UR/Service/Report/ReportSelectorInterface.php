<?php


namespace UR\Service\Report;

use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\ParamsInterface;

interface ReportSelectorInterface
{
    /**
     * @param ParamsInterface $params
     * @param $dateRange
     * @param $overridingFilters
     * @return Statement
     */
    public function getReportData(ParamsInterface $params, &$dateRange, $overridingFilters = null);
}