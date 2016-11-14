<?php


namespace UR\Service\Report\Groupers;


use Doctrine\DBAL\Driver\Statement;

interface GrouperInterface
{
    /**
     * @param $groupingField
     * @param Statement $statement
     * @param array $metrics
     * @return array
     */
    public function getGroupedReport($groupingField, Statement $statement, array $metrics);
}