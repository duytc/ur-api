<?php

namespace UR\Service\Parser\Filter;


class DateFilter implements ColumnFilterInterface
{
    protected $dateFrom;
    protected $dateTo;
    protected $format = 'Y-m-d';

    public function __construct($dateFrom, $dateTo)
    {
        $dateFrom = date($this->format, strtotime($dateFrom));
        $dateTo = date($this->format, strtotime($dateTo));
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function filter($filter)
    {
        $filter = date($this->format, strtotime($filter));
        if ($filter < $this->dateTo && $filter > $this->dateFrom) {
            return true;
        }
        return false;
    }
}