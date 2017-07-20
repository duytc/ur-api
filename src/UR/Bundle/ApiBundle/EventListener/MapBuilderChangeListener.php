<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Psr\Log\LoggerInterface;
use UR\Entity\Core\ConnectedDataSource;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\AugmentationMappingService;
use UR\Worker\Manager;

class MapBuilderChangeListener
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Manager */
    protected $workerManager;

    protected $persistEntities = [];

    protected $updateEntities = [];

    /** @var AugmentationMappingService  */
    protected $augmentationMappingService;

    /**
     * MapBuilderChangeListener constructor.
     * @param LoggerInterface $logger
     * @param Manager $workerManager
     * @param AugmentationMappingService $augmentationMappingService
     */
    public function __construct(LoggerInterface $logger, Manager $workerManager, AugmentationMappingService $augmentationMappingService)
    {
        $this->logger = $logger;
        $this->workerManager = $workerManager;
        $this->augmentationMappingService = $augmentationMappingService;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof DataSetInterface) {
            return;
        }

        if (!$entity->isMapBuilderEnabled()) {
            return;
        }

        $this->persistEntities[] = $entity;
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();

        if (!$entity instanceof DataSetInterface) {
            return;
        }

        if ($args->hasChangedField('mapBuilderEnabled')) {
            $connectedDataSourceRepository = $em->getRepository(ConnectedDataSource::class);
            $connectedDataSources = $connectedDataSourceRepository->getConnectedDataSourceByDataSet($entity);

            foreach ($connectedDataSources as $connectedDataSource) {
                if  (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                    continue;
                }
                $em->remove($connectedDataSource);
            }
            $this->workerManager->removeAllDataFromDataSet($entity->getId());
        }

        if (!$entity->isMapBuilderEnabled()) {
            return;
        }

        if (!$args->hasChangedField('mapBuilderConfigs')) {
            return;
        }
        
        $this->updateEntities[] = $entity;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {

    }

    /**
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
    }

    /**
     * @param PostFlushEventArgs $event
     */
    public function postFlush(PostFlushEventArgs $event)
    {
        $em = $event->getEntityManager();
        $allEntities = array_values(array_merge($this->persistEntities, $this->updateEntities));
        $this->persistEntities = [];
        $this->updateEntities = [];

        $count = 0;
        foreach ($allEntities as $entity) {
            if (!$entity instanceof DataSetInterface) {
                continue;
            }

//            $this->workerManager->loadFilesIntoDataSetMapBuilder($entity->getId());

            $entity->increaseNumChanges();
            $em->persist($entity);
            $count++;

            $this->augmentationMappingService->noticeChangesInDataSetMapBuilder($entity, $em);
        }

        if ($count > 0) {
            $em->flush();
        }
    }
}