<?php


namespace UR\DomainManager;


use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface OptimizationRuleManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return OptimizationRuleInterface[]
     */
    public function getOptimizationRulesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    public function getOptimizeFieldName(ModelInterface $optimizationRule);
}