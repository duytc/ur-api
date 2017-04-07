<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;

interface DataSourceIntegrationScheduleManagerInterface extends ManagerInterface
{
    /**
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