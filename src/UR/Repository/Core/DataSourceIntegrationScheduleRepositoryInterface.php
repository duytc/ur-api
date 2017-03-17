<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;

interface DataSourceIntegrationScheduleRepositoryInterface extends ObjectRepository
{
    /**
     * @return array|DataSourceIntegrationScheduleInterface[]
     */
    public function findToBeExecuted();
}