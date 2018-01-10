<?php

namespace UR\Service\AutoOptimization;


use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\DTO\Report\ReportResultInterface;

interface DataTrainingCollectorInterface
{
    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @return \UR\Service\DTO\Report\ReportResultInterface
     */
    public function buildDataForAutoOptimizationConfig(AutoOptimizationConfigInterface $autoOptimizationConfig);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifiers
     * @return ReportResultInterface
     */
    public function getDataByIdentifiers(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifiers);
}