<?php


namespace UR\Service\Report\Sorter;


use UR\Service\DTO\Collection;

interface SortByInterface
{

    /**
     * @param array $sortFields
     * @param Collection $reports
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function sortByFields(array $sortFields, Collection $reports, array $metrics, array $dimensions);

} 