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
 * Class UpdateNumOfFileWhenDataSourceEntryInsertedOrDeletedListener
 *
 * when a file received or be deleted, update numOfFiles
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class UpdateNumOfFileWhenDataSourceEntryInsertedOrDeletedListener
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
        $dataSource = $dataSourceEntry->getDataSource();
        $dataSource->setNumOfFiles($dataSource->getNumOfFiles() + 1);
        $dataSourceEntry->setDataSource($dataSource);
        $this->insertedEntities[] = $dataSourceEntry;
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }
        $dataSource = $dataSourceEntry->getDataSource();
        $dataSource->setNumOfFiles($dataSource->getNumOfFiles() - 1);
        $dataSourceEntry->setDataSource($dataSource);
        $this->insertedEntities[] = $dataSourceEntry;
    }
}