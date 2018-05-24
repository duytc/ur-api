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

        $oldSheet = $args->getOldValue('sheets');
        sort($oldSheet);
        $newSheet = $args->getNewValue('sheets');
        sort($newSheet);

        $deletedSheets = array_diff($oldSheet, $newSheet);

        $this->dataSources[$entity->getId()] = [
            'deletedSheets' => $deletedSheets,
            'dataSource' => $entity
        ];
    }

    public function postFlush()
    {
        if (count($this->dataSources) < 1) {
            return;
        }

        $dataSources = $this->dataSources;
        $this->dataSources = [];

        foreach ($dataSources as $__dataSource) {
            $deletedSheets = $__dataSource['deletedSheets'];
            $dataSource = $__dataSource['dataSource'];

            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            $dataSourceEntries = $dataSource->getDataSourceEntries();
            $dataSourceEntries = $dataSourceEntries instanceof Collection ? $dataSourceEntries->toArray() : $dataSourceEntries;
            $dataSource->setDetectedFields([]);
            foreach ($dataSourceEntries as $dataSourceEntry) {
                if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                    continue;
                }

                //All jobs related to entry executing in here
                $this->manager->splitHugeFile($dataSourceEntry->getId());

                //Update detetecdFields for datasource
                $this->manager->updateDetectedFieldsWhenDataSourceSheetConfigChange($dataSourceEntry->getId(), $dataSourceEntry->getDataSource()->getId(), $deletedSheets);
            }
        }
    }
}