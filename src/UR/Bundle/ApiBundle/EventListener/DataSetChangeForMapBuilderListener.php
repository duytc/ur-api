<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Entity\Core\MapBuilderConfig;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Service\DataSet\AugmentationMappingService;
use UR\Service\PublicSimpleException;
use UR\Worker\Manager;

class DataSetChangeForMapBuilderListener
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Manager */
    protected $workerManager;

    /** @var AugmentationMappingService  */
    protected $augmentationMappingService;

    /**
     * MapBuilderChangeListener constructor.
     * @param LoggerInterface $logger
     * @param Manager $workerManager
     * @param AugmentationMappingService $augmentationMappingService
     */
    public function __construct(LoggerInterface $logger, Manager $workerManager, AugmentationMappingService $augmentationMappingService)
    {
        $this->logger = $logger;
        $this->workerManager = $workerManager;
        $this->augmentationMappingService = $augmentationMappingService;
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Exception
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();
        $dataSet = $args->getEntity();

        if (!$dataSet instanceof DataSetInterface) {
            return;
        }
        /** Get MapBuilders from deleted data set*/
        $mapBuilderRepository = $em->getRepository(MapBuilderConfig::class);
        $mapBuilderConfigs = $mapBuilderRepository->getByMapDataSet($dataSet);

        if (!empty($mapBuilderConfigs)) {
            $message = sprintf("You need remove this data set from %s Map Builder Config before delete %s. Check on ",
                count($mapBuilderConfigs),
                $dataSet->getName());
            foreach ($mapBuilderConfigs as $mapBuilderConfig) {
                if (!$mapBuilderConfig instanceof MapBuilderConfigInterface) {
                    continue;
                }
                $dataSet = $mapBuilderConfig->getDataSet();
                $message = sprintf("%s (%s ,Id %s)", $message, $dataSet->getName(), $dataSet->getId());
            }
            throw new PublicSimpleException($message);
        }

        /** Get LinkMapDataSets */
        $linkMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);
        $linkedMapDataSets = $linkMapDataSetRepository->getByMapDataSet($dataSet);

        if (!empty($linkedMapDataSets)) {
            $message = sprintf("You need remove this data set from %s Augmentation before delete %s. Check on ",
                count($linkedMapDataSets),
                $dataSet->getName());
            foreach ($linkedMapDataSets as $linkedMapDataSet) {
                if (!$linkedMapDataSet instanceof LinkedMapDataSetInterface) {
                    continue;
                }
                $dataSet = $linkedMapDataSet->getMapDataSet();
                $message = sprintf("%s (%s ,Id %s)", $message, $dataSet->getName(), $dataSet->getId());
            }
            throw new PublicSimpleException($message);
        }

        $this->augmentationMappingService->noticeChangesInLeftRightMapBuilder($dataSet, $em);
        $this->augmentationMappingService->noticeChangesInDataSetMapBuilder($dataSet, $em);
    }
}