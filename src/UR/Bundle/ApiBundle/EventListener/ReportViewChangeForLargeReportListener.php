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
use UR\Service\Report\ParamsBuilder;
use UR\Service\Report\ReportBuilder;
use UR\Worker\Manager;

class ReportViewChangeForLargeReportListener
{
    use LargeReportViewUtilTrait;

    /** @var  array */
    protected $updateReportViewIds = [];

    /** @var  array */
    private $deletePreCalculatedTable = [];

    /** @var array */
    private $updateDataSets = [];

    /** @var  Connection */
    private $connection;

    /** @var  Synchronizer */
    private $synchronizer;

    /** @var Manager */
    private $manager;

    /** @var  int */
    private $largeThreshold;

    /** @var  EntityManagerInterface */
    private $em;

    /**
     * ReportViewChangeForLargeReportListener constructor.
     * @param Manager $manager
     * @param $largeThreshold
     */
    public function __construct(Manager $manager, $largeThreshold)
    {
        $this->manager = $manager;
        $this->largeThreshold = $largeThreshold;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $this->em = $args->getEntityManager();
        $entity = $args->getEntity();

        if ($entity instanceof ReportViewInterface) {
            $this->updateReportViewIds[] = $entity->getId();
            return;
        }

        if ($entity instanceof ReportViewDataSetInterface) {
            $reportView = $entity->getReportView();

            if (!$reportView instanceof ReportViewInterface) {
                return;
            }

            $this->updateReportViewIds[] = $reportView->getId();
            return;
        }
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $this->em = $args->getEntityManager();
        $entity = $args->getEntity();

        if ($entity instanceof ReportViewInterface) {
            if ($args->hasChangedField(ReportBuilder::DIMENSIONS_KEY) ||
                $args->hasChangedField(ReportBuilder::METRICS_KEY) ||
                $args->hasChangedField(ReportViewInterface::REPORT_VIEW_FILTERS) ||
                $args->hasChangedField(ParamsBuilder::DATA_SET_KEY) ||
                $args->hasChangedField(ReportViewInterface::REPORT_VIEW_TRANSFORMS)
            ) {
                $this->updateReportViewIds[] = $entity->getId();
                $entity->setAvailableToRun(false);
                $entity->setAvailableToChange(false);

                if ($this->isLargeReportView($entity, $this->largeThreshold)) {
                    $entity->setPreCalculateTable(null);
                }
            }
        }

        if ($entity instanceof ReportViewDataSetInterface) {
            $reportView = $entity->getReportView();
            if ($args->hasChangedField(ReportViewInterface::REPORT_VIEW_DIMENSIONS) ||
                $args->hasChangedField(ReportViewInterface::REPORT_VIEW_METRICS) ||
                $args->hasChangedField(ReportViewInterface::REPORT_VIEW_FILTERS)
            ) {
                $this->updateReportViewIds[] = $reportView->getId();
                $reportView->setPreCalculateTable(null);
            }
        }

        if ($entity instanceof DataSetInterface) {
            if ($args->hasChangedField('totalRow') ||
                $args->hasChangedField(DataSetInterface::DIMENSIONS_COLUMN) ||
                $args->hasChangedField(DataSetInterface::METRICS_COLUMN)
            ) {
                if ($args->hasChangedField('totalRow')) {
                    $oldTotalRow = floatval($args->getOldValue('totalRow'));
                    $newTotalRow = floatval($args->getNewValue('totalRow'));

                    if ($oldTotalRow == $newTotalRow) {
                        return;
                    }
                }

                $this->updateDataSets[] = $entity->getId();
                $this->deletePreCalculatedTableByDataSet($entity);
            }
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $this->em = $args->getEntityManager();

        $entity = $args->getEntity();
        if ($entity instanceof ReportViewInterface && !empty($entity->getPreCalculateTable())) {
            $this->deletePreCalculatedTable[] = $entity->getPreCalculateTable();
        }

        if ($entity instanceof DataSetInterface) {
            $this->updateDataSets = $entity->getId();
        }

        if ($entity instanceof ReportViewDataSetInterface) {
            $reportView = $entity->getReportView();
            $this->updateReportViewIds[] = $reportView->getId();
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

        $deleteTables = array_unique($this->deletePreCalculatedTable);
        $this->deletePreCalculatedTable = [];

        if (!is_array($deleteTables)) {
            $deleteTables = [];
        }

        foreach ($deleteTables as $table) {
            //Call service delete pre calculate table
            $this->getSynchronizer()->deleteTable($table);
        }

        $reportViewIds = array_unique(array_values($this->updateReportViewIds));
        $this->updateReportViewIds = [];

        $reportViewIds = array_unique(array_merge($reportViewIds, $this->getReportViewsByUpdateDataSets()));
        if (!is_array($reportViewIds)) {
            $reportViewIds = [];
        }

        foreach ($reportViewIds as $reportViewId) {
            $reportView = $reportViewRepository->find($reportViewId);

            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            if (!$this->isLargeReportView($reportView, $this->largeThreshold)) {
                continue;
            }

            $this->manager->maintainPreCalculateTableForLargeReportView($reportViewId);
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

        $updateDataSets = $this->updateDataSets;
        $this->updateDataSets = [];
        $reportViewIds = [];

        if (!is_array($updateDataSets)) {
            $updateDataSets = [];
        }

        foreach ($updateDataSets as $dataSetId) {
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

                /** Ignore subviews */
                if ($reportView->getMasterReportView() instanceof ReportViewInterface) {
                    continue;
                }

                $reportViewIds[] = $reportView->getId();
            }
        }

        return array_unique($reportViewIds);
    }

    /**
     * @param DataSetInterface $dataSet
     */
    private function deletePreCalculatedTableByDataSet(DataSetInterface $dataSet)
    {
        /** @var ReportViewDataSetRepositoryInterface $reportViewDataSetRepository */
        $reportViewDataSetRepository = $this->getEm()->getRepository(ReportViewDataSet::class);

        $reportViewDataSets = $reportViewDataSetRepository->getByDataSet($dataSet);
        if (!is_array($reportViewDataSets)) {
            $reportViewDataSets = [];
        }

        foreach ($reportViewDataSets as $reportViewDataSet) {
            if (!$reportViewDataSet instanceof ReportViewDataSetInterface) {
                continue;
            }

            $reportView = $reportViewDataSet->getReportView();

            if (!$reportView instanceof ReportViewInterface) {
                continue;
            }

            /** Delete preCalculateTable and update report view*/

            $preCalculateTable = $reportView->getPreCalculateTable();

            if ($this->getSynchronizer() instanceof Synchronizer) {
                $this->getSynchronizer()->deleteTable($preCalculateTable);
            }

            $reportView->setPreCalculateTable(null);
            $reportView->setLargeReport(false);
            $this->em->merge($reportView);
        }
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEm()
    {
        return $this->em;
    }
}