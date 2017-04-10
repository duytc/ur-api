<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\ModelInterface;
use UR\Service\Import\LoadingDataService;
use UR\Service\Parser\Transformer\Augmentation;
use UR\Service\Parser\Transformer\TransformerFactory;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * when a file received or be replayed, doing import
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReImportWhenConnectedDataSourceEntryInsertedListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];

    /** @var ContainerInterface $container */
    private $container;

    private $transformFactory;

    function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->transformFactory = new TransformerFactory();
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $this->insertedEntities = array_merge($this->insertedEntities, $uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates());

        $this->insertedEntities = array_filter($this->insertedEntities, function ($entity) {
            return $entity instanceof ConnectedDataSourceInterface;
        });
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->insertedEntities) < 1) {
            return;
        }

        $connectedDataSources = [];
        $entryIds = [];
        foreach ($this->insertedEntities as $entity) {
            if (!$entity instanceof ConnectedDataSourceInterface) {
                continue;
            }

            if (!$entity->isReplayData()) {
                continue;
            }

            if ($entity->getDataSource()->getEnable()) {
                /** @var Collection|DataSourceEntryInterface[] $dataSourceEntries */
                $dataSourceEntries = $entity->getDataSource()->getDataSourceEntries();
                if ($dataSourceEntries instanceof Collection) {
                    $dataSourceEntries = $dataSourceEntries->toArray();
                }

                foreach ($dataSourceEntries as $dataSourceEntry) {
                    $entryIds[] = $dataSourceEntry->getId();
                }

                $connectedDataSources[] = $entity;
            }
        }

        // reset for new onFlush event
        $this->insertedEntities = [];

        /** @var LoadingDataService */
        $loadingDataService = $this->container->get('ur.service.loading_data_service');
        $loadingDataService->doLoadDataFromEntryToDataBase($connectedDataSources, $entryIds);
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $em = $args->getEntityManager();

        if (!$entity instanceof ConnectedDataSourceInterface) {
            return;
        }

        $transforms = $entity->getTransforms();
        $augmentationTransforms = $this->getAugmentationTransforms($transforms);
        $linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);
        /**
         * @var Augmentation[] $augmentationTransforms
         */
        foreach ($augmentationTransforms as $insertedAugmentationTransform) {
            $linkedMapDataSetRepository->override($insertedAugmentationTransform->getMapDataSet(), $entity, $insertedAugmentationTransform->getMapFields());
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();
        $entity = $args->getEntity();

        if (!$entity instanceof ConnectedDataSourceInterface) {
            return;
        }

        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($entity);

        if (!array_key_exists('transforms', $changedFields)) {
            return;
        }

        $values = $changedFields['transforms'];
        /**
         * @var Augmentation[] $beforeAugmentationTransforms
         */
        $beforeAugmentationTransforms = $this->getAugmentationTransforms($values[0]);

        /**
         * @var Augmentation[] $afterAugmentationTransforms
         */
        $afterAugmentationTransforms = $this->getAugmentationTransforms($values[1]);

        $linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);
        /*
         * delete all linked data set with this connected data source
         */
        if (count($beforeAugmentationTransforms) > 0) {
            $linkedMapDataSetRepository->deleteByConnectedDataSource($entity->getId());
        }

        /*
         * add augmentation transform
         */
        /**
         * @var Augmentation[] $afterAugmentationTransforms
         */
        foreach ($afterAugmentationTransforms as $insertedAugmentationTransform) {
            $linkedMapDataSetRepository->override($insertedAugmentationTransform->getMapDataSet(), $entity, $insertedAugmentationTransform->getMapFields());
        }
    }

    private function getAugmentationTransforms(array $transforms)
    {
        $result = [];
        foreach ($transforms as $transform) {
            $transformObjects = $this->transformFactory->getTransform($transform);

            if (!is_array($transformObjects)) {
                continue;
            }

            foreach ($transformObjects as $transformObject) {
                if ($transformObject instanceof Augmentation) {
                    $result[] = $transformObject;
                }
            }
        }

        return $result;
    }
}