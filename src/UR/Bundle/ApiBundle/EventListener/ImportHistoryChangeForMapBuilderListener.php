<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use UR\Entity\Core\MapBuilderConfig;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Model\Core\MapBuilderConfigInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Worker\Manager;

class ImportHistoryChangeForMapBuilderListener
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Manager */
    protected $workerManager;
    
    /**
     * MapBuilderChangeListener constructor.
     * @param LoggerInterface $logger
     * @param Manager $workerManager
     */
    public function __construct(LoggerInterface $logger, Manager $workerManager)
    {
        $this->logger = $logger;
        $this->workerManager = $workerManager;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $em = $args->getEntityManager();
        $importHistory = $args->getEntity();

        if (!$importHistory instanceof ImportHistoryInterface) {
            return;
        }

        $mapDataSet = $importHistory->getDataSet();
        $mapBuilderRepository = $em->getRepository(MapBuilderConfig::class);

        $mapBuilderConfigs = $mapBuilderRepository->getByMapDataSet($mapDataSet);

        foreach ($mapBuilderConfigs as $mapBuilderConfig) {
            if (!$mapBuilderConfig instanceof MapBuilderConfigInterface) {
                continue;
            }

            $dataSet = $mapBuilderConfig->getDataSet();
            $this->removeImportHistoryFromMapBuilderDataSet($importHistory, $dataSet, $em);
            $this->workerManager->updateTotalRowsForDataSet($dataSet);
        }
    }

    /**
     * @param ImportHistoryInterface $importHistory
     * @param DataSetInterface $dataSet
     * @param EntityManagerInterface $em
     */
    private function removeImportHistoryFromMapBuilderDataSet(ImportHistoryInterface $importHistory, DataSetInterface $dataSet, EntityManagerInterface $em)
    {
        $conn = $em->getConnection();
        $synchronize = new Synchronizer($conn, new Comparator());

        $dataTable = $synchronize->getDataSetImportTable($dataSet->getId());

        if (!$dataTable) {
            return;
        }

        $qb = $conn->createQueryBuilder();
        $qb->delete($conn->quoteIdentifier($dataTable->getName()));

        $qb
            ->where(sprintf('%s = :importHistory', $conn->quoteIdentifier(DataSetInterface::IMPORT_ID_COLUMN)))
            ->setParameter('importHistory', $importHistory->getId());

        try {
            $qb->execute();
        } catch (\Exception $e) {

        }
    }
}