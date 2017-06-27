<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Service\DateTime\NextExecutedAt;

class UpdateDataSourceIntegrationScheduleListener
{
    /** @var array|DataSourceIntegrationInterface[] */
    private $updateDataSourceIntegrations = [];

    /** @var NextExecutedAt  */
    private $nextExecutedAt;

    public function __construct(NextExecutedAt $nextExecutedAt)
    {
        $this->nextExecutedAt = $nextExecutedAt;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $this->em = $args->getEntityManager();
        $dataSourceIntegration = $args->getEntity();

        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }

        // update all dataSourceIntegrationSchedules
        $dataSourceIntegration = $this->nextExecutedAt->updateDataSourceIntegrationSchedule($dataSourceIntegration, $args->getEntityManager());

        // add to $updateDataSourceIntegrations
        $this->updateDataSourceIntegrations[] = $dataSourceIntegration;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->updateDataSourceIntegrations) < 1) {
            return;
        }

        $em = $args->getEntityManager();
        foreach ($this->updateDataSourceIntegrations as $dataSourceIntegration) {
            $em->persist($dataSourceIntegration);
        }

        // reset $updateDataSourceIntegrations
        $this->updateDataSourceIntegrations = [];

        // flush changes
        $em->flush();
    }
}