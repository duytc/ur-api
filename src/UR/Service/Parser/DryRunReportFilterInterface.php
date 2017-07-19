<?php

namespace UR\Service\Parser;

use UR\Domain\DTO\ConnectedDataSource\DryRunParamsInterface;

interface DryRunReportFilterInterface
{
    /**
     * @param $reportResult
     * @param DryRunParamsInterface $dryRunParams
     * @return array result
     */
    public function filterReports(array $reportResult, DryRunParamsInterface $dryRunParams);
}