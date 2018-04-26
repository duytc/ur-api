<?php

namespace UR\DomainManager;


interface OptimizationIntegrationManagerInterface extends ManagerInterface
{
    /**
     * @param int|$optimizationIntegrationId
     * @return array
     */
    public function getOptimizationIntegrationAdSlotIds($optimizationIntegrationId = null);
}