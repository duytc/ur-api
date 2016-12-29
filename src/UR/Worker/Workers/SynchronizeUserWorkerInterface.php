<?php

namespace UR\Worker\Workers;


use stdClass;

interface SynchronizeUserWorkerInterface
{
    public function synchronizeUser(StdClass $params);
}