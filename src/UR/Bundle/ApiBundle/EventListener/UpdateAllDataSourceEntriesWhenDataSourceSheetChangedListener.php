<?php

namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Entity\Core\DataSource;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Worker\Manager;

class UpdateAllDataSourceEntriesWhenDataSourceSheetChangedListener
{
    protected $dataSources = [];

    protected $manager;

    /**
     * UpdateDataSourceEntryTotalRowWhenDataSourceSheetChangedListener constructor.
     * @param Manager $manager
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof DataSource) {
            return;
        }

        if (!$args->hasChangedField('sheets')) {
            return;
        }

        $this->dataSources[$entity->getId()] = $entity;
    }

    public function postFlush()
    {
        if (count($this->dataSources) < 1) {
            return;
        }

        $dataSources = $this->dataSources;
        $this->dataSources = [];

        foreach ($dataSources as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            $dataSourceEntries = $dataSource->getDataSourceEntries();
            $dataSourceEntries = $dataSourceEntries instanceof Collection ? $dataSourceEntries->toArray() : $dataSourceEntries;

            foreach ($dataSourceEntries as $dataSourceEntry) {
                if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                    continue;
                }

                //All jobs related to entry executing in here
                $this->manager->splitHugeFile($dataSourceEntry->getId());
            }
        }
    }
}