<?php

namespace UR\Worker\Workers;
use StdClass;

interface AlertWorkerInterface
{
    public function processAlert(StdClass $params);
}