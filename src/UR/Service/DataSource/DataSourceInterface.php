<?php

namespace UR\Service\DataSource;

interface DataSourceInterface
{
    const DETECT_HEADER_ROWS = 20;

    const DETECT_JSON_HEADER_ROWS = 50;

    const ROW_MATCH = 15;

    const FIRST_MATCH = 1;

    const SECOND_ROW = 2;

    const MAX_ROW_XLS = 65535;

    public function getColumns();

    public function getRows($fromDateFormat);

    public function getDataRow();
}