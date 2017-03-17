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
}