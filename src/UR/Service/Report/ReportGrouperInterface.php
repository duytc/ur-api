<?php


namespace UR\Service\Report;


use UR\Service\DTO\Collection;

interface ReportGrouperInterface
{
    /**
     * @param Collection $collection
     * @param array $metrics
     * @return mixed
     */
    public function group(Collection $collection, array $metrics);
}