<?php

namespace UR\DomainManager;

use UR\Model\Core\IntegrationInterface;

interface IntegrationManagerInterface extends ManagerInterface
{
    /**
     * return all resource by name
     * @param string $name
     */
    public function findByName($name);

    /**
     * return resource by canonicalName
     * @param string $canonicalName
     * @return IntegrationInterface
     */
    public function findByCanonicalName($canonicalName);
}