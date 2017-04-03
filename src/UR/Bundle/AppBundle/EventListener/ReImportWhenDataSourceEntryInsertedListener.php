<?php

namespace UR\Bundle\AppBundle\EventListener;


use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\ModelInterface;
use UR\Service\Import\LoadingDataService;

/**
 * Class ConnectedDataSourceChangeListener
 *
 * when a file received or be replayed, doing import
 *
 * @package UR\Bundle\AppBundle\EventListener
 */
class ReImportWhenDataSourceEntryInsertedListener
{
    /**
     * @var array|ModelInterface[]
     */
    protected $insertedEntities = [];

    /** @var LoadingDataService */
    private $loadingDataService;

    function __construct(LoadingDataService $loadingDataService)
    {
        $this->loadingDataService = $loadingDataService;
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $this->insertedEntities = array_merge($this->insertedEntities, $uow->getScheduledEntityInsertions());

        $this->insertedEntities = array_filter($this->insertedEntities, function ($entity) {
            return $entity instanceof DataSourceEntryInterface;
        });
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);

        if (count($this->insertedEntities) < 1) {
            return;
        }

        foreach ($this->insertedEntities as $entity) {
            if (!$entity instanceof DataSourceEntryInterface) {
                continue;
            }

            if ($entity->getDataSource()->getEnable()) {
                $this->loadingDataService->doLoadDataFromEntryToDataBase($entity, $linkedMapDataSetRepository);
            }
        }

        // reset for new onFlush event
        $this->insertedEntities = [];
    }

}