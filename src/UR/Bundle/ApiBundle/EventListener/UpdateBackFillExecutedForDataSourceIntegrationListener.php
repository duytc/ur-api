<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Entity\Core\DataSourceIntegrationBackfillHistory;
use UR\Model\Core\DataSourceIntegrationInterface;

class UpdateBackFillExecutedForDataSourceIntegrationListener
{
    /** @var array|DataSourceIntegrationInterface[] */
    private $updateDataSourceIntegrations = [];

    /** @var  EntityManager */
    protected $em;

    public function __construct()
    {
    }

    /**
     * handle event postPersist one dataset, this auto create empty data import table with name __data_import_{dataSetId}
     *
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->em = $args->getEntityManager();

        if (!$entity instanceof DataSourceIntegrationInterface) {
            return;
        }
        if ($entity->getBackFillStartDate()) {
            $this->createBackFillHistoryWhenCreateDataSourceIntegration($entity);
            $entity->setBackFillStartDate(null);
            $entity->setBackFillEndDate(null);
            $this->updateDataSourceIntegrations[] = $entity;
        }
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $this->em = $args->getEntityManager();

        $dataSourceIntegration = $args->getEntity();
        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }
        // create backfill history when has the change on Data Source Integration
        if ($args->hasChangedField('backFillStartDate') || $args->hasChangedField('backFillEndDate')) {
            $this->updateDataSourceIntegrations[] = $this->createBackFillHistoryForDataSourceIntegration($dataSourceIntegration);
            $dataSourceIntegration->setBackFillStartDate(null);
            $dataSourceIntegration->setBackFillEndDate(null);
        }

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
    private function createBackFillHistoryForDataSourceIntegration(DataSourceIntegrationInterface $dataSourceIntegration)
    {
        $backfillHistory = (new DataSourceIntegrationBackfillHistory())
            ->setDataSourceIntegration($dataSourceIntegration)
            ->setBackFillStartDate($dataSourceIntegration->getBackFillStartDate())
            ->setBackFillEndDate($dataSourceIntegration->getBackFillEndDate());

        return $backfillHistory;
    }

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @return DataSourceIntegrationInterface
     */
    private function createBackFillHistoryWhenCreateDataSourceIntegration(DataSourceIntegrationInterface $dataSourceIntegration)
    {
        $backfillHistory = (new DataSourceIntegrationBackfillHistory())
            ->setDataSourceIntegration($dataSourceIntegration)
            ->setBackFillStartDate($dataSourceIntegration->getBackFillStartDate())
            ->setBackFillEndDate($dataSourceIntegration->getBackFillEndDate());

        $this->em->persist($backfillHistory);
    }
}