<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;
use UR\Service\DTO\Collection;

interface ReportGrouperInterface
{
    /**
     * @param GroupByTransformInterface $transform
     * @param Collection $collection
     * @param array $metrics
     * @return array
     */
    public function groupReports(GroupByTransformInterface $transform, Collection $collection, array $metrics);
}