<?php


namespace UR\Service\Report\Groupers;


use Doctrine\DBAL\Driver\Statement;

class ByDateGrouper extends AbstractGrouper
{
    public function getGroupedReport($groupingFields, Statement $statement, array $metrics)
    {
        parent::getGroupedReport($groupingFields, $statement, $metrics); // TODO: Change the autogenerated stub
    }

}