<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Service\DateTime\DateRangeServiceInterface;
use UR\Worker\Manager;

/**
 * Class UpdateNumOfFileWhenDataSourceEntryInsertedOrDeletedListener
 *
 * when a file received or be deleted, update numOfFiles
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class UpdateDateRangeForDataSourceListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];

    /** @var Manager $workerManager */
    private $workerManager;

    /**
     * @var DateRangeServiceInterface
     */
    protected $dateRangeService;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        /** @var DataSourceInterface $dataSource */
        $dataSource = $args->getEntity();
        if (!$dataSource instanceof DataSourceInterface) {
            return;
        }

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        $changedFields = $uow->getEntityChangeSet($dataSource);

        if (
            !array_key_exists('dimensions', $changedFields) &&
            !array_key_exists('dateFields', $changedFields) &&
            !array_key_exists('dateFormats', $changedFields) &&
            !array_key_exists('dateRangeDetectionEnabled', $changedFields) &&
            !array_key_exists('dateRange', $changedFields)
        ) {
            return;
        }

        $this->insertedEntities[] = $dataSource;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        /** @var \UR\Model\Core\DataSourceInterface $dataSource */
        foreach ($this->insertedEntities as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }
            $entries = $dataSource->getDataSourceEntries();
            foreach ($entries as $entry) {
                if (!$entry instanceof DataSourceEntryInterface) {
                    continue;
                }
                $this->workerManager->updateDateRangeForDataSourceEntry($dataSource->getId(), $entry->getId());
            }

            $this->workerManager->updateDateRangeForDataSource($dataSource->getId());
        }
    }
}