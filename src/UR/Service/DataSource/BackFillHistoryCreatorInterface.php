<?php


namespace UR\Service\DataSource;

use UR\Model\Core\DataSourceInterface;

interface BackFillHistoryCreatorInterface
{
    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function createBackfillForDataSource(DataSourceInterface $dataSource);
}