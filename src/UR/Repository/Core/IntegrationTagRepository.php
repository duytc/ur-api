<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Model\Core\IntegrationInterface;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\PublisherInterface;

class IntegrationTagRepository extends EntityRepository implements IntegrationTagRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function findByIntegration(IntegrationInterface $integration) {
        return $this->createQueryBuilder('it')
            ->where('it.integration = :integration')
            ->setParameter('integration', $integration)->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag) {
        return $this->createQueryBuilder('it')
            ->where('it.tag = :tag')
            ->setParameter('tag', $tag)->getQuery()->getResult();
    }

    /**
     * @inheritdoc
     */
    public function findByPublisher(PublisherInterface $publisher) {
        $qb = $this->createQueryBuilder('it')
            ->leftJoin('it.tag', 't')
            ->leftJoin('t.userTags', 'ut')
            ->where('ut.publisher = :publisher')
            ->setParameter('publisher', $publisher);

        return $qb->getQuery()->getOneOrNullResult();

    }
}