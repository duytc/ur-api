<?php

namespace UR\DomainManager;

use UR\Service\Alert\AlertParams;

interface AlertManagerInterface extends ManagerInterface
{   
    public function deleteAlertsByIds($ids);

    public function updateMarkAsReadByIds($ids);

    public function updateMarkAsUnreadByIds($ids);

    public function getAlertsByParams(AlertParams $alertParams);
}