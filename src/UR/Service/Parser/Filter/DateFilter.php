<?php

namespace UR\Service\Parser\Filter;


use UR\Service\DataSet\FilterType;

class DateFilter implements ColumnFilterInterface
{
    protected $dateFrom;
    protected $dateTo;
    protected $format;

    public function __construct($format, $dateFrom, $dateTo)
    {
        $this->format = '!' . $format;
        $this->dateFrom = \DateTime::createFromFormat(FilterType::DEFAULT_DATE_FORMAT, $dateFrom);
        $this->dateTo = \DateTime::createFromFormat(FilterType::DEFAULT_DATE_FORMAT, $dateTo);
    }

    public function filter($filter)
    {
        $filter = \DateTime::createFromFormat($this->format, $filter);

        if (!$filter) {
            return 2;
        }

        if ($filter <= $this->dateTo && $filter >= $this->dateFrom) {
            return true;
        }

        return false;
    }
}