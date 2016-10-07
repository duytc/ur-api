<?php

namespace UR\DomainManager;

interface IntegrationGroupManagerInterface extends ManagerInterface
{
    /**
     * return all resource by name
     * @param string $name
     */
    public function findByName($name);
}