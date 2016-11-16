<?php


namespace UR\Service\Report\Groupers;


use UR\Service\DTO\Collection;

interface GrouperInterface
{
    /**
     * @param $groupingField
     * @param Collection $collection
     * @param array $metrics
     * @return array
     */
    public function getGroupedReport($groupingField, Collection $collection, array $metrics);
}