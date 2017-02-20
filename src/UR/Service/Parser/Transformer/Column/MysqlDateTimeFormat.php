<?php

namespace UR\Service\Parser\Transformer\Column;

class MysqlDateTimeFormat extends DateFormat
{
    public function __construct($fromDateFormat = 'Y-m-d', $isCustomFormatDateFrom = false)
    {
        parent::__construct($fromDateFormat, 'Y-m-d H:i:s', $isCustomFormatDateFrom);
    }
}