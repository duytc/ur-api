<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;

interface ReportGrouperInterface
{
    /**
     * @param GroupByTransformInterface $transform
     * @param array $report
     * @param array $metrics
     * @param array $dimensions
     * @return array
     */
    public function groupReports(GroupByTransformInterface $transform, array $report, array $metrics, array $dimensions);
}