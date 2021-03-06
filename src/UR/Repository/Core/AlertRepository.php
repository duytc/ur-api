<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\DataSourceInterface;
use UR\Model\AlertPagerParam;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class AlertRepository extends EntityRepository implements AlertRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'createdDate' => 'createdDate', 'title' => 'code'];

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

    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getAlertsForPublisherQuery($user) : $this->createQueryBuilder('a');
    }

    public function getAlertsForUserQuery(UserRoleInterface $user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        // support filter by alert types
        if ($param instanceof AlertPagerParam && !empty($param->getTypes())) {
            $types = explode(',',$param->getTypes());
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
            $types = explode(',',$param->getTypes());
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

                default:
                    break;
            }
        } else {
            $qb->addOrderBy('a.createdDate', 'desc');
        }

        return $qb;
    }

    public function deleteAlertsByIds($ids)
    {
        $qb = $this->createQueryBuilder('a');

        $qb->delete()
            ->where($qb->expr()->in('a.id', $ids));

        return $qb->getQuery()->getResult();
    }

    public function updateMarkAsReadByIds($ids)
    {
        $qb = $this->createQueryBuilder('a');

        $qb->update()
            ->set('a.isRead', 1)
            ->where($qb->expr()->in('a.id', $ids));

        return $qb->getQuery()->getResult();
    }

    public function updateMarkAsUnreadByIds($ids)
    {
        $qb = $this->createQueryBuilder('a');

        $qb->update()
            ->set('a.isRead', 0)
            ->where($qb->expr()->in('a.id', $ids));

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
}