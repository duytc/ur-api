<?php


namespace UR\Service\Report\Groupers;


use UR\Domain\DTO\Report\Transforms\GroupByTransform;

class ByDateGrouper extends AbstractGrouper
{
    public function getGroupedReport(GroupByTransform $transform, array $reports, array $metrics)
    {
        parent::getGroupedReport($transform, $reports, $metrics); // TODO: Change the autogenerated stub
    }

}