<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;

interface ReportBuilderInterface
{
    /**
     * @param ParamsInterface $params
     * @return mixed
     */
    public function getReport(ParamsInterface $params);
}