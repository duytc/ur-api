<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\StringUtilTrait;
use UR\Worker\Manager;

class UpdateMetricsAndDimensionsForReportViewListener
{
    /**
     * @var array
     */
    protected $changedEntities;

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * UpdateMetricsAndDimensionsForReportViewListener constructor.
     * @param Manager $manager
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }


    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof ReportViewInterface) {
            return;
        }

        $this->changedEntities[] = $entity;
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof ReportViewInterface) {
            return;
        }

        if ($args->hasChangedField('dataSets') || $args->hasChangedField('transforms')) {
            $this->changedEntities[] = $entity;
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->changedEntities) < 1) {
            return;
        }

        /**
         * @var ReportViewInterface $entity
         */
        foreach($this->changedEntities as $entity) {
            $this->manager->updateDimensionsAndMetricsForReportView($entity->getId());
        }

        $this->changedEntities = [];
    }
}