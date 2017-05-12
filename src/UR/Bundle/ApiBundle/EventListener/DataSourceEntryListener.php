<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DataSource\DataSourceType;
use UR\Worker\Manager;

class DataSourceEntryListener
{
    /**
     * @var Manager
     */
    protected $workerManager;

    /**
     * @var
     */
    protected $uploadFileDir;

    /**
     * DataSourceEntryListener constructor.
     * @param Manager $workerManager
     * @param $uploadFileDir
     */
    public function __construct(Manager $workerManager, $uploadFileDir)
    {
        $this->workerManager = $workerManager;
        $this->uploadFileDir = $uploadFileDir;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof DataSourceEntryInterface) {
            return;
        }

        if (in_array($entity->getDataSource()->getFormat(), DataSourceType::$CSV_TYPES)) {
            $this->workerManager->fixWindowLineFeed($this->uploadFileDir . $entity->getPath());
        }
    }
}