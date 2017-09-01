<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use UR\Entity\Core\ConnectedDataSource;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Repository\Core\ConnectedDataSourceRepositoryInterface;
use UR\Worker\Manager;

class DataSourceEntryChangeForTimeSeriesListener
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Manager */
    protected $workerManager;

    /**
     * MapBuilderChangeListener constructor.
     * @param LoggerInterface $logger
     * @param Manager $workerManager
     */
    public function __construct(LoggerInterface $logger, Manager $workerManager)
    {
        $this->logger = $logger;
        $this->workerManager = $workerManager;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof DataSourceEntryInterface || !$entity->getRemoveHistory()) {
            return;
        }

        $dataSource = $entity->getDataSource();

        /** @var ConnectedDataSourceRepositoryInterface $connectedDataSourceRepository */
        $connectedDataSourceRepository = $args->getEntityManager()->getRepository(ConnectedDataSource::class);

        $connectedDataSources = $connectedDataSourceRepository->getConnectedDataSourceByDataSource($dataSource);

        if (count($connectedDataSources) < 1) {
            /**
             * Can not inject CleanUpDataSourceTimeSeriesService because of circular reference
             * Use worker to run this
             */
            $this->workerManager->removeDuplicatedDateEntries($dataSource->getId());
        }
    }
}