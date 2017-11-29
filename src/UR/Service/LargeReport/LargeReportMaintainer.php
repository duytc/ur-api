<?php
namespace UR\Service\LargeReport;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use UR\Behaviors\LargeReportViewUtilTrait;
use UR\DomainManager\ReportViewManagerInterface;
use UR\Entity\Core\ReportView;
use UR\Model\Core\ReportViewInterface;
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
    public function maintainerLargeReport(ReportViewInterface $reportView)
    {
        if (!$this->isLargeReportView($reportView, $this->getLargeThreshold())) {
            return;
        }

        $this->setLockReportView($reportView, true);

        $params = $this->getParamsBuilder()->buildFromReportView($reportView);
        $dataSets = $params->getDataSets();

        if (count($dataSets) < 2) {
            $temporarySql = $this->getSqlBuilder()->buildSQLForSingleDataSet($params);
        } else {
            $temporarySql = $this->getSqlBuilder()->buildSQLForMultiDataSets($params);
        }

        $preCalculateTable = sprintf(self::PRE_CALCULATE_TABLE_TEMPLATE, $reportView->getId());
        $preCalculateSql = $this->getSqlBuilder()->buildSQLForPreCalculateTable($params, $preCalculateTable);

        try {
            $this->getConnection()->exec($temporarySql);
            gc_collect_cycles();
            $this->getConnection()->exec($preCalculateSql);
            gc_collect_cycles();

            if ($this->needDeletePreCalculateTable($reportView)) {
                $deleteSql = sprintf("DROP TABLE %s;", $preCalculateTable);
                $this->getConnection()->exec($deleteSql);
                $this->getConnection()->commit();
            } else {
                $this->updateLargeReportView($reportView, $preCalculateTable);
                gc_collect_cycles();
                $this->updateSubViews($reportView);
            }
        } catch (\Exception $e) {
            $this->updateSmallReportView($reportView);
        }

        $indexSql = $this->getSqlBuilder()->buildIndexSQLForPreCalculateTable($params, $preCalculateTable);
        try {
            $this->getConnection()->exec($indexSql);
            gc_collect_cycles();
        } catch (\Exception $e) {

        }

        $this->setLockReportView($reportView, false);
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
        $reportView->setLargeReport(true);
        $reportView->setPreCalculateTable($preCalculateTable);
    }

    /**
     * @param ReportViewInterface $reportView
     */
    private function updateSmallReportView(ReportViewInterface $reportView)
    {
        $reportView->setLargeReport(false);
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
    private function needDeletePreCalculateTable(ReportViewInterface $reportView)
    {
        $currentReportView = $this->reportViewManager->find($reportView->getId());

        return !$currentReportView instanceof ReportViewInterface;
    }
}