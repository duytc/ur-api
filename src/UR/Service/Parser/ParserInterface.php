<?php

namespace UR\Service\Parser;

use UnifiedReports\DataSource\DataSourceInterface;
use UnifiedReports\DTO\Collection;

interface ParserInterface
{
    public function parse(DataSourceInterface $dataSource, ParserConfig $config): Collection;
}