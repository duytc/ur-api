<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\DataSourceIntegrationInterface;

interface DataSourceIntegrationRepositoryInterface extends ObjectRepository
{
    /**
     * @param $canonicalName
     * @return array|DataSourceIntegrationInterface[]
     */
    public function findByIntegrationCanonicalName($canonicalName);
}