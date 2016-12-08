<?php

namespace UR\Service\DataSource;

interface DataSourceInterface
{
    const DETECT_HEADER_ROWS = 20;

    public function getColumns();

    public function getRows($fromDateFormat);
}