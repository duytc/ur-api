<?php

namespace UR\Repository\Core;

use Doctrine\ORM\EntityRepository;
use UR\Bundle\UserSystem\PublisherBundle\Entity\User;
use UR\Model\Core\IntegrationInterface;

class IntegrationPublisherRepository extends EntityRepository implements IntegrationPublisherRepositoryInterface
{
    /**
     * @param IntegrationInterface $integration
     * @return mixed
     */
    public function getByIntegration(IntegrationInterface $integration)
    {
        return $this->createQueryBuilder('ip')
            ->where('ip.integration = :integration')
            ->setParameter('integration', $integration)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User $publisher
     * @return mixed
     */
    public function getByPublisher(User $publisher)
    {
        return $this->createQueryBuilder('ip')
            ->where('ip.publisher = :publisher')
            ->setParameter('publisher', $publisher)
            ->getQuery()
            ->getResult();
    }
}