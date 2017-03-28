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

    /**
     * @return array
     */
    public function getColumns();

    /**
     * @param array $fromDateFormat
     * @return array
     */
    public function getRows(array $fromDateFormat);

    /**
     * @return int
     */
    public function getDataRow();
}