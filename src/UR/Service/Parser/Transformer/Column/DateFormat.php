<?php

namespace UR\Service\Parser\Transformer\Column;

use DateTime;

class DateFormat implements ColumnTransformerInterface
{
    protected $fromDateFormat;
    protected $toDateFormat;

    public function __construct($fromDateFormat = 'Y-m-d', $toDateFormat = 'Y-m-d')
    {
        $this->fromDateFormat = $fromDateFormat;
        $this->toDateFormat = $toDateFormat;
    }

    public function transform($value)
    {
        if ($value instanceof DateTime) {
            return $value->format($this->toDateFormat);
        }

        if (strcmp($value, "0000-00-00") === 0 || $value === null) {
            return null;
        }

        $date = DateTime::createFromFormat($this->fromDateFormat, $value);

        if (!$date) {
            return 2;
        }

        return $date->format($this->toDateFormat);
    }

    public function getFromDateForMat()
    {
        return $this->fromDateFormat;
    }
}