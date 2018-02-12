<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\ModelInterface;
use UR\Service\DataSource\DataSourceType;
use UR\Worker\Manager;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * when a file received or be replayed, doing import
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class UpdateDetectedFieldsForDataSourceEntryListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];
    protected $deletedEntities = [];

    /** @var Manager */
    private $workerManager;

    function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
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

    public function postRemove(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $this->deletedEntities[] = $dataSourceEntry;
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->insertedEntities) < 1 && count($this->deletedEntities) < 1) {
            return;
        }

        /** @var DataSourceEntryInterface $dataSourceEntry */
        foreach ($this->deletedEntities as $dataSourceEntry) {
            // important: data source entry may be deleted by cascade when data source is deleted
            // so that, make sure the data source is existed before do other actions

            $dataSource = $dataSourceEntry->getDataSource();
            if (empty($dataSource->getId())) {
                continue;
            }

            $chunkPaths = array();
            if (!empty($dataSourceEntry->getChunks()) && is_array($dataSourceEntry->getChunks())) {
                $chunkPaths = $dataSourceEntry->getChunks();
            }
            $dataSourceTypeExtension = DataSourceType::getOriginalDataSourceType(pathinfo($dataSourceEntry->getPath(), PATHINFO_EXTENSION));
            $this->workerManager->updateDetectedFieldsWhenEntryDeleted($dataSourceTypeExtension, $dataSourceEntry->getPath(), $chunkPaths, $dataSource->getId());
        }

        $em = $args->getEntityManager();

        foreach ($this->insertedEntities as &$dataSourceEntry) {
            $em->persist($dataSourceEntry);
        }

        $dataSourceEntriesToBeDetectedFields = array_map(function ($dataSourceEntry) {
            /** @var DataSourceEntryInterface $dataSourceEntry */
            return [
                'dataSourceEntryId' => $dataSourceEntry->getId(),
                'dataSourceId' => $dataSourceEntry->getDataSource()->getId()
            ];
        }, $this->insertedEntities);

        // reset for new onFlush event
        $this->insertedEntities = [];
        $this->deletedEntities = [];

        try {
            // flush changes
            $em->flush();
        } catch (\Exception $e) {

        }

        foreach ($dataSourceEntriesToBeDetectedFields as $dataSourceEntryToBeDetectedFields) {
            $this->workerManager->updateDetectedFieldsWhenEntryInserted($dataSourceEntryToBeDetectedFields['dataSourceEntryId'], $dataSourceEntryToBeDetectedFields['dataSourceId']);
        }
    }
}