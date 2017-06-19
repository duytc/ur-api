<?php


namespace UR\Bundle\ApiBundle\EventListener;


use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use UR\Bundle\ApiBundle\Event\ConnectedDataSourceReloadCompletedEvent;
use UR\Bundle\ApiBundle\Event\DataSetReloadCompletedEvent;
use UR\Entity\Core\ConnectedDataSource;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\LinkedMapDataSet;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\LinkedMapDataSetInterface;
use UR\Repository\Core\ConnectedDataSourceRepositoryInterface;
use UR\Repository\Core\DataSetRepositoryInterface;
use UR\Repository\Core\LinkedMapDataSetRepositoryInterface;

class ResetNumberOfChangesListener
{
    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var DataSetRepositoryInterface
     */
    private $dataSetRepository;

    /**
     * @var ConnectedDataSourceRepositoryInterface
     */
    private $connectedDataSourceRepository;

    /**
     * @var LinkedMapDataSetRepositoryInterface
     */
    private $linkedMapDataSetRepository;

    /**
     * ChangesCountingListener constructor.
     * @param EntityManagerInterface $em
     * @param LoggerInterface $logger
     */
    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->dataSetRepository = $em->getRepository(DataSet::class);
        $this->connectedDataSourceRepository = $em->getRepository(ConnectedDataSource::class);
        $this->linkedMapDataSetRepository = $em->getRepository(LinkedMapDataSet::class);
    }

    /**
     * @param DataSetReloadCompletedEvent $event
     */
    public function onDataSetReloadCompleted(DataSetReloadCompletedEvent $event)
    {
        $dataSetId = $event->getDataSetId();
        $this->logger->notice(sprintf('Received DataSetReloadCompletedEvent with Data Set %d', $dataSetId));
        $dataSet = $this->dataSetRepository->find($dataSetId);
        if (!$dataSet instanceof DataSetInterface) {
            $this->logger->notice(sprintf('Received DataSetReloadCompletedEvent but no such Data Set %d', $dataSetId));
            return;
        }

        $dataSet
            ->setNumChanges(0)
            ->setNumConnectedDataSourceChanges(0);

        $this->em->merge($dataSet);
        $this->em->flush();
    }

    /**
     * @param ConnectedDataSourceReloadCompletedEvent $event
     */
    public function onConnectedDataSourceReloadCompleted(ConnectedDataSourceReloadCompletedEvent $event)
    {
        $connectedDataSourceId = $event->getConnectedDataSourceId();
        $this->logger->notice(sprintf('Received ConnectedDataSourceReloadCompletedEvent with Connected Data Source %d', $connectedDataSourceId));
        $connectedDataSource = $this->connectedDataSourceRepository->find($connectedDataSourceId);
        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            $this->logger->notice(sprintf('Received ConnectedDataSourceReloadCompletedEvent but no such Connected Data Source %d', $connectedDataSourceId));
            return;
        }

        // update the connected data source and its data set
        $oldConnectedDataSourceNumChanges = $connectedDataSource->getNumChanges();

        $connectedDataSource->setNumChanges(0);
        $this->em->merge($connectedDataSource);

        $dataSet = $connectedDataSource->getDataSet();
        $dataSet->decreaseNumConnectedDataSourceChanges($oldConnectedDataSourceNumChanges);

        if ($dataSet->getNumConnectedDataSourceChanges() === 0) {
            // also reset data set numChanges when all connected data source have no changes
            $dataSet->setNumChanges(0);
        }

        $this->em->merge($dataSet);

        //update related connected data sources (Augmentation transform)
        $linkedMapDataSets = $this->linkedMapDataSetRepository->getByMapDataSet($dataSet);
        if (count($linkedMapDataSets) < 1) {
            // flush before to save changes before quit
            $this->em->flush();

            return;
        }

        /** @var LinkedMapDataSetInterface $linkedMapDataSet */
        foreach ($linkedMapDataSets as $linkedMapDataSet) {
            $relatedConnectedDataSource = $linkedMapDataSet->getConnectedDataSource();
            if (!$relatedConnectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $relatedConnectedDataSource->increaseNumChanges();
            $this->em->merge($relatedConnectedDataSource);

            $relatedDataSet = $relatedConnectedDataSource->getDataSet();
            $relatedDataSet->increaseNumConnectedDataSourceChanges();
            $this->em->merge($relatedDataSet);
        }

        $this->em->flush();
    }
}