<?php

namespace UR\Service\Alert;


interface ProcessAlertInterface
{
    const DELETE_ACTION_KEY = 'delete';
    const MARK_AS_READ_ACTION_KEY = 'markAsRead';
    const MARK_AS_UNREAD_ACTION_KEY = 'markAsUnRead';

    /**
     * @param int $alertCode
     * @param int $publisherId
     * @param mixed $details
     * @param null|int $dataSourceId
     * @param null $optimizationIntegrationId
     * @return mixed
     */
    public function createAlert($alertCode, $publisherId, $details, $dataSourceId = null, $optimizationIntegrationId = null);

    /**
     * @param AlertParams $alertParam
     * @return mixed
     */
    public function updateStatusOrDeleteAlertsByParams(AlertParams $alertParam);
}