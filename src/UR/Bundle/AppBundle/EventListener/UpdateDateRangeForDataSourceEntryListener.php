<?php

namespace UR\Bundle\AppBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Worker\Manager;

class UpdateDateRangeForDataSourceEntryListener
{
    /**
     * @var Manager
     */
    protected $workerManager;

    protected $newEntries;

    /**
     * DataSourceEntryListener constructor.
     * @param Manager $workerManager
     */
    public function __construct(Manager $workerManager)
    {
        $this->workerManager = $workerManager;
        $this->newEntries = [];
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postRemove(LifecycleEventArgs $args)
    {
        $dataSourceEntry = $args->getEntity();

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return;
        }

        $changedDataSource = $dataSourceEntry->getDataSource();
        if ($changedDataSource->isDateRangeDetectionEnabled()) {
            $this->workerManager->updateDateRangeForDataSource($changedDataSource->getId());
        }
    }
}