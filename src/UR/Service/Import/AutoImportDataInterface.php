<?php

namespace UR\Service\Import;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DataSet\Importer;
use UR\Service\DataSet\Locator;

interface AutoImportDataInterface
{
    public function autoCreateDataImport(ConnectedDataSourceInterface $connectedDataSource, Importer $dataSetImporter, Locator $dataSetLocator);
}