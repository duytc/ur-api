<?php


namespace UR\Service\Report\Groupers;


use Doctrine\DBAL\Driver\Statement;

interface GrouperInterface
{
    /**
     * @param $groupingField
     * @param Statement $statement
     * @param array $metrics
     * @param array $dimensions
     * @return array
     */
    public function getGroupedReport($groupingField, Statement $statement, array $metrics, array $dimensions);
}