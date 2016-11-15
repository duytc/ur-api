<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class AlertRepository extends EntityRepository implements AlertRepositoryInterface
{
    public function getAlertsForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('a')
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

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());

            $orX = $qb->expr()->orX();
            $conditions = array(
                $qb->expr()->like('a.id', ':searchKey'),
                $qb->expr()->like('a.title', ':searchKey'),
                $qb->expr()->like('a.code', ':searchKey')
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
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['createdDate']:
                    $qb->addOrderBy('dse.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
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
}