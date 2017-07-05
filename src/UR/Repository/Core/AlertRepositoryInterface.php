<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\AlertPagerParam;
use UR\Model\Core\DataSourceInterface;
use Doctrine\ORM\QueryBuilder;
use UR\Model\PagerParam;
use UR\Model\User\Role\UserRoleInterface;

interface AlertRepositoryInterface extends ObjectRepository
{
    /**
     * @param UserRoleInterface $user
     * @param PagerParam|AlertPagerParam $params
     * @return QueryBuilder
     */
    public function getAlertsForUserQuery(UserRoleInterface $user, PagerParam $params);

    /**
     * @param DataSourceInterface $dataSource
     * @param PagerParam|AlertPagerParam $param
     * @return QueryBuilder
     */
    public function getAlertsByDataSourceQuery(DataSourceInterface $dataSource, PagerParam $param);

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