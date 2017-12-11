<?php
namespace UR\Service\LargeReport;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use UR\Behaviors\LargeReportViewUtilTrait;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Entity\Core\ReportView;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\Report\ParamsBuilderInterface;
use UR\Service\Report\SqlBuilderInterface;
use UR\Worker\Manager;

class LargeReportMaintainer implements LargeReportMaintainerInterface
{
    use LargeReportViewUtilTrait;
    const PRE_CALCULATE_TABLE_TEMPLATE = "pre_calculate_table_%s";

    /** @var ReportViewManagerInterface */
    protected $reportViewManager;

    /** @var SqlBuilderInterface */
    private $sqlBuilder;

    /** @var  int */
    private $largeThreshold;

    /** @var EntityManagerInterface */
    private $em;

    /** @var ParamsBuilderInterface */
    private $paramsBuilder;

    /** @var  Connection */
    private $connection;

    /** @var Manager */
    private $manager;

    public function __construct(EntityManagerInterface $em, ReportViewManagerInterface $reportViewManager, SqlBuilderInterface $sqlBuilder, $largeThreshold, ParamsBuilderInterface $paramsBuilder, Manager $manager)
    {
        $this->em = $em;
        $this->reportViewManager = $reportViewManager;
        $this->sqlBuilder = $sqlBuilder;
        $this->largeThreshold = $largeThreshold;
        $this->paramsBuilder = $paramsBuilder;
        $this->manager = $manager;
    }

    /**
     * @inheritdoc
     */
    public function maintainLargeReport(ReportViewInterface $reportView)
    {
        if (!$this->isLargeReportView($reportView, $this->getLargeThreshold())) {
            $reportView->setSmallReport();
            $this->reportViewManager->save($reportView);
            return;
        }

        /** Pre maintain: Delete old data and lock report view */
        $preCalculateTable = sprintf(self::PRE_CALCULATE_TABLE_TEMPLATE, $reportView->getId());
        $this->deleteCurrentTable($preCalculateTable);
        $this->setLockReportView($reportView, true);

        /** Build SQL for maintain large report */
        $params = $this->getParamsBuilder()->buildFromReportView($reportView);
        $dataSets = $params->getDataSets();

        if (count($dataSets) < 2) {
            $temporarySql = $this->getSqlBuilder()->buildSQLForSingleDataSet($params);
        } else {
            $temporarySql = $this->getSqlBuilder()->buildSQLForMultiDataSets($params);
        }

        $preCalculateSql = $this->getSqlBuilder()->buildSQLForPreCalculateTable($params, $preCalculateTable);

        /** Execute SQL. Handle exception here */
        try {
            $this->getConnection()->exec($temporarySql);
            gc_collect_cycles();
            $this->getConnection()->exec($preCalculateSql);
            gc_collect_cycles();

            /** Notify UI to known this report is large and not allow user change User defined dimensions */
            $this->updateLargeReportView($reportView, $preCalculateTable);
            gc_collect_cycles();

            $this->updateSubViews($reportView);
        } catch (\Exception $e) {
            $this->updateSmallReportView($reportView);
        }

        /**
         * Create indexes for using FORCE INDEX when query report on the future.
         * Indexes is enhanced feature. Exception on creating indexes not affected to Pre Calculate Table.
         * */
        $indexSql = $this->getSqlBuilder()->buildIndexSQLForPreCalculateTable($params, $preCalculateTable);
        try {
            $this->getConnection()->exec($indexSql);
            gc_collect_cycles();
        } catch (\Exception $e) {

        }

        if ($this->isExistReportView($reportView)) {
            /** Post Maintain: Unlock report view, allow user edit report view from UI */
            $this->setLockReportView($reportView, false);
        } else {
            /** Some report views are deleted when maintaining report. We need remove Pre Calculate Table for deleted reports */
            $deleteSql = sprintf("DROP TABLE %s;", $preCalculateTable);
            $this->getConnection()->exec($deleteSql);
            $this->getConnection()->commit();
        }
    }

    /**
     * @return ReportViewManagerInterface
     */
    public function getReportViewManager()
    {
        return $this->reportViewManager;
    }

    /**
     * @return SqlBuilderInterface
     */
    public function getSqlBuilder()
    {
        return $this->sqlBuilder;
    }

    /**
     * @return int
     */
    public function getLargeThreshold()
    {
        return $this->largeThreshold;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEm()
    {
        return $this->em;
    }

    /**
     * @return ParamsBuilderInterface
     */
    public function getParamsBuilder()
    {
        return $this->paramsBuilder;
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
     * @param ReportViewInterface $reportView
     * @param $isLock
     */
    private function setLockReportView(ReportViewInterface $reportView, $isLock)
    {
        $reportView->setAvailableToChange(!$isLock);
        $reportView->setAvailableToRun(!$isLock);
        $this->getReportViewManager()->save($reportView);
    }

    /**
     * @param ReportViewInterface $reportView
     * @param $preCalculateTable
     */
    private function updateLargeReportView(ReportViewInterface $reportView, $preCalculateTable)
    {
//        $reportView->setLargeReport(true);
        $reportView->setPreCalculateTable($preCalculateTable);
    }

    /**
     * @param ReportViewInterface $reportView
     */
    private function updateSmallReportView(ReportViewInterface $reportView)
    {
//        $reportView->setLargeReport(false);
        $reportView->setPreCalculateTable(null);
    }

    /**
     * @param ReportViewInterface $entity
     */
    private function updateSubViews(ReportViewInterface $entity)
    {
        $reportViewRepository = $this->em->getRepository(ReportView::class);
        $subViews = $reportViewRepository->getSubViewsByReportView($entity);

        foreach ($subViews as $subView) {
            if (!$subView instanceof ReportViewInterface) {
                continue;
            }

            if ($subView->getId() == $entity->getId()) {
                continue;
            }

            if (!$this->isLargeReportView($subView, $this->largeThreshold)) {
                continue;
            }

            $this->manager->maintainPreCalculateTableForLargeReportView($subView->getId());
        }
    }

    /**
     * @param ReportViewInterface $reportView
     * @return bool
     */
    private function isExistReportView(ReportViewInterface $reportView)
    {
        $currentReportView = $this->reportViewManager->find($reportView->getId());

        return $currentReportView instanceof ReportViewInterface;
    }

    /**
     * @param $tableName
     */
    private function deleteCurrentTable($tableName)
    {
        $sync = new Synchronizer($this->getConnection(), new Comparator());
        $sync->deleteTable($tableName);
    }
}