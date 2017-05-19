<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;

interface DataSourceIntegrationScheduleManagerInterface extends ManagerInterface
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
     * @param DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule
     * @param \DateTime $executedAt
     * @return DataSourceIntegrationScheduleInterface
     */
    public function updateExecuteAt(DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule, \DateTime $executedAt);
}