<?php

namespace UR\Worker\Workers;

use StdClass;
use UR\Model\User\UserEntityInterface;
use UR\Service\Alert\AlertParams;
use UR\Service\Alert\ProcessAlertInterface;

class AlertWorker implements AlertWorkerInterface
{
    /**
     * @var UserEntityInterface
     */
    private $alert;

    /**
     * AlertWorker constructor.
     * @param ProcessAlertInterface $alert
     */
    public function __construct(ProcessAlertInterface $alert)
    {
        $this->alert = $alert;
    }

    /**
     * @param StdClass $params
     * @throws \Exception
     */
    public function processAlert(StdClass $params)
    {
        $params = (array) $params->parameters;
        $code = $params['code'];
        $publisherId = $params['publisherId'];
        $params = $params['params'];

        $this->alert->createAlert($code, $publisherId, $params);
    }
}