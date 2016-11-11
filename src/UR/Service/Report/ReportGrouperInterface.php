<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Driver\Statement;
use UR\Domain\DTO\Report\Transforms\GroupByTransformInterface;

interface ReportGrouperInterface
{
    /**
     * @param GroupByTransformInterface $transform
     * @param Statement $statement
     * @param array $metrics
     * @param array $dimensions
     * @return array
     */
    public function groupReports(GroupByTransformInterface $transform, Statement $statement, array $metrics, array $dimensions);
}