<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;

interface ReportSelectorInterface
{
    /**
     * @param ParamsInterface $params
     * @return array
     */
    public function getReportData(ParamsInterface $params);
}