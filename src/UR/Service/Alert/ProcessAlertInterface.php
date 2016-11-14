<?php

namespace UR\Service\Alert;


interface ProcessAlertInterface
{
    public function createAlert($alertCode, $publisherId, $messageDetail);

}