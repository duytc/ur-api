<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransformInterface;
use UR\Exception\InvalidArgumentException;

class ReportSorter implements ReportSorterInterface
{
    const SORT_DIRECTION_ASC = -1;
    const SORT_DIRECTION_DESC = 1;

    protected $sortDirection;

    protected function setSortDirection($direction)
    {
        if (!is_int($direction)) {
            throw new InvalidArgumentException('invalid sort direction');
        }

        $this->sortDirection = $direction;
    }

    public function sort(array $reports, SortByTransformInterface $sortBy)
    {
        if ($sortBy->getDirection() === SortByTransform::SORT_ASC) {
            $this->setSortDirection(self::SORT_DIRECTION_ASC);
        } else {
            $this->setSortDirection(self::SORT_DIRECTION_DESC);
        }

        usort($reports, "compareLogic");
    }

    protected function compareLogic($a, $b)
    {
        return ($a <= $b) ? $this->sortDirection : -1 * $this->sortDirection;
    }
}