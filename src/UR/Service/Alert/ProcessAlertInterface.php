<?php

namespace UR\Service\Alert;


interface ProcessAlertInterface
{
    /**
     * @param $alertCode
     * @param $publisherId
     * @param $messageDetail
     * @return mixed
     */
    public function createAlert($alertCode, $publisherId, $messageDetail);

}