<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\ParamsInterface;

interface ReportSelectorInterface
{
    /**
     * @param ParamsInterface $params
     * @return Statement
     */
    public function getReportData(ParamsInterface $params);
}