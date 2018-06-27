<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\AlertPagerParam;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;
use UR\Service\Alert\AlertParams;

class AlertRepository extends EntityRepository implements AlertRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'createdDate' => 'createdDate', 'title' => 'code', 'type' => 'type'];

    public function getAlertsForUserQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        // support filter by alert types
        if ($param instanceof AlertPagerParam && !empty($param->getTypes())) {
            $types = explode(',', $param->getTypes());
            $qb
                ->andWhere('a.type IN (:types)')
                ->setParameter('types', $types);
        }

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());

            $orX = $qb->expr()->orX();
            $conditions = array(
                $qb->expr()->like('a.id', ':searchKey'),
                $qb->expr()->like('a.code', ':searchKey'),
                $qb->expr()->like('a.type', ':searchKey')
            );
            $orX->addMultiple($conditions);

            $qb
                ->andWhere($orX)
                ->setParameter('searchKey', $searchLike);

            $searchLike = sprintf('%%%s%%', str_replace("/", "-", $param->getSearchKey()));
            $qb
                ->orWhere($qb->expr()->like('SUBSTRING(a.createdDate, 0, 10)', ':date'))
                ->setParameter('date', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            array_key_exists($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case 'id':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'createdDate':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'title':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['title'], $param->getSortDirection());
                    break;

                case 'type':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['type'], $param->getSortDirection());
                    break;

                default:
                    break;
            }
        } else {
            $qb->addOrderBy('a.createdDate', 'desc');
        }

        return $qb;
    }

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getAlertsForPublisherQuery($user) : $this->createQueryBuilder('a');
    }

    public function getAlertsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.publisher', 'p')
            ->select('a, p')
            ->where('a.publisher = :publisher')
            ->setParameter('publisher', $publisher);

        if (is_int($limit)) {
            $qb->setMaxResults($limit);
        }

        if (is_int($offset)) {
            $qb->setFirstResult($offset);
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getAlertsByDataSourceQuery(DataSourceInterface $dataSource, PagerParam $param)
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.dataSource', 'ds')
            ->where('a.dataSource = :dataSource')
            ->andWhere('ds.enable = :enable')
            ->setParameter('dataSource', $dataSource)
            ->setParameter('enable', true);

        // support filter by alert types
        if ($param instanceof AlertPagerParam && !empty($param->getTypes())) {
            $types = explode(',', $param->getTypes());
            $qb
                ->andWhere('a.type IN (:types)')
                ->setParameter('types', $types);
        }

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('a.id', ':searchKey'),
                    $qb->expr()->like('a.code', ':searchKey'),
                    $qb->expr()->like('a.type', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case 'id':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'createdDate':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'title':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['title'], $param->getSortDirection());
                    break;

                case 'type':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['type'], $param->getSortDirection());
                    break;

                default:
                    break;
            }
        } else {
            $qb->addOrderBy('a.createdDate', 'desc');
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getAllDataSourceAlertsQuery(UserRoleInterface $user, PagerParam $param)
    {
        if ($user instanceof PublisherInterface) {
            $qb = $this->createQueryBuilder('a')
                ->leftJoin('a.publisher', 'p')
                ->select('a, p')
                ->where('a.publisher = :publisher')
                ->setParameter('publisher', $user);
        } else {
            $qb = $this->createQueryBuilder('a');
        }

        $qb
            ->join('a.dataSource', 'ds')
            ->andWhere('ds.enable = :enable')
            ->andWhere('a.dataSource is not null')
            ->setParameter('enable', true);

        // support filter by alert types
        if ($param instanceof AlertPagerParam && !empty($param->getTypes())) {
            $types = explode(',', $param->getTypes());
            $qb
                ->andWhere('a.type IN (:types)')
                ->setParameter('types', $types);
        }

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('a.id', ':searchKey'),
                    $qb->expr()->like('a.code', ':searchKey'),
                    $qb->expr()->like('a.type', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case 'id':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'createdDate':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'title':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['title'], $param->getSortDirection());
                    break;

                case 'type':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['type'], $param->getSortDirection());
                    break;

                default:
                    break;
            }
        } else {
            $qb->addOrderBy('a.createdDate', 'desc');
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getAlertsByOptimizationQuery(OptimizationIntegrationInterface $optimizationIntegration, PagerParam $param)
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.optimizationIntegration', 'ds')
            ->where('a.optimizationIntegration = :optimizationIntegration')
            ->setParameter('optimizationIntegration', $optimizationIntegration);

        // support filter by alert types
        if ($param instanceof AlertPagerParam && !empty($param->getTypes())) {
            $types = explode(',', $param->getTypes());
            $qb
                ->andWhere('a.type IN (:types)')
                ->setParameter('types', $types);
        }

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('a.id', ':searchKey'),
                    $qb->expr()->like('a.code', ':searchKey'),
                    $qb->expr()->like('a.type', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case 'id':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'createdDate':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'title':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['title'], $param->getSortDirection());
                    break;

                case 'type':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['type'], $param->getSortDirection());
                    break;

                default:
                    break;
            }
        } else {
            $qb->addOrderBy('a.createdDate', 'desc');
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function getAllOptimizationAlertsQuery(UserRoleInterface $user, PagerParam $param)
    {
        if ($user instanceof PublisherInterface) {
            $qb = $this->createQueryBuilder('a')
                ->leftJoin('a.optimizationIntegration', 'oi')
                ->leftJoin('oi.optimizationRule', 'r')
                ->select('a')
                ->where('r.publisher = :publisher')
                ->setParameter('publisher', $user);
        } else {
            $qb = $this->createQueryBuilder('a');
        }

        $qb
            ->join('a.optimizationIntegration', 'ds')
            ->andWhere('a.optimizationIntegration is not null');

        // support filter by alert types
        if ($param instanceof AlertPagerParam && !empty($param->getTypes())) {
            $types = explode(',', $param->getTypes());
            $qb
                ->andWhere('a.type IN (:types)')
                ->setParameter('types', $types);
        }

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('ds.name', ':searchKey'),
                    $qb->expr()->like('a.id', ':searchKey'),
                    $qb->expr()->like('a.code', ':searchKey'),
                    $qb->expr()->like('a.type', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case 'id':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'createdDate':
                    $qb->addOrderBy('a.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case 'title':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['title'], $param->getSortDirection());
                    break;

                case 'type':
                    $qb->addOrderBy('a.' . $this->SORT_FIELDS['type'], $param->getSortDirection());
                    break;

                default:
                    break;
            }
        } else {
            $qb->addOrderBy('a.createdDate', 'desc');
        }

        return $qb;
    }

    public function deleteAlertsByIds($ids = null)
    {
        $qb = $this->createQueryBuilder('a');

        $qb->delete();

        if (!empty($ids)) {
            $qb->where($qb->expr()->in('a.id', $ids));
        }

        return $qb->getQuery()->getResult();
    }

    public function updateMarkAsReadByIds($ids = null)
    {
        $qb = $this->createQueryBuilder('a');

        $qb->update()
            ->set('a.isRead', 1);

        if (!empty($ids)) {
            $qb->where($qb->expr()->in('a.id', $ids));
        }

        return $qb->getQuery()->getResult();
    }

    public function updateMarkAsUnreadByIds($ids = null)
    {
        $qb = $this->createQueryBuilder('a');

        $qb->update()
            ->set('a.isRead', 0);

        if (!empty($ids)) {
            $qb->where($qb->expr()->in('a.id', $ids));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getAlertsToSendEmailByTypesQuery(PublisherInterface $publisher, array $types)
    {
        $qb = $this->createQueryBuilderForUser($publisher)
            ->andWhere('a.isSent = :sent')
            ->setParameter('sent', false);

        // support filter by alert types
        if (!empty($types)) {
            $qb
                ->andWhere('a.type IN (:types)')
                ->setParameter('types', $types);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findOldActionRequiredAlert(AlertInterface $newAlert)
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.id < :id')
            ->setParameter('id', $newAlert->getId())
            ->andWhere('a.type = :type')
            ->setParameter('type', $newAlert->getType())
            ->andWhere('a.optimizationIntegration = :optimizationIntegration')
            ->setParameter('optimizationIntegration', $newAlert->getOptimizationIntegration());

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function getAlertsByParams(AlertParams $alertParams)
    {
        $publisherId = $alertParams->getPublisherId();
        $alertSource = $alertParams->getAlertSource();
        $sourceId = $alertParams->getSourceId();
        $types = $alertParams->getTypes();


        $qb = $this->createQueryBuilder('a');
        $qb = $qb->where('a.id > 0');

        if (!empty($publisherId)) {
            $qb = $qb->andwhere('a.publisher = :publisherId')
                ->setParameter('publisherId', $publisherId);
        }

        if (!empty($alertSource)) {
            switch ($alertSource) {
                case 'all':
                    break;
                case 'datasource':
                    $qb = $qb->andWhere('a.dataSource = :sourceId');
                    $qb = $qb->setParameter('sourceId', $sourceId);
                    break;
                case 'optimization':
                    $qb = $qb->andWhere('a.optimizationIntegration = :sourceId');
                    $qb = $qb->setParameter('sourceId', $sourceId);
                    break;
            }
        }

        if (!empty($types)) {
            $qb = $qb->andWhere($qb->expr()->in('a.type', $types));
        }

        return $qb->getQuery()->getResult();

    }

    /**
     * @inheritdoc
     */
    public function getAlertsCreatedFromDateRange(OptimizationIntegrationInterface $optimizationIntegration, $alertType, \DateTime $fromDate, \DateTime $toDate)
    {
        $optimizationRule = $optimizationIntegration->getOptimizationRule();
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return [];
        }

        //Local server might use timezone not UTC, such as PST8PDT
        $fromDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        $qb = $this->createQueryBuilderForUser($optimizationRule->getPublisher())
            ->andWhere('a.type = :type')
            ->andWhere('a.createdDate > :startDate')
            ->andWhere('a.createdDate < :endDate')
            ->setParameter('type', $alertType)
            ->setParameter('startDate', $fromDate)
            ->setParameter('endDate', $toDate);

        return $qb->getQuery()->getResult();
    }
}