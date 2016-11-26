<?php

namespace UR\Worker\Workers;


use stdClass;
use UR\Service\SynchronizeUser\SynchronizeUserServiceInterface;

class SynchronizeUserWorker implements SynchronizeUserWorkerInterface
{
    /**
     * @var SynchronizeUserServiceInterface
     */
    private $synchronizeUser;

    public function __construct(SynchronizeUserServiceInterface $synchronizeUser)
    {
        $this->synchronizeUser = $synchronizeUser;
    }

    public function synchronizeUser(StdClass $params)
    {
        $entity = (array) $params->entity;
        $this->synchronizeUser->synchronizeUser($entity);
    }
}