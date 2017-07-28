<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
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
        $dataSourceIntegration = $args->getEntity();

        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }

        // update all dataSourceIntegrationSchedules
        $dataSourceIntegration = $this->nextExecutedAt->updateDataSourceIntegrationScheduleForDataSourceIntegration($dataSourceIntegration);

        // add to $updateDataSourceIntegrations
        $this->updateDataSourceIntegrations[] = $dataSourceIntegration;
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $dataSourceIntegration = $args->getEntity();

        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }

        // update all dataSourceIntegrationSchedules
        $dataSourceIntegration = $this->nextExecutedAt->updateDataSourceIntegrationScheduleForDataSourceIntegration($dataSourceIntegration);

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