<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Behaviors\OptimizationRuleUtilTrait;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\DynamicTable\DynamicTableService;

class RemoveOptimizationRuleScoreWhenOptimizationRuleChangeListener
{
    use OptimizationRuleUtilTrait;

    /**
     * @var DynamicTableService
     */
    private $dynamicTableService = null;

    /**
     * @param LifecycleEventArgs $args
     * @throws \Doctrine\DBAL\DBALException
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $optimizationRule = $args->getEntity();
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return;
        }

        /* inject $dynamicTableService to $this (if not injected yet) for OptimizationRuleUtilTrait using */
        if (!$this->dynamicTableService instanceof DynamicTableService) {
            $em = $args->getEntityManager();
            $this->dynamicTableService = new DynamicTableService($em);
        }

        /* delete */
        $this->deleteOptimizationRuleScoreTable($optimizationRule);
    }
}