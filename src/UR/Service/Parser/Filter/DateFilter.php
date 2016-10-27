<?php

namespace UR\Service\Parser\Filter;

class DateFilter implements ColumnFilterInterface
{
    protected $dateFrom;
    protected $dateTo;

    public function __construct($dateFrom, $dateTo)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function filter($filter)
    {
        // TODO: Implement filter() method.
    }
}