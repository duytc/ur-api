<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;
use UR\Model\Core\DataSourceInterface;

interface DataSourceIntegrationScheduleRepositoryInterface extends ObjectRepository
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
}