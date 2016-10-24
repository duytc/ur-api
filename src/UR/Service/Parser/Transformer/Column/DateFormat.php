<?php

namespace UnifiedReports\Parser\Transformer\Column;

use DateTime;

class DateFormat implements ColumnTransformerInterface
{
    protected $fromDateFormat;
    protected $toDateFormat;

    public function __construct(string $fromDateFormat = 'Y-m-d', string $toDateFormat = 'Y-m-d')
    {
        $this->fromDateFormat = $fromDateFormat;
        $this->toDateFormat = $toDateFormat;
    }

    public function transform($value)
    {
        $date = DateTime::createFromFormat($this->fromDateFormat, $value);

        if (!$date) {
            return $value;
        }

        return $date->format($this->toDateFormat);
    }
}