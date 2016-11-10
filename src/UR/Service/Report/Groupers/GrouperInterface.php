<?php


namespace UR\Service\Report\Groupers;


interface GrouperInterface
{
    /**
     * @param $groupingField
     * @param array $reports
     * @param array $metrics
     * @param array $dimensions
     * @return array
     */
    public function getGroupedReport($groupingField, array $reports, array $metrics, array $dimensions);
}