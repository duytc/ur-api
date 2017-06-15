<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\DataSourceType;

/**
 * Auto switch data source supported format when a file received and data source has no entry before
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class AutoSwitchFormatForDataSourceListener
{
    /**
     * @var array|DataSourceInterface[]
     */
    protected $dataSources = [];

    public function prePersist(LifecycleEventArgs $args)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $args->getEntity();
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $dataSource = $dataSourceEntry->getDataSource();

        // automatically update data source format if has no entry before
        if (count($dataSource->getDataSourceEntries()) < 1) {
            $this->updateNewFormatForDataSourceDueToFileExtension($dataSource, $dataSourceEntry->getFileExtension());
        }
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->dataSources) < 1) {
            return;
        }

        $em = $args->getEntityManager();

        foreach ($this->dataSources as $dataSource) {
            $em->persist($dataSource);
        }

        // clear before flush
        $this->dataSources = [];

        $em->flush();
    }

    private function updateNewFormatForDataSourceDueToFileExtension(DataSourceInterface $dataSource, $fileExtension)
    {
        $fileExtension = DataSourceType::getOriginalDataSourceType($fileExtension);

        // do not need update if same format
        if ($dataSource->getFormat() == $fileExtension) {
            return;
        }

        $dataSource->setFormat($fileExtension);

        $this->dataSources[] = $dataSource;
    }
}