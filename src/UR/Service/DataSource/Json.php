<?php

namespace UR\Service\DataSource;

class Json implements DataSourceInterface
{
    public function __construct($filePath)
    {
    }

    public function getColumns()
    {
        // todo
        return [];
    }

    public function getRows($fromDateFormat)
    {
        // todo
        return [];
    }

    public function getDataRow()
    {
        // TODO: Implement getDataRow() method.
    }
}