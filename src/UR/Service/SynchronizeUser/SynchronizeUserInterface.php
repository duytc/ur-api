<?php

namespace UR\Service\SynchronizeUser;


interface SynchronizeUserInterface
{
    public function synchronizeUser($id, $entity);
}