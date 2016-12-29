<?php


namespace UR\Service\Report;

use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\ParamsInterface;

interface ReportSelectorInterface
{
    /**
     * @param ParamsInterface $params
     * @param $overridingFilters
     * @return Statement
     */
    public function getReportData(ParamsInterface $params, $overridingFilters = null);
}