<?php

namespace UR\DomainManager;

interface IntegrationManagerInterface extends ManagerInterface
{
    /**
     * return all resource by name
     * @param string $name
     */
    public function findByName($name);
}