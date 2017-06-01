<?php

namespace UR\Service\Alert;


interface ProcessAlertInterface
{
    /**
     * @param int $alertCode
     * @param int $publisherId
     * @param mixed $details
     * @param null|int $dataSourceId
     * @return mixed
     */
    public function createAlert($alertCode, $publisherId, $details, $dataSourceId = null);
}