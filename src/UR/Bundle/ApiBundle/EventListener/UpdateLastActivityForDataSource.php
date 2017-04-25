<?php

namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;

/**
 * Class UpdateLastActivityForDataSource
 * update last Activity for data source when data source entry inserted
 */
class UpdateLastActivityForDataSource
{
    /**
     * @var array|DataSourceInterface[]
     */
    protected $dataSources = [];

    public function postPersist(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $this->dataSources[] = $dataSourceEntry->getDataSource();
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $this->dataSources[] = $dataSourceEntry->getDataSource();
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->dataSources) < 1) {
            return;
        }

        $em = $args->getEntityManager();

        $uniqueDataSources = [];
        foreach ($this->dataSources as $dataSource) {
            $uniqueDataSources[$dataSource->getId()] = $dataSource;
        }

        foreach ($uniqueDataSources as $uniqueDataSource) {
            $uniqueDataSource->setLastActivity(new \DateTime());
            $em->persist($uniqueDataSource);
        }

        $this->dataSources = [];

        $em->flush();
    }
}