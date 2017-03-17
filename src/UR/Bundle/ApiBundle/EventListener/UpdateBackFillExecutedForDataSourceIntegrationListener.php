<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\DataSourceIntegrationInterface;

class UpdateBackFillExecutedForDataSourceIntegrationListener
{
    /** @var array|DataSourceIntegrationInterface[] */
    private $updateDataSourceIntegrations = [];

    public function __construct()
    {
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        // only do encrypt if params changed
        if (!$args->hasChangedField('backFillForce')) {
            return;
        }

        $dataSourceIntegration = $args->getEntity();
        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }

        // update all dataSourceIntegrationSchedules
        $dataSourceIntegration = $this->updateBackFillExecutedDataSourceIntegration($dataSourceIntegration);

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

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @return DataSourceIntegrationInterface
     */
    private function updateBackFillExecutedDataSourceIntegration(DataSourceIntegrationInterface $dataSourceIntegration)
    {
        $isBackFillForce = $dataSourceIntegration->isBackFillForce();
        if ($isBackFillForce) {
            // clear backFill execute
            $dataSourceIntegration->setBackFillExecuted(false);

            // clear backFill force
            $dataSourceIntegration->setBackFillForce(false);
        }

        return $dataSourceIntegration;
    }
}