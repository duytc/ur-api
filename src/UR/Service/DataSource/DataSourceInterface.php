<?php

namespace UR\Service\DataSource;

interface DataSourceInterface
{
    public function getColumns();
    public function getRows();
}