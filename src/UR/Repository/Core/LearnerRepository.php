<?php


namespace UR\Repository\Core;


use Doctrine\ORM\EntityRepository;
use UR\Model\Core\AutoOptimizationConfigInterface;

class LearnerRepository extends EntityRepository implements LearnerRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function getLearnerByAutoOptimizationAndIdentifier(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        $qb = $this->createQueryBuilder("l")
            ->andWhere('l.autoOptimizationConfig = :autoOptimizationConfig')
            ->andWhere('l.identifier = :identifier')
            ->setParameter("autoOptimizationConfig", $autoOptimizationConfig)
            ->setParameter('identifier', $identifier);

        return $qb->getQuery()->getResult();
    }
}