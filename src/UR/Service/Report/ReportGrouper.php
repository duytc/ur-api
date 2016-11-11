<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;
use UR\Exception\InvalidArgumentException;
use UR\Service\Report\Groupers\DefaultGrouper;

class ReportGrouper implements ReportGrouperInterface
{
    public function groupReports(GroupByTransformInterface $transform, Statement $statement, array $metrics, array $dimensions)
    {
        $grouper = new DefaultGrouper();

        $groupingFields = $transform->getFields();
        if (empty($groupingFields)) {
            throw new InvalidArgumentException('grouping fields can not be empty');
        }

        return $grouper->getGroupedReport($transform->getFields(), $statement, $metrics, $dimensions);

    }
}