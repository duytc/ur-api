<?php

namespace UR\Bundle\AppBundle\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;

/**
 * Class DeleteImportHistoryWhenDataSourceEntryDeletedListener
 *
 * when a file deleted, delete all import histories on this
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class DeleteImportHistoryWhenDataSourceEntryDeletedListener
{
    public function preRemove(LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $importHistories = $dataSourceEntry->getImportHistories();
        $importHistories = $importHistories instanceof Collection ? $importHistories->toArray() : $importHistories;
        foreach ($importHistories as $importHistory) {
            if (!$importHistory instanceof ImportHistoryInterface) {
                continue;
            }

            $em->remove($importHistory);
        }
    }
}