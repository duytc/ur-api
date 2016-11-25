<?php

namespace UR\Worker\Workers;


use stdClass;
use UR\Service\SynchronizeUser\SynchronizeUserInterface;

class SynchronizeUserWorker implements SynchronizeUserWorkerInterface
{
    /**
     * @var SynchronizeUserInterface
     */
    private $synchronizeUser;

    public function __construct(SynchronizeUserInterface $synchronizeUser)
    {
        $this->synchronizeUser = $synchronizeUser;
    }

    public function synchronizeUser(StdClass $params)
    {
        $id = $params->id;
        $entity = (array) $params->entity;
        $this->synchronizeUser->synchronizeUser($id, $entity);
    }
}