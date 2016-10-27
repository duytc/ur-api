<?php

namespace UR\Service\Parser;

use UR\Service\DataSource\DataSourceInterface;

interface ParserInterface
{
    public function parse(DataSourceInterface $dataSource, ParserConfig $config);
}