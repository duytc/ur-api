<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Service\Import\LoadingDataService;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * when a file received or be replayed, doing import
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReImportWhenDataSourceEntryInsertedListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];

    /** @var ContainerInterface $container */
    private $container;

    function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $this->insertedEntities[] = $dataSourceEntry;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->insertedEntities) < 1) {
            return;
        }

        $entryIds = [];
        foreach ($this->insertedEntities as $entity) {
            if (!$entity instanceof DataSourceEntryInterface) {
                continue;
            }

            if ($entity->getDataSource()->getEnable()) {
                $entryIds[] = $entity->getId();
                $dataSource = $entity->getDataSource();
            }
        }

        // reset for new onFlush event
        $this->insertedEntities = [];

        if (count($entryIds) > 0) {
            /** @var LoadingDataService */
            $loadingDataService = $this->container->get('ur.service.loading_data_service');

            /**
             * @var  DataSourceInterface $dataSource
             */
            foreach ($dataSource->getConnectedDataSources() as $connectedDataSource) {
                $loadingDataService->doLoadDataFromEntryToDataBase($connectedDataSource, $entryIds);
            }
        }
    }
}