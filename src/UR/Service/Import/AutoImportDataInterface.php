<?php

namespace UR\Service\Import;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;

interface AutoImportDataInterface
{
    public function autoCreateDataImport(DataSetInterface $dataSet, DataSourceEntryInterface $dataSourceEntry);
}