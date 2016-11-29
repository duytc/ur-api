<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\DTO\Report\ReportResultInterface;

interface ReportBuilderInterface
{
    /**
     * @param ParamsInterface $params
     * @return ReportResultInterface
     */
    public function getReport(ParamsInterface $params);
}