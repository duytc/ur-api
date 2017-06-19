<?php


namespace UR\Service\Report;


use UR\Service\DTO\Collection;

interface ReportGrouperInterface
{
    /**
     * @param Collection $collection
     * @param array $metrics
     * @param $weightedCalculation
     * @param $dateRanges
     * @param $isShowDataSetName
     * @return mixed
     */
    public function group(Collection $collection, array $metrics, $weightedCalculation, $dateRanges, $isShowDataSetName);
}