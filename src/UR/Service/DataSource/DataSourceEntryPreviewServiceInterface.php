<?php


namespace UR\Service\DataSource;


use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;

interface DataSourceEntryPreviewServiceInterface
{
    /**
     * @param DataSourceEntryInterface|null $dataSourceEntry
     * @param int $limit
     * @return mixed
     */
    public function preview(DataSourceEntryInterface $dataSourceEntry, $limit = 100);
}