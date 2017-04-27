<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\ModelInterface;
use UR\Service\Import\LoadingDataService;
use UR\Service\Parser\Transformer\TransformerFactory;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * when a file received or be replayed, doing import
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReImportWhenConnectedDataSourceChangedListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedOrChangedEntities = [];

    /** @var ContainerInterface $container */
    private $container;

    private $transformFactory;

    function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->transformFactory = new TransformerFactory();
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $args->getEntity();

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        $this->insertedOrChangedEntities[] = $connectedDataSource;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $connectedDataSource = $args->getEntity();

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            return;
        }

        // TODO: filter all ConnectedDataSources changed on need-listen fields
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($connectedDataSource);

        // only re-import on need fields
        if (!array_key_exists('requires', $changedFields)
            && !array_key_exists('mapFields', $changedFields)
            && !array_key_exists('filters', $changedFields)
            && !array_key_exists('transforms', $changedFields)
        ) {
            return;
        }

        $this->insertedOrChangedEntities[] = $connectedDataSource;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->insertedOrChangedEntities) < 1) {
            return;
        }

        $loadingConfigs = [];
        foreach ($this->insertedOrChangedEntities as $entity) {
            if (!$entity instanceof ConnectedDataSourceInterface) {
                continue;
            }

            if (!$entity->isReplayData()) {
                continue;
            }

            if ($entity->getLinkedType() === ConnectedDataSourceInterface::LINKED_TYPE_AUGMENTATION) {
                continue;
            }

            if ($entity->getDataSource()->getEnable()) {
                /** @var Collection|DataSourceEntryInterface[] $dataSourceEntries */
                $dataSourceEntries = $entity->getDataSource()->getDataSourceEntries();
                if ($dataSourceEntries instanceof Collection) {
                    $dataSourceEntries = $dataSourceEntries->toArray();
                }

                $entryIds = [];
                foreach ($dataSourceEntries as $dataSourceEntry) {
                    $entryIds[] = $dataSourceEntry->getId();
                }

                $loadingConfigs[] = [
                    'connectedDataSource' => $entity,
                    'entryIds' => $entryIds
                ];
            }
        }

        // reset for new onFlush event
        $this->insertedOrChangedEntities = [];

        /** @var LoadingDataService */
        $loadingDataService = $this->container->get('ur.service.loading_data_service');

        foreach ($loadingConfigs as $loadingConfig) {
            $loadingDataService->doLoadDataFromEntryToDataBase($loadingConfig['connectedDataSource'], $loadingConfig['entryIds']);
        }
    }
}