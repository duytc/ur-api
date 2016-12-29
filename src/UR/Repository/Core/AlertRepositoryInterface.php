<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\PagerParam;
use Doctrine\ORM\QueryBuilder;
use UR\Model\User\Role\UserRoleInterface;

interface AlertRepositoryInterface extends ObjectRepository
{
    /**
     * @param UserRoleInterface $user
     * @param PagerParam $params
     * @return QueryBuilder
     */
    public function getAlertsForUserQuery(UserRoleInterface $user, PagerParam $params);

    /**
     * @param $ids
     * @return mixed
     */
    public function deleteAlertsByIds($ids);

    /**
     * @param $ids
     * @return mixed
     */
    public function updateMarkAsReadByIds($ids);

    /**
     * @param $ids
     * @return mixed
     */
    public function updateMarkAsUnreadByIds($ids);
}