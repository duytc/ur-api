<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;
use UR\Service\DTO\Collection;

interface ReportGrouperInterface
{
    /**
     * @param GroupByTransformInterface $transform
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function groupReports(GroupByTransformInterface $transform, Collection $collection, array $metrics, array $dimensions);
}