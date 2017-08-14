<?php

namespace UR\DomainManager;

use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\TagInterface;

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

    /**
     * @param TagInterface $tag
     */
    public function findByTag(TagInterface $tag);
}