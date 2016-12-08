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
     * @param $singleDataSet
     * @return mixed
     */
    public function group(Collection $collection, array $metrics, $weightedCalculation, $dateRanges, $singleDataSet = false);
}