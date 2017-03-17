<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSourceIntegrationScheduleInterface;

interface DataSourceIntegrationScheduleManagerInterface extends ManagerInterface
{
    /**
     * @return array|DataSourceIntegrationScheduleInterface[]
     */
    public function findToBeExecuted();

    /**
     * @param DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule
     * @param \DateTime $executedAt
     * @return DataSourceIntegrationScheduleInterface
     */
    public function updateExecuteAt(DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule, \DateTime $executedAt);
}