<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\DataSourceEntryInterface;

/**
 * Class UpdateNumOfFileWhenDataSourceEntryInsertedOrDeletedListener
 *
 * when a file received or be deleted, update numOfFiles
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class UpdateNumOfFileWhenDataSourceEntryInsertedOrDeletedListener
{
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
    }
}