<?php

namespace UR\Service\Alert;


interface ProcessAlertInterface
{
    /**
     * @param $alertCode
     * @param $publisherId
     * @param array $params
     * @return mixed
     */
    public function createAlert($alertCode, $publisherId, array $params);

}