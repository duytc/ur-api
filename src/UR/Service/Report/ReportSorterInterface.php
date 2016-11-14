<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\Transforms\SortByTransformInterface;

interface ReportSorterInterface
{
    public function sort(array $reports, SortByTransformInterface $sortBy);
}