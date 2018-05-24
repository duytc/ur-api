<?php

namespace UR\Repository\Core;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

class OptimizationIntegrationRepository extends EntityRepository implements OptimizationIntegrationRepositoryInterface
{
    protected $SORT_FIELDS = ['id' => 'id', 'name' => 'name'];

    /**
     * @inheritdoc
     */
    public function getOptimizationIntegrationsForOptimizationRuleQuery($user, $optimizationRuleId, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);
        $qb
            ->andWhere('opc.optimizationRule = :optimizationRuleId')
            ->setParameter('optimizationRuleId', $optimizationRuleId);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('opc.name', ':searchKey'),
                    $qb->expr()->like('opc.id', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('opc.' . $param->getSortField(), $param->getSortDirection());
                    break;

                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('opc.' . $param->getSortField(), $param->getSortDirection());
                    break;

                default:
                    break;
            }
        }

        return $qb;
    }

    /**
     * @param UserRoleInterface $user
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function createQueryBuilderForUser(UserRoleInterface $user)
    {
        return $user instanceof PublisherInterface ? $this->getOptimizationRulesForPublisherQuery($user) : $this->createQueryBuilder('opc');
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationRulesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        $publisherId = $publisher->getId();

        $qb = $this->createQueryBuilder('opc')
            ->leftJoin('opc.optimizationRule', 'opr')
            ->where('opr.publisher = :publisherId')
            ->setParameter('publisherId', $publisherId, Type::INTEGER);

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
    public function getOptimizationIntegrationsQuery($user, PagerParam $param)
    {
        $qb = $this->createQueryBuilderForUser($user);

        if (is_string($param->getSearchKey())) {
            $searchLike = sprintf('%%%s%%', $param->getSearchKey());
            $qb
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('opc.name', ':searchKey'),
                    $qb->expr()->like('opc.id', ':searchKey')
                ))
                ->setParameter('searchKey', $searchLike);
        }

        if (is_string($param->getSortField()) &&
            is_string($param->getSortDirection()) &&
            in_array($param->getSortDirection(), ['asc', 'desc', 'ASC', 'DESC']) &&
            in_array($param->getSortField(), $this->SORT_FIELDS)
        ) {
            switch ($param->getSortField()) {
                case $this->SORT_FIELDS['id']:
                    $qb->addOrderBy('opc.' . $param->getSortField(), $param->getSortDirection());
                    break;
                case $this->SORT_FIELDS['name']:
                    $qb->addOrderBy('opc.' . $param->getSortField(), $param->getSortDirection());
                    break;
                default:
                    break;
            }
        }

        return $qb;
    }

    /**
     * @inheritdoc
     */
    public function hasOptimizationIntegrations(OptimizationRuleInterface $optimizationRule)
    {
        $optimizationIntegrations = $this->createQueryBuilder('opc')
            ->where('opc.optimizationRule = :optimizationRule')
            ->setParameter('optimizationRule', $optimizationRule)
            ->getQuery()
            ->getResult();

        return count($optimizationIntegrations) > 0;
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationIntegrationAdSlotIds($optimizationIntegrationId = null)
    {
        $optimizationIntegrations = $this->createQueryBuilder('opc');
        if (is_numeric($optimizationIntegrationId))
            $optimizationIntegrations->where($optimizationIntegrations->expr()->notIn('opc.id', $optimizationIntegrationId));

        $optimizationIntegrations = $optimizationIntegrations->getQuery()->getResult();

        $adSlotIds = [];
        foreach ($optimizationIntegrations as $optimizationIntegration) {
            if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }
            $adSlotIds = array_merge($adSlotIds, $optimizationIntegration->getAdSlots());
        }

        return $adSlotIds;
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationIntegrationWaterFallTagIds($optimizationIntegrationId = null)
    {
        $optimizationIntegrations = $this->createQueryBuilder('opc');
        if (is_numeric($optimizationIntegrationId))
            $optimizationIntegrations->where($optimizationIntegrations->expr()->notIn('opc.id', $optimizationIntegrationId));

        $optimizationIntegrations = $optimizationIntegrations->getQuery()->getResult();

        $waterfallTagIds = [];
        foreach ($optimizationIntegrations as $optimizationIntegration) {
            if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }
            $waterfallTagIds = array_merge($waterfallTagIds, $optimizationIntegration->getWaterfallTags());
        }

        $waterfallTagIds = array_values(array_unique($waterfallTagIds));

        return $waterfallTagIds;
    }

    /**
     * @inheritdoc
     */
    public function getSegmentsByAdSlotId($adSlotId)
    {
        if (empty($adSlotId)) {
            return [];
        }

        $optimizationIntegrations = $this->createQueryBuilder('opc');

        $optimizationIntegrations = $optimizationIntegrations->getQuery()->getResult();

        foreach ($optimizationIntegrations as $optimizationIntegration) {
            if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }

            if (in_array($adSlotId, $optimizationIntegration->getAdSlots())) {
                return $optimizationIntegration;
            }
        }

        return [];
    }
}