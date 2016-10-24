<?php

namespace UnifiedReports\Parser\Transformer\Column;

class MysqlDateTimeFormat extends DateFormat
{
    public function __construct(string $fromDateFormat = 'Y-m-d')
    {
        parent::__construct($fromDateFormat, 'Y-m-d H:i:s');
    }
}