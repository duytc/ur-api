<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Behaviors\LargeReportViewUtilTrait;
use UR\Entity\Core\DataSet;
use UR\Entity\Core\ReportView;
use UR\Entity\Core\ReportViewDataSet;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\ReportViewDataSetInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Repository\Core\DataSetRepositoryInterface;
use UR\Repository\Core\ReportViewDataSetRepositoryInterface;
use UR\Repository\Core\ReportViewRepositoryInterface;
use UR\Service\DataSet\Synchronizer;

class ReportViewChangeForSmallReportListener
{
    use LargeReportViewUtilTrait;

    /** @var  array */
    private $updateReportViewIds = [];

    /** @var array */
    private $updateDataSetIds = [];

    /** @var  Connection */
    private $connection;

    /** @var  Synchronizer */
    private $synchronizer;

    /** @var  int */
    private $largeThreshold;

    /** @var  EntityManagerInterface */
    private $em;

    /**
     * ReportViewChangeForLargeReportListener constructor.
     * @param $largeThreshold
     */
    public function __construct($largeThreshold)
    {
        $this->largeThreshold = $largeThreshold;
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $this->em = $args->getEntityManager();
        $entity = $args->getEntity();

        /** Collecting all report views via report view id and data set id */

        if ($entity instanceof ReportViewInterface) {
            $this->updateReportViewIds[] = $entity->getId();
        }

        if ($entity instanceof ReportViewDataSetInterface) {
            $reportView = $entity->getReportView();
            if ($reportView instanceof ReportViewInterface) {
                $this->updateReportViewIds[] = $reportView->getId();
            }
        }

        if ($entity instanceof DataSetInterface && $args->hasChangedField(DataSetInterface::TOTAL_ROW)) {
            $this->updateDataSetIds[] = $entity->getId();
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $this->em = $args->getEntityManager();
        $entity = $args->getEntity();

        if ($entity instanceof ReportViewDataSetInterface) {
            $reportView = $entity->getReportView();
            if ($reportView instanceof ReportViewInterface) {
                $this->updateReportViewIds[] = $reportView->getId();
            }
        }

        if ($entity instanceof DataSetInterface) {
            $this->updateDataSetIds[] = $entity->getId();
        }
    }

    /**
     * @param PostFlushEventArgs $event
     */
    public function postFlush(PostFlushEventArgs $event)
    {
        $this->em = $event->getEntityManager();
        $this->setConnection($this->getEm()->getConnection());
        /** @var ReportViewRepositoryInterface $reportViewRepository */
        $reportViewRepository = $this->getEm()->getRepository(ReportView::class);

        $reportViewIds = array_merge($this->updateReportViewIds, $this->getReportViewsByUpdateDataSets());
        $reportViewIds = array_unique($reportViewIds);
        $this->updateReportViewIds = [];
        $needFlush = false;

        if (!is_array($reportViewIds)) {
            $reportViewIds = [];
        }

        $allReportViewIds = [];
        foreach ($reportViewIds as $reportViewId) {
            $reportView = $reportViewRepository->find($reportViewId);

            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            $allReportViewIds[] = $reportView->getId();
            $subViews = $reportViewRepository->getSubViewsByReportView($reportView);

            foreach ($subViews as $subView) {
                if (!$subView instanceof ReportViewInterface) {
                    continue;
                }

                $allReportViewIds[] = $subView->getId();
            }
        }

        $allReportViewIds = array_unique($allReportViewIds);

        foreach ($allReportViewIds as $reportViewId) {
            $reportView = $reportViewRepository->find($reportViewId);

            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            if ($this->isLargeReportView($reportView, $this->largeThreshold)) {
                continue;
            }

            /** Delete Pre Calculate Table for small report*/
            $this->getSynchronizer()->deleteTable($reportView->getPreCalculateTable());

            /** Update to small report view */
            $reportView->setSmallReport();

            $this->getEm()->persist($reportView);
            $needFlush = true;
        }

        if ($needFlush) {
            $this->getEm()->flush();
        }
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        if (!$this->connection instanceof Connection) {
            $this->connection = $this->getEm()->getConnection();
        }

        return $this->connection;
    }

    /**
     * @param Connection $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return Synchronizer
     */
    public function getSynchronizer()
    {
        if (!$this->synchronizer instanceof Synchronizer) {
            $conn = $this->getConnection();

            if ($conn instanceof Connection) {
                $this->setSynchronizer(new Synchronizer($conn, new Comparator()));
            }
        }

        return $this->synchronizer;
    }

    /**
     * @param Synchronizer $synchronizer
     */
    public function setSynchronizer($synchronizer)
    {
        $this->synchronizer = $synchronizer;
    }

    private function getReportViewsByUpdateDataSets()
    {
        $em = $this->getEm();
        /** @var ReportViewDataSetRepositoryInterface $reportViewDataSetRepository */
        $reportViewDataSetRepository = $em->getRepository(ReportViewDataSet::class);

        /** @var DataSetRepositoryInterface $dataSetRepository */
        $dataSetRepository = $em->getRepository(DataSet::class);

        $updateDataSetIds = $this->updateDataSetIds;
        $this->updateDataSetIds = [];
        $reportViewIds = [];

        if (!is_array($updateDataSetIds)) {
            $updateDataSetIds = [];
        }

        foreach ($updateDataSetIds as $dataSetId) {
            $dataSet = $dataSetRepository->find($dataSetId);
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $rpDataSets = $reportViewDataSetRepository->getByDataSet($dataSet);
            if (!is_array($rpDataSets)) {
                $rpDataSets = [];
            }

            foreach ($rpDataSets as $rpDataSet) {
                if (!$rpDataSet instanceof ReportViewDataSetInterface) {
                    continue;
                }

                $reportView = $rpDataSet->getReportView();

                if (!$reportView instanceof ReportViewInterface) {
                    continue;
                }

                $reportViewIds[] = $reportView->getId();
            }
        }

        return array_unique($reportViewIds);
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEm()
    {
        return $this->em;
    }
}