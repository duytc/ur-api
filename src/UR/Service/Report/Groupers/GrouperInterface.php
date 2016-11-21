<?php


namespace UR\Service\Report\Groupers;


use UR\Service\DTO\Collection;

interface GrouperInterface
{
    /**
     * @param $groupingField
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function getGroupedReport($groupingField, Collection $collection, array $metrics, array $dimensions);
}