<?php

namespace UR\Service\DataSource;

interface DataSourceInterface
{
    const DETECT_HEADER_ROWS = 20;

    const ROW_MATCH = 10;

    const FIRST_MATCH = 1;

    const SECOND_ROW = 2;

    public function getColumns();

    public function getRows($fromDateFormat);

    public function getDataRow();
}