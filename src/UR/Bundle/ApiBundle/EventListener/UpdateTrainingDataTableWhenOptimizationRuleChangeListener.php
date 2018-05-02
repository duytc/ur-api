<?php
namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\DynamicTable\DynamicTableService;
use UR\Service\OptimizationRule\DataTrainingTableService;
use UR\Worker\Manager;

class UpdateTrainingDataTableWhenOptimizationRuleChangeListener
{
    protected $changedOptimizationRules;

    private $batchSize;
    /**
     * @var Manager
     */
    private $manager;

    /**
     * UpdateTrainingDataTableWhenOptimizationRuleChangeListener constructor.
     * @param Manager $manager
     * @param $batchSize
     */
    public function __construct(Manager $manager, $batchSize)
    {
        $this->manager = $manager;
        $this->batchSize = $batchSize;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $optimizationRule = $args->getEntity();

        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return;
        }

        $this->changedOptimizationRules[] = $optimizationRule;
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $optimizationRule = $args->getEntity();

        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return;
        }

        $this->changedOptimizationRules[] = $optimizationRule;
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Doctrine\DBAL\DBALException
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $optimizationRule = $args->getEntity();
        $em = $args->getEntityManager();
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return;
        }

        $dynamicTableService = new DynamicTableService($em, $this->batchSize);
        $dataTrainingTableService = new DataTrainingTableService($dynamicTableService);

        $dataTrainingTableService->deleteDataTrainingTable($optimizationRule);
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (empty($this->changedOptimizationRules)) {
            return;
        }

        $changedOptimizationRules = $this->changedOptimizationRules;
        $this->changedOptimizationRules = [];

        foreach ($changedOptimizationRules as $optimizationRule) {
            if (!$optimizationRule instanceof OptimizationRuleInterface) {
                continue;
            }

            $this->manager->syncTrainingDataAndGenerateLearnerModel($optimizationRule->getId());
        }
    }
}