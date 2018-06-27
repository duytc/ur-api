<?php

namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\AlertPagerParam;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceInterface;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Service\Alert\AlertParams;

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
     * @param UserRoleInterface $user
     * @param PagerParam $param
     * @return mixed
     */
    public function getAllDataSourceAlertsQuery(UserRoleInterface $user, PagerParam $param);
    
    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @param PagerParam|AlertPagerParam $param
     * @return QueryBuilder
     */
    public function getAlertsByOptimizationQuery(OptimizationIntegrationInterface $optimizationIntegration, PagerParam $param);

    /**
     * @param UserRoleInterface $user
     * @param PagerParam $param
     * @return mixed
     */
    public function getAllOptimizationAlertsQuery(UserRoleInterface $user, PagerParam $param);

    /**
     * @param PublisherInterface $publisher
     * @param array $types
     * @return array
     */
    public function getAlertsToSendEmailByTypesQuery(PublisherInterface $publisher, array $types);

    /**
     * @param $ids
     * @return mixed
     */
    public function deleteAlertsByIds($ids = null);

    /**
     * @param $ids
     * @return mixed
     */
    public function updateMarkAsReadByIds($ids=null);

    /**
     * @param $ids
     * @return mixed
     */
    public function updateMarkAsUnreadByIds($ids=null);

    /**
     * @param AlertInterface $newAlert
     * @return mixed
     */
    public function findOldActionRequiredAlert(AlertInterface $newAlert);

    public function getAlertsByParams(AlertParams $alertParams);

    /**
     * @param OptimizationIntegrationInterface $optimizationIntegration
     * @param $alertType
     * @param \DateTime $fromDate
     * @param \DateTime $toDate
     * @return mixed
     */
    public function getAlertsCreatedFromDateRange(OptimizationIntegrationInterface $optimizationIntegration, $alertType, \DateTime $fromDate, \DateTime $toDate);
}