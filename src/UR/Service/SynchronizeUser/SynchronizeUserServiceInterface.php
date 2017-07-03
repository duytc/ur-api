<?php

namespace UR\Service\SynchronizeUser;


interface SynchronizeUserServiceInterface
{
    /**
     * @param array $entityData
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function synchronizeUser(array $entityData);
}