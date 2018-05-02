<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\PagerParam;

interface OptimizationIntegrationRepositoryInterface extends ObjectRepository
{
    /**
     * @param $user
     * @param int|$optimizationRuleId
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getOptimizationIntegrationsForOptimizationRuleQuery($user, $optimizationRuleId, PagerParam $param);

    /**
     * @param $user
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getOptimizationIntegrationsQuery($user, PagerParam $param);

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return null|OptimizationRuleInterface
     */
    public function hasOptimizationIntegrations(OptimizationRuleInterface $optimizationRule);

    /**
     * @param int|$optimizationIntegrationId
     * @return array
     */
    public function getOptimizationIntegrationAdSlotIds($optimizationIntegrationId = null);

    /**
     * @param $adSlotId
     * @return array
     */
    public function getSegmentsByAdSlotId($adSlotId);
}