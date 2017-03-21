<?php

namespace UR\Service\Alert;


interface ProcessAlertInterface
{
    /**
     * @param $alertCode
     * @param $publisherId
     * @param $details
     * @return mixed
     */
    public function createAlert($alertCode, $publisherId, $details);

}