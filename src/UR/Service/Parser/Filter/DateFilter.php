<?php

namespace UR\Service\Parser\Filter;


class DateFilter implements ColumnFilterInterface
{
    protected $dateFrom;
    protected $dateTo;
    protected $format;

    public function __construct($format, $dateFrom, $dateTo)
    {
        $this->format = '!' . $format;
        $this->dateFrom = \DateTime::createFromFormat($this->format, $dateFrom);
        $this->dateTo = \DateTime::createFromFormat($this->format, $dateTo);
    }

    public function filter($filter)
    {
        $filter = \DateTime::createFromFormat($this->format, $filter);

        if(!$filter){
            return 2;
        }
        if ($filter < $this->dateTo && $filter > $this->dateFrom) {
            return true;
        }
        return false;
    }
}