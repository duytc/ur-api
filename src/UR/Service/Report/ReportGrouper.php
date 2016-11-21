<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;
use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;
use UR\Service\Report\Groupers\DefaultGrouper;

class ReportGrouper implements ReportGrouperInterface
{
    public function groupReports(GroupByTransformInterface $transform, Collection $collection, array $metrics, array $dimensions)
    {
        $grouper = new DefaultGrouper();

        $groupingFields = $transform->getFields();
        if (empty($groupingFields)) {
            throw new InvalidArgumentException('grouping fields can not be empty');
        }

        return $grouper->getGroupedReport($transform->getFields(), $collection, $metrics, $dimensions);

    }
}