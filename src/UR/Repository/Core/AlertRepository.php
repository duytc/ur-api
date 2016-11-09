<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;

class AlertRepository extends EntityRepository implements AlertRepositoryInterface
{
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