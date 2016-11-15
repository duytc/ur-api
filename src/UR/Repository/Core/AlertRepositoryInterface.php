<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;

interface AlertRepositoryInterface extends ObjectRepository
{
    public function getAlertsForUserQuery(UserRoleInterface $user, PagerParam $params);

    public function deleteAlertsByIds($ids);

    public function updateMarkAsReadByIds($ids);

    public function updateMarkAsUnreadByIds($ids);
}