<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSourceIntegrationInterface;

interface DataSourceIntegrationManagerInterface extends ManagerInterface
{
    /**
     * @param $canonicalName
     * @return array|DataSourceIntegrationInterface[]
     */
    public function findByIntegrationCanonicalName($canonicalName);

    /**
     * @return array|DataSourceIntegrationInterface[]
     */
    public function getIntegrationBySchedule();

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @param \DateTime $lastExecuteTime
     * @return DataSourceIntegrationInterface
     */
    public function updateLastExecuteTime(DataSourceIntegrationInterface $dataSourceIntegration, \DateTime $lastExecuteTime);
}