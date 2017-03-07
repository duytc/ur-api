<?php

namespace UR\Worker\Workers;

use StdClass;
use UR\Model\User\UserEntityInterface;
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
     * @param StdClass $inputAlert
     * @internal param StdClass $params
     */
    public function processAlert(StdClass $inputAlert)
    {
        $code = $inputAlert->code;
        $publisherId = $inputAlert->publisherId;
        $message = $inputAlert->message;
        $details = $inputAlert->details;

        $this->alert->createAlert($code, $publisherId, $message, $details);
    }
}