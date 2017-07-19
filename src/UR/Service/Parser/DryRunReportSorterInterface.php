<?php

namespace UR\Service\Parser;


use UR\Domain\DTO\ConnectedDataSource\DryRunParamsInterface;

interface DryRunReportSorterInterface
{
    /**
     * @param $reportResult
     * @param DryRunParamsInterface $dryRunParams
     * @return array report
     */
    public function sortReports(array $reportResult, DryRunParamsInterface $dryRunParams);
}