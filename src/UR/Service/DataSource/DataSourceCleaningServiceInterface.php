<?php


namespace UR\Service\DataSource;

use UR\Model\Core\DataSourceInterface;

interface DataSourceCleaningServiceInterface
{
    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function removeDuplicatedDateEntries(DataSourceInterface $dataSource);
}