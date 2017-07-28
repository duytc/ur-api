<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;

interface DataSourceIntegrationScheduleRepositoryInterface extends ObjectRepository
{
    /**
     * find data source integration To Be Executed by executed_at time or not yet run backfill
     * Notice: if backfill is enable and is not yet run => also return to make it to be run immediately
     *
     * @return array|DataSourceIntegrationScheduleInterface[]
     */
    public function findToBeExecuted();

    /**
     * @param DataSourceInterface $dataSource
     * @return array|DataSourceIntegrationScheduleInterface[]
     */
    public function findByDataSource(DataSourceInterface $dataSource);

    /**
     * @param $uuid
     * @return array|DataSourceIntegrationScheduleInterface[]
     */
    public function findByUUID($uuid);
}